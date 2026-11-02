<?php

use App\Enums\RideCategory;
use App\Services\FareCalculator;
use App\Support\Pricing\RouteEstimate;

beforeEach(function () {
    config()->set('rides.fares', [
        'economy' => ['base' => 100, 'per_km' => 40, 'per_minute' => 5, 'minimum' => 200],
        'comfort' => ['base' => 200, 'per_km' => 60, 'per_minute' => 8, 'minimum' => 350],
    ]);
});

it('prices a trip as base plus distance plus time', function () {
    $quote = (new FareCalculator)->quote(RideCategory::Economy, new RouteEstimate(distanceMeters: 10_000, durationSeconds: 1_200));

    expect($quote->amount)->toBe(100 + 400 + 100)
        ->and($quote->currency)->toBe('PKR')
        ->and($quote->breakdown)->toBe(['base' => 100, 'distance' => 400, 'time' => 100, 'minimum_fare_applied' => false]);
});

it('charges the category minimum for very short trips', function () {
    $quote = (new FareCalculator)->quote(RideCategory::Comfort, new RouteEstimate(distanceMeters: 500, durationSeconds: 120));

    expect($quote->amount)->toBe(350)
        ->and($quote->breakdown['minimum_fare_applied'])->toBeTrue();
});

it('prices comfort above economy for the same route', function () {
    $route = new RouteEstimate(distanceMeters: 8_000, durationSeconds: 1_000);
    $calculator = new FareCalculator;

    expect($calculator->quote(RideCategory::Comfort, $route)->amount)
        ->toBeGreaterThan($calculator->quote(RideCategory::Economy, $route)->amount);
});
