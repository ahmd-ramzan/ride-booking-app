<?php

use App\Models\Driver;
use App\Models\IdempotencyKey;
use App\Models\Ride;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 400 when a ride request has no Idempotency-Key', function () {
    Sanctum::actingAs(User::factory()->rider()->create());

    $this->postJson('/api/v1/rides', ridePayload())
        ->assertBadRequest()
        ->assertJsonPath('code', 'idempotency_key_required');

    expect(Ride::query()->count())->toBe(0);
});

it('returns 400 for a malformed Idempotency-Key', function () {
    Sanctum::actingAs(User::factory()->rider()->create());

    $this->postJson('/api/v1/rides', ridePayload(), idempotencyHeader('short'))
        ->assertBadRequest()
        ->assertJsonPath('code', 'idempotency_key_invalid');
});

it('replays the original response for a retried ride request without creating a duplicate', function () {
    Sanctum::actingAs(User::factory()->rider()->create());

    $first = $this->postJson('/api/v1/rides', ridePayload(), idempotencyHeader('retry-key-0001'));
    $retry = $this->postJson('/api/v1/rides', ridePayload(), idempotencyHeader('retry-key-0001'));

    $first->assertCreated();
    $retry->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect(Ride::query()->count())->toBe(1);
});

it('returns 422 when an Idempotency-Key is reused with a different payload', function () {
    Sanctum::actingAs(User::factory()->rider()->create());
    $this->postJson('/api/v1/rides', ridePayload(), idempotencyHeader('reused-key-0001'))->assertCreated();

    $this->postJson('/api/v1/rides', ridePayload(['category' => 'comfort']), idempotencyHeader('reused-key-0001'))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'idempotency_key_reused');
});

it('returns 409 while the original request with the same key is still in flight', function () {
    $rider = User::factory()->rider()->create();
    Sanctum::actingAs($rider);
    $this->postJson('/api/v1/rides', ridePayload(), idempotencyHeader('inflight-key-01'))->assertCreated();
    IdempotencyKey::query()->update(['response_status' => null, 'response_body' => null, 'locked_until' => now()->addSeconds(30)]);

    $this->postJson('/api/v1/rides', ridePayload(), idempotencyHeader('inflight-key-01'))
        ->assertConflict()
        ->assertJsonPath('code', 'idempotency_request_in_progress')
        ->assertHeader('Retry-After', '1');
});

it('replays a stored error response instead of re-running the request', function () {
    $rider = User::factory()->rider()->create();
    Ride::factory()->for($rider, 'rider')->create();
    Sanctum::actingAs($rider);
    $this->postJson('/api/v1/rides', ridePayload(), idempotencyHeader('error-key-0001'))->assertConflict();

    $this->postJson('/api/v1/rides', ridePayload(), idempotencyHeader('error-key-0001'))
        ->assertConflict()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertJsonPath('code', 'active_ride_exists');
});

it('scopes idempotency keys to the user', function () {
    Sanctum::actingAs(User::factory()->rider()->create());
    $this->postJson('/api/v1/rides', ridePayload(), idempotencyHeader('shared-key-0001'))->assertCreated();

    Sanctum::actingAs(User::factory()->rider()->create());
    $this->postJson('/api/v1/rides', ridePayload(), idempotencyHeader('shared-key-0001'))
        ->assertCreated()
        ->assertHeaderMissing('Idempotent-Replayed');

    expect(Ride::query()->count())->toBe(2);
});

it('replays a retried accept for the driver who made it', function () {
    $ride = Ride::factory()->create();
    $driver = Driver::factory()->create();
    Sanctum::actingAs($driver->user);

    $this->postJson("/api/v1/rides/{$ride->id}/accept", [], idempotencyHeader('accept-key-001'))->assertOk();

    $this->postJson("/api/v1/rides/{$ride->id}/accept", [], idempotencyHeader('accept-key-001'))
        ->assertOk()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertJsonPath('data.status', 'accepted');
});
