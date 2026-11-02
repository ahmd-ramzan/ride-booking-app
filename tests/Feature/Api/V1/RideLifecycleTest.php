<?php

use App\Enums\DriverStatus;
use App\Enums\RideStatus;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('start', function () {
    it('moves an accepted ride to in progress', function () {
        $driver = Driver::factory()->create();
        $ride = Ride::factory()->acceptedBy($driver)->create();
        Sanctum::actingAs($driver->user);

        $this->postJson("/api/v1/rides/{$ride->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.allowed_actions', ['complete']);

        expect($ride->refresh()->started_at)->not->toBeNull();
    });

    it('returns 404 when a driver other than the assigned one starts the ride', function () {
        $ride = Ride::factory()->acceptedBy()->create();
        Sanctum::actingAs(Driver::factory()->create()->user);

        $this->postJson("/api/v1/rides/{$ride->id}/start")->assertNotFound();
    });
});

describe('complete', function () {
    it('completes the ride, settles the fare and frees the driver at the drop-off', function () {
        $driver = Driver::factory()->create();
        $ride = Ride::factory()->inProgressWith($driver)->create();
        Sanctum::actingAs($driver->user);

        $this->postJson("/api/v1/rides/{$ride->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.fare.final', $ride->estimated_fare)
            ->assertJsonPath('data.allowed_actions', []);

        $driver->refresh();

        expect($driver->status)->toBe(DriverStatus::Available)
            ->and($driver->latitude)->toBe($ride->dropoff_latitude)
            ->and($driver->longitude)->toBe($ride->dropoff_longitude);
    });
});

describe('cancel', function () {
    it('lets the rider cancel a requested ride', function () {
        $ride = Ride::factory()->create();
        Sanctum::actingAs($ride->rider);

        $this->postJson("/api/v1/rides/{$ride->id}/cancel", ['reason' => 'Changed my plans'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation.cancelled_by', 'rider')
            ->assertJsonPath('data.cancellation.reason', 'Changed my plans');
    });

    it('frees the assigned driver when an accepted ride is cancelled', function () {
        $driver = Driver::factory()->create();
        $ride = Ride::factory()->acceptedBy($driver)->create();
        Sanctum::actingAs($ride->rider);

        $this->postJson("/api/v1/rides/{$ride->id}/cancel")->assertOk();

        expect($driver->refresh()->status)->toBe(DriverStatus::Available);
    });

    it('returns 403 when the assigned driver tries to cancel', function () {
        $driver = Driver::factory()->create();
        $ride = Ride::factory()->acceptedBy($driver)->create();
        Sanctum::actingAs($driver->user);

        $this->postJson("/api/v1/rides/{$ride->id}/cancel")
            ->assertForbidden()
            ->assertJsonPath('message', 'Only the rider can cancel this ride.');
    });

    it('returns 422 for a reason longer than 255 characters', function () {
        $ride = Ride::factory()->create();
        Sanctum::actingAs($ride->rider);

        $this->postJson("/api/v1/rides/{$ride->id}/cancel", ['reason' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    });
});

it('returns 409 for invalid ride state transitions', function (string $state, string $action, string $actor) {
    $driver = Driver::factory()->create();
    $ride = match ($state) {
        'requested' => Ride::factory()->create(),
        'accepted' => Ride::factory()->acceptedBy($driver)->create(),
        'in_progress' => Ride::factory()->inProgressWith($driver)->create(),
        'completed' => Ride::factory()->completed()->create(['driver_id' => $driver->id]),
        'cancelled' => Ride::factory()->cancelled()->create(['driver_id' => $driver->id]),
    };
    Sanctum::actingAs($actor === 'rider' ? $ride->rider : $driver->user);

    $this->postJson("/api/v1/rides/{$ride->id}/{$action}")
        ->assertConflict()
        ->assertJsonPath('code', 'invalid_ride_transition')
        ->assertJsonPath('details.current_status', $state);

    expect($ride->refresh()->status)->toBe(RideStatus::from($state));
})->with([
    'complete before start' => ['accepted', 'complete', 'driver'],
    'start twice' => ['in_progress', 'start', 'driver'],
    'cancel an in-progress ride' => ['in_progress', 'cancel', 'rider'],
    'cancel a completed ride' => ['completed', 'cancel', 'rider'],
    'cancel twice' => ['cancelled', 'cancel', 'rider'],
    'complete a cancelled ride' => ['cancelled', 'complete', 'driver'],
    'start a completed ride' => ['completed', 'start', 'driver'],
]);

describe('show', function () {
    it('shows the ride to its rider with the assigned driver and pickup ETA', function () {
        $ride = Ride::factory()->acceptedBy(Driver::factory()->at(31.5150, 74.3400)->create())->create();
        Sanctum::actingAs($ride->rider);

        $response = $this->getJson("/api/v1/rides/{$ride->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $ride->id)
            ->assertJsonPath('data.allowed_actions', ['cancel']);

        expect($response->json('data.driver.pickup_eta_seconds'))->toBeInt()->toBeGreaterThanOrEqual(60);
    });

    it('returns 404 for another rider\'s ride', function () {
        $ride = Ride::factory()->create();
        Sanctum::actingAs(User::factory()->rider()->create());

        $this->getJson("/api/v1/rides/{$ride->id}")->assertNotFound();
    });
});

describe('index', function () {
    it('lists only the current rider\'s rides, filtered to active ones', function () {
        $rider = User::factory()->rider()->create();
        $active = Ride::factory()->for($rider, 'rider')->create();
        Ride::factory()->for($rider, 'rider')->completed()->create();
        Ride::factory()->create();
        Sanctum::actingAs($rider);

        $this->getJson('/api/v1/rides?status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);
    });
});
