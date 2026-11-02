<?php

use App\Enums\RideStatus;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('creates a ride request with a server-computed fare', function () {
    $rider = User::factory()->rider()->create();
    Sanctum::actingAs($rider);

    $response = $this->postJson('/api/v1/rides', ridePayload(['estimated_fare' => 1]), idempotencyHeader());

    $response->assertCreated()
        ->assertHeader('Location')
        ->assertJsonPath('data.status', 'requested')
        ->assertJsonPath('data.category', 'economy')
        ->assertJsonPath('data.pickup.address', 'Liberty Market, Gulberg III')
        ->assertJsonPath('data.driver', null)
        ->assertJsonPath('data.allowed_actions', ['cancel']);

    $ride = Ride::query()->sole();

    expect($ride->rider_id)->toBe($rider->id)
        ->and($ride->status)->toBe(RideStatus::Requested)
        ->and($ride->estimated_fare)->toBeGreaterThan(200)
        ->and($response->json('data.fare.estimated'))->toBe($ride->estimated_fare)
        ->and($ride->events()->sole()->to_status)->toBe(RideStatus::Requested);
});

it('returns 422 listing every missing field for an empty payload', function () {
    Sanctum::actingAs(User::factory()->rider()->create());

    $this->postJson('/api/v1/rides', [], idempotencyHeader())
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['category', 'pickup', 'dropoff']);

    expect(Ride::query()->count())->toBe(0);
});

it('returns 422 for invalid ride input', function (array $overrides, string $field, string $message) {
    Sanctum::actingAs(User::factory()->rider()->create());

    $this->postJson('/api/v1/rides', ridePayload($overrides), idempotencyHeader())
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field => $message]);
})->with([
    'unknown category' => [['category' => 'helicopter'], 'category', 'The selected category is invalid.'],
    'pickup outside Lahore' => [['pickup' => ['latitude' => 24.8607]], 'pickup.latitude', 'The pickup latitude must be inside the Lahore service area.'],
    'non numeric longitude' => [['dropoff' => ['longitude' => 'east']], 'dropoff.longitude', 'The destination longitude field must be a number.'],
    'missing address' => [['pickup' => ['address' => '']], 'pickup.address', 'The pickup address field is required.'],
    'destination too close' => [['dropoff' => ['latitude' => 31.5103, 'longitude' => 74.3442]], 'dropoff', 'The destination must be at least 200 m away from the pickup.'],
]);

it('returns 409 when the rider already has an active ride', function () {
    $rider = User::factory()->rider()->create();
    $activeRide = Ride::factory()->for($rider, 'rider')->create();
    Sanctum::actingAs($rider);

    $this->postJson('/api/v1/rides', ridePayload(), idempotencyHeader())
        ->assertConflict()
        ->assertJsonPath('code', 'active_ride_exists')
        ->assertJsonPath('details.active_ride_id', $activeRide->id);

    expect(Ride::query()->count())->toBe(1);
});

it('allows a new request once the previous ride is finished', function () {
    $rider = User::factory()->rider()->create();
    Ride::factory()->for($rider, 'rider')->completed()->create();
    Ride::factory()->for($rider, 'rider')->cancelled()->create();
    Sanctum::actingAs($rider);

    $this->postJson('/api/v1/rides', ridePayload(), idempotencyHeader())->assertCreated();
});

it('returns 403 when a driver requests a ride', function () {
    Sanctum::actingAs(Driver::factory()->create()->user);

    $this->postJson('/api/v1/rides', ridePayload(), idempotencyHeader())
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');
});

it('returns 401 without a token', function () {
    $this->postJson('/api/v1/rides', ridePayload(), idempotencyHeader())->assertUnauthorized();
});
