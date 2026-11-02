<?php

namespace App\Support\Pricing;

use App\Enums\RideCategory;

/**
 * An upfront fare in whole Pakistani rupees.
 */
final readonly class FareQuote
{
    /**
     * @param  array{base: int, distance: int, time: int, minimum_fare_applied: bool}  $breakdown
     */
    public function __construct(
        public RideCategory $category,
        public int $amount,
        public string $currency,
        public array $breakdown,
    ) {}
}
