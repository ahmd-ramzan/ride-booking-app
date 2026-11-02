<?php

namespace App\Services;

use App\Support\Geo\Coordinates;
use App\Support\Pricing\RouteEstimate;

/**
 * Approximates road distance and travel time between two points.
 *
 * In production this would delegate to a routing provider (Google Routes,
 * OSRM, Mapbox) behind the same method signature.
 */
class RouteEstimator
{
    public function estimate(Coordinates $from, Coordinates $to): RouteEstimate
    {
        $roadDistanceMeters = $from->distanceTo($to) * (float) config('rides.routing.road_distance_factor');
        $metersPerSecond = (float) config('rides.routing.average_speed_kmh') * 1000 / 3600;

        return new RouteEstimate(
            distanceMeters: (int) round($roadDistanceMeters),
            durationSeconds: (int) round($roadDistanceMeters / $metersPerSecond),
        );
    }

    /**
     * Seconds for a driver at $driver to reach $pickup.
     */
    public function pickupEtaSeconds(Coordinates $driver, Coordinates $pickup): int
    {
        return max(
            (int) config('rides.routing.minimum_eta_seconds'),
            $this->estimate($driver, $pickup)->durationSeconds,
        );
    }
}
