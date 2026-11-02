<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Rides\CompleteRide;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RideResource;
use App\Models\Driver;
use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompleteRideController extends Controller
{
    /**
     * The assigned driver has dropped the rider off; the fare is settled.
     */
    public function __invoke(Request $request, Ride $ride, CompleteRide $completeRide): RideResource
    {
        Gate::authorize('complete', $ride);

        /** @var Driver $driver */
        $driver = $request->user()?->driver;

        return new RideResource($completeRide->handle($ride, $driver)->load('driver.user'));
    }
}
