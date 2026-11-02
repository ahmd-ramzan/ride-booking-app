<?php

use App\Http\Controllers\Api\V1\AcceptRideController;
use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\CancelRideController;
use App\Http\Controllers\Api\V1\CompleteRideController;
use App\Http\Controllers\Api\V1\Driver\DriverAvailabilityController;
use App\Http\Controllers\Api\V1\Driver\RideOfferController;
use App\Http\Controllers\Api\V1\FareEstimateController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PlaceController;
use App\Http\Controllers\Api\V1\RideController;
use App\Http\Controllers\Api\V1\StartRideController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/tokens', [AuthTokenController::class, 'store'])
        ->middleware('throttle:login')
        ->name('auth.tokens.store');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::delete('auth/tokens/current', [AuthTokenController::class, 'destroy'])->name('auth.tokens.destroy');
        Route::get('me', MeController::class)->name('me');

        Route::get('places', PlaceController::class)->name('places.index');
        Route::get('fare-estimates', FareEstimateController::class)->name('fare-estimates.show');

        Route::get('rides', [RideController::class, 'index'])->name('rides.index');
        Route::post('rides', [RideController::class, 'store'])
            ->middleware('idempotent:required')
            ->name('rides.store');
        Route::get('rides/{ride}', [RideController::class, 'show'])->name('rides.show');

        Route::middleware('idempotent:optional')->group(function () {
            Route::post('rides/{ride}/accept', AcceptRideController::class)->name('rides.accept');
            Route::post('rides/{ride}/start', StartRideController::class)->name('rides.start');
            Route::post('rides/{ride}/complete', CompleteRideController::class)->name('rides.complete');
            Route::post('rides/{ride}/cancel', CancelRideController::class)->name('rides.cancel');
        });

        Route::prefix('driver')->name('driver.')->group(function () {
            Route::put('availability', DriverAvailabilityController::class)->name('availability.update');
            Route::get('ride-offers', RideOfferController::class)->name('ride-offers.index');
        });
    });
});
