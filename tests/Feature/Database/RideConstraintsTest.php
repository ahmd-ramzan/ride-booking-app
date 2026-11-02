<?php

use App\Enums\RideStatus;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/*
 * These invariants are enforced by PostgreSQL itself, independently of the
 * application code, as the last line of defence against races and bugs.
 */

it('rejects a second active ride for the same rider', function () {
    $rider = User::factory()->rider()->create();
    Ride::factory()->for($rider, 'rider')->create();

    expect(fn () => DB::transaction(fn () => Ride::factory()->for($rider, 'rider')->create()))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('rejects assigning a driver to two active rides', function () {
    $driver = Driver::factory()->create();
    Ride::factory()->acceptedBy($driver)->create();

    expect(fn () => DB::transaction(fn () => Ride::factory()->acceptedBy($driver)->create()))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('rejects an accepted ride without a driver', function () {
    expect(fn () => DB::transaction(fn () => Ride::factory()->create(['status' => RideStatus::Accepted])))
        ->toThrow(QueryException::class, 'rides_assigned_driver_check');
});

it('rejects an unknown ride status', function () {
    $ride = Ride::factory()->create();

    expect(fn () => DB::transaction(fn () => DB::table('rides')->where('id', $ride->id)->update(['status' => 'teleported'])))
        ->toThrow(QueryException::class, 'rides_status_check');
});
