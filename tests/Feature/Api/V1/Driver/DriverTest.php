<?php

use App\Enums\DriverStatus;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('availability', function () {
    it('puts an offline driver online at the given location', function () {
        $driver = Driver::factory()->offline()->create();
        Sanctum::actingAs($driver->user);

        $this->putJson('/api/v1/driver/availability', ['status' => 'available', 'latitude' => 31.52, 'longitude' => 74.35])
            ->assertOk()
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('data.location.latitude', 31.52);

        expect($driver->refresh()->status)->toBe(DriverStatus::Available);
    });

    it('returns 422 when going online without a location', function () {
        Sanctum::actingAs(Driver::factory()->offline()->create()->user);

        $this->putJson('/api/v1/driver/availability', ['status' => 'available'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    });

    it('returns 422 when trying to set on_trip directly', function () {
        Sanctum::actingAs(Driver::factory()->create()->user);

        $this->putJson('/api/v1/driver/availability', ['status' => 'on_trip'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });

    it('returns 409 when a driver on a trip goes offline', function () {
        $driver = Driver::factory()->create();
        Ride::factory()->acceptedBy($driver)->create();
        Sanctum::actingAs($driver->user);

        $this->putJson('/api/v1/driver/availability', ['status' => 'offline'])
            ->assertConflict()
            ->assertJsonPath('code', 'driver_on_trip');

        expect($driver->refresh()->status)->toBe(DriverStatus::OnTrip);
    });

    it('returns 403 for riders', function () {
        Sanctum::actingAs(User::factory()->rider()->create());

        $this->putJson('/api/v1/driver/availability', ['status' => 'offline'])->assertForbidden();
    });
});

describe('ride offers', function () {
    it('lists nearby requested rides in the driver\'s category, nearest first', function () {
        $driver = Driver::factory()->at(31.5102, 74.3441)->create();
        $far = Ride::factory()->create(['pickup_latitude' => 31.4826, 'pickup_longitude' => 74.3255]);
        $near = Ride::factory()->create(['pickup_latitude' => 31.5134, 'pickup_longitude' => 74.3335]);
        Ride::factory()->comfort()->create();
        Ride::factory()->acceptedBy()->create();
        Ride::factory()->create(['pickup_latitude' => 31.3680, 'pickup_longitude' => 74.1850]);
        Sanctum::actingAs($driver->user);

        $response = $this->getJson('/api/v1/driver/ride-offers');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $near->id)
            ->assertJsonPath('data.1.id', $far->id)
            ->assertJsonPath('data.0.allowed_actions', ['accept']);

        expect($response->json('data.0.distance_to_pickup_meters'))->toBeLessThan($response->json('data.1.distance_to_pickup_meters'));
    });

    it('returns no offers while the driver is offline', function () {
        $driver = Driver::factory()->offline()->create();
        Ride::factory()->create();
        Sanctum::actingAs($driver->user);

        $this->getJson('/api/v1/driver/ride-offers')->assertOk()->assertJsonCount(0, 'data');
    });

    it('returns 403 for riders', function () {
        Sanctum::actingAs(User::factory()->rider()->create());

        $this->getJson('/api/v1/driver/ride-offers')->assertForbidden();
    });
});
