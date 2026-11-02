<?php

use App\Enums\RideStatus;

test('the ride state machine allows exactly these transitions', function (RideStatus $from, array $allowed) {
    foreach (RideStatus::cases() as $to) {
        expect($from->canTransitionTo($to))->toBe(in_array($to, $allowed, true), "{$from->value} -> {$to->value}");
    }
})->with([
    'requested' => [RideStatus::Requested, [RideStatus::Accepted, RideStatus::Cancelled]],
    'accepted' => [RideStatus::Accepted, [RideStatus::InProgress, RideStatus::Cancelled]],
    'in progress' => [RideStatus::InProgress, [RideStatus::Completed]],
    'completed' => [RideStatus::Completed, []],
    'cancelled' => [RideStatus::Cancelled, []],
]);

test('only requested, accepted and in-progress rides are active', function () {
    expect(RideStatus::active())->toBe([RideStatus::Requested, RideStatus::Accepted, RideStatus::InProgress])
        ->and(RideStatus::Completed->isActive())->toBeFalse()
        ->and(RideStatus::Cancelled->isActive())->toBeFalse();
});
