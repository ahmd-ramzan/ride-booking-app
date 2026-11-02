<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Rides\AcceptRide;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RideResource;
use App\Models\Driver;
use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AcceptRideController extends Controller
{
    /**
     * The authenticated driver accepts the ride and is assigned to it.
     */
    public function __invoke(Request $request, Ride $ride, AcceptRide $acceptRide): RideResource
    {
        Gate::authorize('accept', $ride);

        /** @var Driver $driver */
        $driver = $request->user()?->driver;

        return new RideResource($acceptRide->handle($ride, $driver)->load('driver.user'));
    }
}
