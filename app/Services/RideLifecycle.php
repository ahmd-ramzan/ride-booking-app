<?php

namespace App\Services;

use App\Enums\RideStatus;
use App\Exceptions\InvalidRideTransitionException;
use App\Models\Ride;
use App\Models\RideEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * The single place where a ride's status is written. Every transition is
 * validated against the RideStatus state machine, recorded in the
 * ride_events audit trail and logged.
 *
 * Callers must hold a row lock on the ride (SELECT ... FOR UPDATE) inside a
 * transaction so the checked status cannot change before it is written.
 */
class RideLifecycle
{
    /**
     * @throws InvalidRideTransitionException
     */
    public function ensureCanTransition(Ride $ride, RideStatus $to): void
    {
        if (! $ride->status->canTransitionTo($to)) {
            throw new InvalidRideTransitionException($ride->status, $to);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes  Extra ride attributes written with the status change.
     *
     * @throws InvalidRideTransitionException
     */
    public function transition(Ride $ride, RideStatus $to, ?User $actor, array $attributes = []): Ride
    {
        $this->ensureCanTransition($ride, $to);

        $from = $ride->status;

        $ride->forceFill([...$attributes, 'status' => $to])->save();

        $this->record($ride, $from, $actor);

        return $ride;
    }

    public function record(Ride $ride, ?RideStatus $from, ?User $actor): void
    {
        $event = new RideEvent;
        $event->forceFill([
            'ride_id' => $ride->id,
            'from_status' => $from,
            'to_status' => $ride->status,
            'actor_id' => $actor?->id,
            'driver_id' => $ride->driver_id,
        ])->save();

        Log::info('ride.status_changed', [
            'ride_id' => $ride->id,
            'from' => $from?->value,
            'to' => $ride->status->value,
            'actor_id' => $actor?->id,
            'driver_id' => $ride->driver_id,
        ]);
    }
}
