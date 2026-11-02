<?php

use App\Models\Driver;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function estimateQuery(): string
{
    return http_build_query([
        'pickup_latitude' => 31.5102,
        'pickup_longitude' => 74.3441,
        'dropoff_latitude' => 31.4711,
        'dropoff_longitude' => 74.3555,
    ]);
}

it('returns a fare and pickup ETA for each ride category', function () {
    Driver::factory()->at(31.5150, 74.3400)->create();
    Driver::factory()->comfort()->at(31.5200, 74.3550)->create();
    Sanctum::actingAs(User::factory()->rider()->create());

    $response = $this->getJson('/api/v1/fare-estimates?'.estimateQuery());

    $response->assertOk()
        ->assertJsonPath('data.options.0.category', 'economy')
        ->assertJsonPath('data.options.0.drivers_available', true)
        ->assertJsonPath('data.options.1.category', 'comfort')
        ->assertJsonPath('data.options.1.fare.currency', 'PKR');

    expect($response->json('data.route.distance_meters'))->toBeGreaterThan(4_480)
        ->and($response->json('data.options.0.pickup_eta_seconds'))->toBeInt()->toBeGreaterThanOrEqual(60)
        ->and($response->json('data.options.1.fare.amount'))->toBeGreaterThan($response->json('data.options.0.fare.amount'));
});

it('reports no pickup ETA when no eligible driver is nearby', function () {
    Driver::factory()->offline()->create();
    Driver::factory()->onTrip()->create();
    Driver::factory()->at(31.3680, 74.1850)->create(); // Bahria Town: outside the matching radius
    Sanctum::actingAs(User::factory()->rider()->create());

    $this->getJson('/api/v1/fare-estimates?'.estimateQuery())
        ->assertOk()
        ->assertJsonPath('data.options.0.pickup_eta_seconds', null)
        ->assertJsonPath('data.options.0.drivers_available', false);
});

it('returns 422 for coordinates outside the service area', function () {
    Sanctum::actingAs(User::factory()->rider()->create());

    $this->getJson('/api/v1/fare-estimates?pickup_latitude=24.86&pickup_longitude=67.00&dropoff_latitude=31.47&dropoff_longitude=74.35')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pickup_latitude', 'pickup_longitude']);
});
