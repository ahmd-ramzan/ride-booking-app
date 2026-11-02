<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RideResource;
use App\Models\Driver;
use App\Models\Ride;
use App\Services\DriverMatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RideOfferController extends Controller
{
    /**
     * Open ride requests the authenticated driver is eligible for, nearest first.
     */
    public function __invoke(Request $request, DriverMatcher $matcher): AnonymousResourceCollection
    {
        Gate::authorize('viewOffers', Ride::class);

        /** @var Driver $driver */
        $driver = $request->user()?->driver;

        return RideResource::collection($matcher->offersFor($driver)->load('driver.user'));
    }
}
