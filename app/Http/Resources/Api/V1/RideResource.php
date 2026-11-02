<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use App\Services\RouteEstimator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ride
 */
class RideResource extends JsonResource
{
    /**
     * Ride actions and the status each one moves the ride to.
     */
    private const array ACTIONS = [
        'accept' => RideStatus::Accepted,
        'start' => RideStatus::InProgress,
        'complete' => RideStatus::Completed,
        'cancel' => RideStatus::Cancelled,
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'category' => $this->category->value,
            'pickup' => [
                'address' => $this->pickup_address,
                'latitude' => $this->pickup_latitude,
                'longitude' => $this->pickup_longitude,
            ],
            'dropoff' => [
                'address' => $this->dropoff_address,
                'latitude' => $this->dropoff_latitude,
                'longitude' => $this->dropoff_longitude,
            ],
            'route' => [
                'distance_meters' => $this->distance_meters,
                'duration_seconds' => $this->duration_seconds,
            ],
            'fare' => [
                'estimated' => $this->estimated_fare,
                'final' => $this->final_fare,
                'currency' => $this->currency,
                'breakdown' => $this->fare_breakdown,
            ],
            'driver' => $this->whenLoaded('driver', fn (): ?array => $this->driver === null ? null : [
                ...(new DriverResource($this->driver))->toArray($request),
                'pickup_eta_seconds' => $this->driverPickupEtaSeconds(),
            ]),
            'cancellation' => $this->status === RideStatus::Cancelled ? [
                'cancelled_by' => $this->cancelled_by,
                'reason' => $this->cancellation_reason,
            ] : null,
            'distance_to_pickup_meters' => $this->when(
                isset($this->resource->match_distance_meters),
                fn (): int => (int) round((float) $this->resource->match_distance_meters),
            ),
            'allowed_actions' => $this->allowedActions($request->user()),
            'requested_at' => $this->requested_at->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];
    }

    private function driverPickupEtaSeconds(): ?int
    {
        $driverCoordinates = $this->driver?->coordinates();

        if ($this->status !== RideStatus::Accepted || $driverCoordinates === null) {
            return null;
        }

        return app(RouteEstimator::class)->pickupEtaSeconds($driverCoordinates, $this->pickup()->coordinates);
    }

    /**
     * Actions the current user may take right now, so clients do not need to
     * duplicate the state machine or the authorization rules.
     *
     * @return list<string>
     */
    private function allowedActions(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $allowed = [];

        foreach (self::ACTIONS as $action => $targetStatus) {
            if ($this->status->canTransitionTo($targetStatus) && $user->can($action, $this->resource)) {
                $allowed[] = $action;
            }
        }

        return $allowed;
    }
}
