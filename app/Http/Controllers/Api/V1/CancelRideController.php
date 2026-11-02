<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Rides\CancelRide;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CancelRideRequest;
use App\Http\Resources\Api\V1\RideResource;
use App\Models\Ride;
use App\Models\User;

class CancelRideController extends Controller
{
    /**
     * The rider cancels a ride that has not started yet.
     */
    public function __invoke(CancelRideRequest $request, Ride $ride, CancelRide $cancelRide): RideResource
    {
        /** @var User $user */
        $user = $request->user();

        $reason = $request->validated('reason');

        return new RideResource($cancelRide->handle($ride, $user, is_string($reason) ? $reason : null)->load('driver.user'));
    }
}
