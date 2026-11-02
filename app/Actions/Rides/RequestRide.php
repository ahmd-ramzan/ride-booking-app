<?php

namespace App\Actions\Rides;

use App\Enums\RideCategory;
use App\Enums\RideStatus;
use App\Exceptions\ActiveRideExistsException;
use App\Models\Ride;
use App\Models\User;
use App\Services\FareCalculator;
use App\Services\RideLifecycle;
use App\Services\RouteEstimator;
use App\Support\Geo\Location;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class RequestRide
{
    public function __construct(
        private RouteEstimator $routeEstimator,
        private FareCalculator $fareCalculator,
        private RideLifecycle $lifecycle,
    ) {}

    /**
     * Creates a ride request with a server-computed upfront fare.
     *
     * A rider may have at most one active ride. The pre-check gives a friendly
     * error; the partial unique index `rides_one_active_per_rider` guarantees it
     * when two requests race past the pre-check.
     *
     * @throws ActiveRideExistsException
     */
    public function handle(User $rider, RideCategory $category, Location $pickup, Location $dropoff): Ride
    {
        $route = $this->routeEstimator->estimate($pickup->coordinates, $dropoff->coordinates);
        $quote = $this->fareCalculator->quote($category, $route);

        try {
            return DB::transaction(function () use ($rider, $category, $pickup, $dropoff, $route, $quote): Ride {
                $activeRideId = $rider->rides()->active()->value('id');

                if ($activeRideId !== null) {
                    throw new ActiveRideExistsException($activeRideId);
                }

                $ride = new Ride;
                $ride->forceFill([
                    'rider_id' => $rider->id,
                    'category' => $category,
                    'status' => RideStatus::Requested,
                    'pickup_address' => $pickup->address,
                    'pickup_latitude' => $pickup->coordinates->latitude,
                    'pickup_longitude' => $pickup->coordinates->longitude,
                    'dropoff_address' => $dropoff->address,
                    'dropoff_latitude' => $dropoff->coordinates->latitude,
                    'dropoff_longitude' => $dropoff->coordinates->longitude,
                    'distance_meters' => $route->distanceMeters,
                    'duration_seconds' => $route->durationSeconds,
                    'currency' => $quote->currency,
                    'estimated_fare' => $quote->amount,
                    'fare_breakdown' => $quote->breakdown,
                    'requested_at' => now(),
                ])->save();

                $this->lifecycle->record($ride, null, $rider);

                return $ride;
            });
        } catch (UniqueConstraintViolationException) {
            throw new ActiveRideExistsException($rider->rides()->active()->value('id'));
        }
    }
}
