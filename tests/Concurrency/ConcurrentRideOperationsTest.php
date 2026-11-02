<?php

use App\Enums\DriverStatus;
use App\Enums\RideStatus;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

/*
 * These tests start several PHP processes that perform the same operation at
 * the same instant against the real test database, then check that exactly
 * one of them won. They are slower than feature tests (each process boots the
 * framework) and are grouped as "concurrency".
 */

/**
 * @param  list<list<string>>  $commands  Worker arguments after the start timestamp.
 * @return list<string> The output of each worker.
 */
function runSimultaneously(array $commands): array
{
    $startAt = (string) (microtime(true) + 4);
    $environment = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => config('database.default'),
        'DB_DATABASE' => config('database.connections.'.config('database.default').'.database'),
        'LOG_CHANNEL' => 'null',
    ];

    $results = Process::pool(function (Pool $pool) use ($commands, $startAt, $environment) {
        foreach ($commands as $arguments) {
            $pool->path(base_path())
                ->env($environment)
                ->timeout(60)
                ->command([PHP_BINARY, 'tests/Concurrency/worker.php', $startAt, ...$arguments]);
        }
    })->start()->wait();

    return collect($results->collect())
        ->map(function ($result): string {
            expect($result->errorOutput())->toBe('');

            return trim($result->output());
        })
        ->values()
        ->all();
}

it('lets exactly one of many drivers accept the same ride', function () {
    $ride = Ride::factory()->create();
    $drivers = Driver::factory()->count(6)->create();

    $outcomes = runSimultaneously(
        $drivers->map(fn (Driver $driver) => ['accept', $ride->id, (string) $driver->id])->all(),
    );

    expect(array_count_values($outcomes))->toEqual(['ok' => 1, 'ride_already_assigned' => 5]);

    $ride->refresh();
    expect($ride->status)->toBe(RideStatus::Accepted)
        ->and(Driver::query()->where('status', DriverStatus::OnTrip)->pluck('id')->all())->toBe([$ride->driver_id])
        ->and($ride->events()->where('to_status', RideStatus::Accepted)->count())->toBe(1);
})->group('concurrency');

it('lets one driver accept only one of several rides requested at the same time', function () {
    $rides = Ride::factory()->count(4)->create();
    $driver = Driver::factory()->create();

    $outcomes = runSimultaneously(
        $rides->map(fn (Ride $ride) => ['accept', $ride->id, (string) $driver->id])->all(),
    );

    expect(array_count_values($outcomes))->toEqual(['ok' => 1, 'driver_not_eligible' => 3])
        ->and(Ride::query()->where('driver_id', $driver->id)->count())->toBe(1);
})->group('concurrency');

it('creates only one ride when the same rider submits several requests at once', function () {
    $rider = User::factory()->rider()->create();

    $outcomes = runSimultaneously(array_fill(0, 5, ['request', (string) $rider->id]));

    expect(array_count_values($outcomes))->toEqual(['ok' => 1, 'active_ride_exists' => 4])
        ->and(Ride::query()->whereBelongsTo($rider, 'rider')->count())->toBe(1);
})->group('concurrency');
