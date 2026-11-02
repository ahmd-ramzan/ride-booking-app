<?php

namespace App\Exceptions;

use App\Enums\RideStatus;

class InvalidRideTransitionException extends ApiException
{
    public function __construct(
        public readonly RideStatus $from,
        public readonly RideStatus $to,
    ) {
        parent::__construct("A ride that is {$from->value} cannot be moved to {$to->value}.");
    }

    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'invalid_ride_transition';
    }

    public function details(): array
    {
        return ['current_status' => $this->from->value, 'requested_status' => $this->to->value];
    }
}
