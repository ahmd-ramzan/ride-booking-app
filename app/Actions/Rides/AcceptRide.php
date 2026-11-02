<?php

namespace App\Actions\Rides;

use App\Enums\DriverStatus;
use App\Enums\RideStatus;
use App\Exceptions\DriverNotEligibleException;
use App\Exceptions\InvalidRideTransitionException;
use App\Exceptions\RideAlreadyAssignedException;
use App\Models\Driver;
use App\Models\Ride;
use App\Services\DriverMatcher;
use App\Services\RideLifecycle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class AcceptRide
{
    public function __construct(
        private DriverMatcher $matcher,
        private RideLifecycle $lifecycle,
    ) {}

    /**
     * Assigns the driver to the ride.
     *
     * Concurrency: the ride row is locked first, then the driver row (the same
     * order used by every ride action, so no deadlocks). The first transaction
     * to lock the ride wins; every other driver blocks, then sees the ride is
     * no longer `requested` and gets RideAlreadyAssigned. Locking the driver row
     * stops one driver from accepting two rides at once, and the partial unique
     * index `rides_one_active_per_driver` is the database-level backstop.
     *
     * Retrying an accept that already succeeded for the same driver returns the
     * ride unchanged, so client retries are safe.
     *
     * @throws RideAlreadyAssignedException
     * @throws InvalidRideTransitionException
     * @throws DriverNotEligibleException
     */
    public function handle(Ride $ride, Driver $driver): Ride
    {
        try {
            return DB::transaction(function () use ($ride, $driver): Ride {
                $ride = Ride::query()->lockForUpdate()->findOrFail($ride->id);

                if ($ride->status !== RideStatus::Requested) {
                    if ($ride->driver_id !== null && $ride->isAssignedTo($driver) && $ride->status === RideStatus::Accepted) {
                        return $ride;
                    }

                    if ($ride->driver_id !== null && ! $ride->isAssignedTo($driver)) {
                        throw new RideAlreadyAssignedException;
                    }

                    throw new InvalidRideTransitionException($ride->status, RideStatus::Accepted);
                }

                $driver = Driver::query()->with('user')->lockForUpdate()->findOrFail($driver->id);

                $this->matcher->ensureEligible($driver, $ride);

                $driver->status = DriverStatus::OnTrip;
                $driver->save();

                return $this->lifecycle->transition($ride, RideStatus::Accepted, $driver->user, [
                    'driver_id' => $driver->id,
                    'accepted_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new DriverNotEligibleException(DriverNotEligibleException::NOT_AVAILABLE);
        }
    }
}
