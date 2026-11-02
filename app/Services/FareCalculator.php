<?php

namespace App\Services;

use App\Enums\RideCategory;
use App\Support\Pricing\FareQuote;
use App\Support\Pricing\RouteEstimate;

/**
 * Upfront pricing: base + per-km + per-minute, floored at the category minimum.
 */
class FareCalculator
{
    public function quote(RideCategory $category, RouteEstimate $route): FareQuote
    {
        /** @var array{base: int, per_km: int, per_minute: int, minimum: int} $tariff */
        $tariff = config("rides.fares.{$category->value}");

        $base = $tariff['base'];
        $distance = (int) round($route->distanceMeters / 1000 * $tariff['per_km']);
        $time = (int) round($route->durationSeconds / 60 * $tariff['per_minute']);
        $subtotal = $base + $distance + $time;

        return new FareQuote(
            category: $category,
            amount: max($subtotal, $tariff['minimum']),
            currency: (string) config('rides.currency'),
            breakdown: [
                'base' => $base,
                'distance' => $distance,
                'time' => $time,
                'minimum_fare_applied' => $subtotal < $tariff['minimum'],
            ],
        );
    }
}
