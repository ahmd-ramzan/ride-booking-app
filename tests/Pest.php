<?php

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run inside a transaction that is rolled back after each test.
| Concurrency tests spawn separate PHP processes that must see committed
| data, so they truncate tables instead.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * A valid ride request body: Liberty Market to Packages Mall (Lahore).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ridePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'category' => 'economy',
        'pickup' => ['address' => 'Liberty Market, Gulberg III', 'latitude' => 31.5102, 'longitude' => 74.3441],
        'dropoff' => ['address' => 'Packages Mall, Walton Road', 'latitude' => 31.4711, 'longitude' => 74.3555],
    ], $overrides);
}

/**
 * @return array<string, string>
 */
function idempotencyHeader(string $key = 'test-key-0001'): array
{
    return ['Idempotency-Key' => $key];
}
