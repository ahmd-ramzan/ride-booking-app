<?php

namespace App\Support\Pricing;

final readonly class RouteEstimate
{
    public function __construct(
        public int $distanceMeters,
        public int $durationSeconds,
    ) {}
}
