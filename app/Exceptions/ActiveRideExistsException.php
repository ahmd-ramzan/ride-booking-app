<?php

namespace App\Exceptions;

class ActiveRideExistsException extends ApiException
{
    public function __construct(public readonly ?string $activeRideId = null)
    {
        parent::__construct('You already have an active ride. Cancel or finish it before requesting another.');
    }

    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'active_ride_exists';
    }

    public function details(): array
    {
        return $this->activeRideId === null ? [] : ['active_ride_id' => $this->activeRideId];
    }
}
