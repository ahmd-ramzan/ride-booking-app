<?php

use App\Support\Geo\Coordinates;

it('computes the great-circle distance between two Lahore landmarks', function () {
    $libertyMarket = new Coordinates(31.5102, 74.3441);
    $packagesMall = new Coordinates(31.4711, 74.3555);

    expect($libertyMarket->distanceTo($packagesMall))->toEqualWithDelta(4_480, 20);
});

it('returns zero for identical points', function () {
    $point = new Coordinates(31.5, 74.3);

    expect($point->distanceTo($point))->toBe(0.0);
});
