<?php

/*
 * Test helper: performs one ride operation in its own PHP process (its own
 * database connection), so concurrency tests exercise real PostgreSQL locking.
 *
 * Usage:
 *   php worker.php <start-at-unix-float> accept  <ride-id> <driver-id>
 *   php worker.php <start-at-unix-float> request <rider-id>
 *
 * All workers boot, load their data, then spin until <start-at> so that they
 * hit the database at (nearly) the same instant. Prints "ok" or the error code.
 */

use App\Actions\Rides\AcceptRide;
use App\Actions\Rides\RequestRide;
use App\Enums\RideCategory;
use App\Exceptions\ApiException;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\User;
use App\Support\Geo\Coordinates;
use App\Support\Geo\Location;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $startAt, $operation] = $argv;

$run = match ($operation) {
    'accept' => (function () use ($argv): Closure {
        $ride = Ride::query()->findOrFail($argv[3]);
        $driver = Driver::query()->findOrFail((int) $argv[4]);

        return fn () => app(AcceptRide::class)->handle($ride, $driver);
    })(),
    'request' => (function () use ($argv): Closure {
        $rider = User::query()->findOrFail((int) $argv[3]);
        $pickup = new Location('Liberty Market', new Coordinates(31.5102, 74.3441));
        $dropoff = new Location('Packages Mall', new Coordinates(31.4711, 74.3555));

        return fn () => app(RequestRide::class)->handle($rider, RideCategory::Economy, $pickup, $dropoff);
    })(),
    default => throw new InvalidArgumentException("Unknown operation [{$operation}]"),
};

while (microtime(true) < (float) $startAt) {
    usleep(100);
}

try {
    $run();
    echo 'ok';
} catch (ApiException $exception) {
    echo $exception->errorCode();
}
