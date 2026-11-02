<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Rides\RequestRide;
use App\Enums\RideStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRideRequest;
use App\Http\Resources\Api\V1\RideResource;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RideController extends Controller
{
    /**
     * Rides of the current user: requested rides for riders, assigned rides for drivers.
     * `?status=active` limits the list to rides that are not finished.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['sometimes', Rule::in(['active', ...array_column(RideStatus::cases(), 'value')])],
        ]);

        /** @var User $user */
        $user = $request->user();

        $rides = Ride::query()
            ->when(
                $user->isDriver(),
                fn ($query) => $query->where('driver_id', $user->driver?->id),
                fn ($query) => $query->whereBelongsTo($user, 'rider'),
            )
            ->when($request->query('status') === 'active', fn ($query) => $query->active())
            ->when(
                $request->filled('status') && $request->query('status') !== 'active',
                fn ($query) => $query->where('status', $request->query('status')),
            )
            ->with('driver.user')
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return RideResource::collection($rides);
    }

    /**
     * Request a ride. Requires an Idempotency-Key header.
     */
    public function store(StoreRideRequest $request, RequestRide $requestRide): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();

        $ride = $requestRide->handle($rider, $request->category(), $request->pickup(), $request->dropoff());

        return (new RideResource($ride->load('driver.user')))
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('api.v1.rides.show', $ride));
    }

    public function show(Ride $ride): RideResource
    {
        Gate::authorize('view', $ride);

        return new RideResource($ride->load('driver.user'));
    }
}
