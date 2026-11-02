<?php

use App\Enums\DriverStatus;
use App\Enums\RideStatus;
use App\Models\Driver;
use App\Models\Ride;
use Laravel\Sanctum\Sanctum;

it('assigns the ride to the accepting driver and puts the driver on trip', function () {
    $ride = Ride::factory()->create();
    $driver = Driver::factory()->create();
    Sanctum::actingAs($driver->user);

    $this->postJson("/api/v1/rides/{$ride->id}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted')
        ->assertJsonPath('data.driver.id', $driver->id)
        ->assertJsonPath('data.driver.vehicle.plate', $driver->vehicle_plate)
        ->assertJsonPath('data.allowed_actions', ['start']);

    $ride->refresh();

    expect($ride->status)->toBe(RideStatus::Accepted)
        ->and($ride->driver_id)->toBe($driver->id)
        ->and($ride->accepted_at)->not->toBeNull()
        ->and($driver->refresh()->status)->toBe(DriverStatus::OnTrip)
        ->and($ride->events()->latest('id')->first()?->driver_id)->toBe($driver->id);
});

it('returns 409 ride_already_assigned when a second driver accepts the same ride', function () {
    $firstDriver = Driver::factory()->create();
    $ride = Ride::factory()->acceptedBy($firstDriver)->create();
    $secondDriver = Driver::factory()->create();
    Sanctum::actingAs($secondDriver->user);

    $this->postJson("/api/v1/rides/{$ride->id}/accept")
        ->assertConflict()
        ->assertJsonPath('code', 'ride_already_assigned');

    expect($ride->refresh()->driver_id)->toBe($firstDriver->id)
        ->and($secondDriver->refresh()->status)->toBe(DriverStatus::Available);
});

it('returns the ride unchanged when the assigned driver retries the accept', function () {
    $driver = Driver::factory()->create();
    $ride = Ride::factory()->acceptedBy($driver)->create();
    Sanctum::actingAs($driver->user);

    $this->postJson("/api/v1/rides/{$ride->id}/accept")
        ->assertOk()
        ->assertJsonPath('data.driver.id', $driver->id);

    expect($ride->events()->count())->toBe(0);
});

it('returns 409 driver_not_eligible for an ineligible driver', function (Closure $makeDriver, string $reason) {
    $ride = Ride::factory()->create();
    $driver = $makeDriver();
    Sanctum::actingAs($driver->user);

    $this->postJson("/api/v1/rides/{$ride->id}/accept")
        ->assertConflict()
        ->assertJsonPath('code', 'driver_not_eligible')
        ->assertJsonPath('details.reason', $reason);

    expect($ride->refresh()->status)->toBe(RideStatus::Requested);
})->with([
    'offline' => [fn () => Driver::factory()->offline()->create(), 'driver_not_available'],
    'already on a trip' => [fn () => Driver::factory()->onTrip()->create(), 'driver_not_available'],
    'wrong vehicle category' => [fn () => Driver::factory()->comfort()->create(), 'vehicle_category_mismatch'],
    'too far from pickup' => [fn () => Driver::factory()->at(31.3680, 74.1850)->create(), 'too_far_from_pickup'],
]);

it('does not let a driver with an active ride accept another', function () {
    $driver = Driver::factory()->create();
    Ride::factory()->acceptedBy($driver)->create();
    $otherRide = Ride::factory()->create();
    Sanctum::actingAs($driver->user);

    $this->postJson("/api/v1/rides/{$otherRide->id}/accept")
        ->assertConflict()
        ->assertJsonPath('details.reason', 'driver_not_available');
});

it('returns 409 invalid_ride_transition when accepting a cancelled ride', function () {
    $ride = Ride::factory()->cancelled()->create();
    Sanctum::actingAs(Driver::factory()->create()->user);

    $this->postJson("/api/v1/rides/{$ride->id}/accept")
        ->assertConflict()
        ->assertJsonPath('code', 'invalid_ride_transition')
        ->assertJsonPath('details.current_status', 'cancelled');
});

it('returns 403 when the rider tries to accept their own ride', function () {
    $ride = Ride::factory()->create();
    Sanctum::actingAs($ride->rider);

    $this->postJson("/api/v1/rides/{$ride->id}/accept")->assertForbidden();
});

it('returns 404 for an unknown ride', function () {
    Sanctum::actingAs(Driver::factory()->create()->user);

    $this->postJson('/api/v1/rides/01JUNKNOWNRIDE00000000000/accept')
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
});
