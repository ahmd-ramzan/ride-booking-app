<?php

namespace App\Policies;

use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Who may do what with a ride. A ride a user has no relationship with answers
 * 404 (not 403) so ride IDs cannot be probed.
 *
 * Policies decide *who*; whether the ride's current status allows the action
 * is decided by the RideStatus state machine (409 on violation).
 */
class RidePolicy
{
    public function view(User $user, Ride $ride): Response
    {
        if ($this->isRiderOf($user, $ride) || $this->isAssignedDriverOf($user, $ride)) {
            return Response::allow();
        }

        if ($user->isDriver() && $ride->status === RideStatus::Requested) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    public function create(User $user): Response
    {
        return $user->isRider()
            ? Response::allow()
            : Response::deny('Only riders can request rides.');
    }

    public function viewOffers(User $user): Response
    {
        return $user->isDriver() && $user->driver !== null
            ? Response::allow()
            : Response::deny('Only drivers can view ride offers.');
    }

    public function accept(User $user, Ride $ride): Response
    {
        if (! $user->isDriver() || $user->driver === null) {
            return $this->isRiderOf($user, $ride)
                ? Response::deny('Only drivers can accept rides.')
                : Response::denyAsNotFound();
        }

        return Response::allow();
    }

    public function start(User $user, Ride $ride): Response
    {
        return $this->assignedDriverOnly($user, $ride, 'Only the assigned driver can start this ride.');
    }

    public function complete(User $user, Ride $ride): Response
    {
        return $this->assignedDriverOnly($user, $ride, 'Only the assigned driver can complete this ride.');
    }

    public function cancel(User $user, Ride $ride): Response
    {
        if ($this->isRiderOf($user, $ride)) {
            return Response::allow();
        }

        return $this->isAssignedDriverOf($user, $ride)
            ? Response::deny('Only the rider can cancel this ride.')
            : Response::denyAsNotFound();
    }

    private function assignedDriverOnly(User $user, Ride $ride, string $message): Response
    {
        if ($this->isAssignedDriverOf($user, $ride)) {
            return Response::allow();
        }

        return $this->view($user, $ride)->allowed()
            ? Response::deny($message)
            : Response::denyAsNotFound();
    }

    private function isRiderOf(User $user, Ride $ride): bool
    {
        return $user->isRider() && $ride->rider_id === $user->id;
    }

    private function isAssignedDriverOf(User $user, Ride $ride): bool
    {
        return $user->isDriver()
            && $user->driver !== null
            && $ride->driver_id === $user->driver->id;
    }
}
