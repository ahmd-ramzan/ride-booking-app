<?php

namespace App\Services;

use App\Enums\DriverStatus;
use App\Enums\RideCategory;
use App\Enums\RideStatus;
use App\Exceptions\DriverNotEligibleException;
use App\Models\Driver;
use App\Models\Ride;
use App\Support\Geo\Coordinates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Simplified nearby-driver discovery.
 *
 * Uses a bounding-box prefilter (index friendly) followed by an exact haversine
 * distance computed in PostgreSQL. In production this would be replaced by a
 * geospatial index (PostGIS `ST_DWithin` on a GiST index, or Redis GEO / H3
 * cells fed by a driver-location stream). See docs/ARCHITECTURE.md.
 */
class DriverMatcher
{
    private const float KM_PER_DEGREE_LATITUDE = 111.32;

    public function __construct(private RouteEstimator $routeEstimator) {}

    /**
     * Closest available driver of the given category within the matching radius.
     *
     * @return array{driver: Driver, distance_meters: int, eta_seconds: int}|null
     */
    public function nearestAvailableDriver(RideCategory $category, Coordinates $pickup): ?array
    {
        /** @var Driver|null $driver */
        $driver = Driver::query()
            ->select('drivers.*')
            ->where('status', DriverStatus::Available)
            ->where('vehicle_category', $category)
            ->tap(fn (Builder $query) => $this->withinRadius($query, 'latitude', 'longitude', $pickup))
            ->orderBy('match_distance_meters')
            ->orderBy('id')
            ->first();

        if ($driver === null) {
            return null;
        }

        /** @var Coordinates $driverCoordinates */
        $driverCoordinates = $driver->coordinates();

        return [
            'driver' => $driver,
            'distance_meters' => (int) round($driverCoordinates->distanceTo($pickup)),
            'eta_seconds' => $this->routeEstimator->pickupEtaSeconds($driverCoordinates, $pickup),
        ];
    }

    /**
     * Open ride requests this driver is eligible to accept, nearest first.
     *
     * @return Collection<int, Ride>
     */
    public function offersFor(Driver $driver): Collection
    {
        $coordinates = $driver->coordinates();

        if ($driver->status !== DriverStatus::Available || $coordinates === null) {
            return new Collection;
        }

        return Ride::query()
            ->select('rides.*')
            ->where('status', RideStatus::Requested)
            ->where('category', $driver->vehicle_category)
            ->tap(fn (Builder $query) => $this->withinRadius($query, 'pickup_latitude', 'pickup_longitude', $coordinates))
            ->orderBy('match_distance_meters')
            ->orderBy('requested_at')
            ->limit((int) config('rides.matching.offers_limit'))
            ->get();
    }

    /**
     * Throws when the driver may not accept the ride. Must be called with both
     * rows locked so that the checked state cannot change before the write.
     *
     * @throws DriverNotEligibleException
     */
    public function ensureEligible(Driver $driver, Ride $ride): void
    {
        if ($driver->status !== DriverStatus::Available) {
            throw new DriverNotEligibleException(DriverNotEligibleException::NOT_AVAILABLE);
        }

        if ($driver->vehicle_category !== $ride->category) {
            throw new DriverNotEligibleException(DriverNotEligibleException::CATEGORY_MISMATCH);
        }

        $coordinates = $driver->coordinates();

        if ($coordinates === null) {
            throw new DriverNotEligibleException(DriverNotEligibleException::LOCATION_UNKNOWN);
        }

        if ($coordinates->distanceTo($ride->pickup()->coordinates) > $this->radiusMeters()) {
            throw new DriverNotEligibleException(DriverNotEligibleException::TOO_FAR);
        }
    }

    private function radiusMeters(): float
    {
        return (float) config('rides.matching.max_pickup_distance_km') * 1000;
    }

    /**
     * Adds a bounding-box prefilter, an exact distance filter, and a
     * `match_distance_meters` select column (meters from $origin).
     *
     * Column names are compile-time literals (never user input); every value is bound.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  literal-string  $latitudeColumn
     * @param  literal-string  $longitudeColumn
     */
    private function withinRadius(Builder $query, string $latitudeColumn, string $longitudeColumn, Coordinates $origin): void
    {
        $radiusKm = $this->radiusMeters() / 1000;
        $latitudeDelta = $radiusKm / self::KM_PER_DEGREE_LATITUDE;
        $longitudeDelta = $radiusKm / (self::KM_PER_DEGREE_LATITUDE * cos(deg2rad($origin->latitude)));

        $haversine = "6371000 * 2 * asin(least(1, sqrt(
            power(sin(radians({$latitudeColumn} - ?) / 2), 2)
            + cos(radians(?)) * cos(radians({$latitudeColumn})) * power(sin(radians({$longitudeColumn} - ?) / 2), 2)
        )))";
        $bindings = [$origin->latitude, $origin->latitude, $origin->longitude];

        $query
            ->whereNotNull($latitudeColumn)
            ->whereNotNull($longitudeColumn)
            ->whereBetween($latitudeColumn, [$origin->latitude - $latitudeDelta, $origin->latitude + $latitudeDelta])
            ->whereBetween($longitudeColumn, [$origin->longitude - $longitudeDelta, $origin->longitude + $longitudeDelta])
            ->whereRaw("{$haversine} <= ?", [...$bindings, $this->radiusMeters()])
            ->selectRaw("{$haversine} as match_distance_meters", $bindings);
    }
}
