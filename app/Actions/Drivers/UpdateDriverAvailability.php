<?php

namespace App\Actions\Drivers;

use App\Enums\DriverStatus;
use App\Exceptions\DriverOnTripException;
use App\Models\Driver;
use App\Support\Geo\Coordinates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateDriverAvailability
{
    /**
     * Toggles a driver between `available` and `offline`. The `on_trip` status is
     * owned by the ride actions and cannot be changed here.
     *
     * @throws DriverOnTripException
     */
    public function handle(Driver $driver, DriverStatus $status, ?Coordinates $location): Driver
    {
        return DB::transaction(function () use ($driver, $status, $location): Driver {
            $driver = Driver::query()->lockForUpdate()->findOrFail($driver->id);

            if ($driver->status === DriverStatus::OnTrip) {
                throw new DriverOnTripException;
            }

            $driver->status = $status;

            if ($location !== null) {
                $driver->moveTo($location);
            }

            $driver->save();

            Log::info('driver.availability_changed', ['driver_id' => $driver->id, 'status' => $status->value]);

            return $driver;
        });
    }
}
