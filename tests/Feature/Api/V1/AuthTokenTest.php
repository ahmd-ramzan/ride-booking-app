<?php

use App\Models\Driver;
use App\Models\User;

it('issues a bearer token for valid credentials', function () {
    $rider = User::factory()->rider()->create(['email' => 'rider@example.com']);

    $response = $this->postJson('/api/v1/auth/tokens', ['email' => 'rider@example.com', 'password' => 'password']);

    $response->assertCreated()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $rider->id)
        ->assertJsonPath('user.role', 'rider')
        ->assertJsonMissingPath('user.password');

    $this->withToken($response->json('token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $rider->id);
});

it('includes the driver profile for drivers', function () {
    $driver = Driver::factory()->create();

    $this->postJson('/api/v1/auth/tokens', ['email' => $driver->user->email, 'password' => 'password'])
        ->assertCreated()
        ->assertJsonPath('user.role', 'driver')
        ->assertJsonPath('user.driver.vehicle.plate', $driver->vehicle_plate);
});

it('returns 422 for wrong credentials', function () {
    User::factory()->create(['email' => 'rider@example.com']);

    $this->postJson('/api/v1/auth/tokens', ['email' => 'rider@example.com', 'password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['email' => 'These credentials do not match our records.']);
});

it('returns 429 after five failed login attempts, even with the right password', function () {
    User::factory()->create(['email' => 'rider@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/tokens', ['email' => 'rider@example.com', 'password' => 'wrong'])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/tokens', ['email' => 'rider@example.com', 'password' => 'password'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'too_many_requests')
        ->assertHeader('Retry-After');
});

it('does not count successful logins towards the failed-attempt limit', function () {
    User::factory()->create(['email' => 'rider@example.com']);

    foreach (range(1, 4) as $attempt) {
        $this->postJson('/api/v1/auth/tokens', ['email' => 'rider@example.com', 'password' => 'wrong']);
    }
    $this->postJson('/api/v1/auth/tokens', ['email' => 'rider@example.com', 'password' => 'password'])->assertCreated();

    $this->postJson('/api/v1/auth/tokens', ['email' => 'rider@example.com', 'password' => 'wrong'])->assertUnprocessable();
});

it('returns 401 with the error envelope when no token is provided', function () {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated')
        ->assertJsonStructure(['message', 'code', 'request_id'])
        ->assertHeader('X-Request-Id');
});

it('revokes the current token on logout', function () {
    User::factory()->create(['email' => 'rider@example.com']);
    $token = $this->postJson('/api/v1/auth/tokens', ['email' => 'rider@example.com', 'password' => 'password'])->json('token');

    $this->withToken($token)->deleteJson('/api/v1/auth/tokens/current')->assertNoContent();

    expect(User::query()->firstOrFail()->tokens()->count())->toBe(0);
});
