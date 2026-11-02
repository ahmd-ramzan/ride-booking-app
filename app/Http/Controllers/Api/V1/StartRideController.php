<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Rides\StartRide;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RideResource;
use App\Models\Driver;
use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StartRideController extends Controller
{
    /**
     * The assigned driver has picked the rider up.
     */
    public function __invoke(Request $request, Ride $ride, StartRide $startRide): RideResource
    {
        Gate::authorize('start', $ride);

        /** @var Driver $driver */
        $driver = $request->user()?->driver;

        return new RideResource($startRide->handle($ride, $driver)->load('driver.user'));
    }
}
