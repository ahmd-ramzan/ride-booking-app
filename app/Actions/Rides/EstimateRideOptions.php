<?php

namespace App\Actions\Rides;

use App\Enums\RideCategory;
use App\Services\DriverMatcher;
use App\Services\FareCalculator;
use App\Services\RouteEstimator;
use App\Support\Geo\Coordinates;

class EstimateRideOptions
{
    public function __construct(
        private RouteEstimator $routeEstimator,
        private FareCalculator $fareCalculator,
        private DriverMatcher $matcher,
    ) {}

    /**
     * Fare and pickup ETA for every ride category.
     *
     * @return array{
     *     route: array{distance_meters: int, duration_seconds: int},
     *     options: list<array{
     *         category: string,
     *         label: string,
     *         fare: array{amount: int, currency: string, breakdown: array{base: int, distance: int, time: int, minimum_fare_applied: bool}},
     *         pickup_eta_seconds: int|null,
     *         drivers_available: bool
     *     }>
     * }
     */
    public function handle(Coordinates $pickup, Coordinates $dropoff): array
    {
        $route = $this->routeEstimator->estimate($pickup, $dropoff);

        $options = array_map(function (RideCategory $category) use ($route, $pickup): array {
            $quote = $this->fareCalculator->quote($category, $route);
            $nearest = $this->matcher->nearestAvailableDriver($category, $pickup);

            return [
                'category' => $category->value,
                'label' => $category->label(),
                'fare' => [
                    'amount' => $quote->amount,
                    'currency' => $quote->currency,
                    'breakdown' => $quote->breakdown,
                ],
                'pickup_eta_seconds' => $nearest['eta_seconds'] ?? null,
                'drivers_available' => $nearest !== null,
            ];
        }, RideCategory::cases());

        return [
            'route' => [
                'distance_meters' => $route->distanceMeters,
                'duration_seconds' => $route->durationSeconds,
            ],
            'options' => $options,
        ];
    }
}
