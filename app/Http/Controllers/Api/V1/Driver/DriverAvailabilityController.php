<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Actions\Drivers\UpdateDriverAvailability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateDriverAvailabilityRequest;
use App\Http\Resources\Api\V1\DriverResource;
use App\Models\Driver;

class DriverAvailabilityController extends Controller
{
    /**
     * Go online (available, with a location) or offline.
     */
    public function __invoke(UpdateDriverAvailabilityRequest $request, UpdateDriverAvailability $updateAvailability): DriverResource
    {
        /** @var Driver $driver */
        $driver = $request->user()?->driver;

        return new DriverResource(
            $updateAvailability->handle($driver, $request->status(), $request->location())->load('user'),
        );
    }
}
