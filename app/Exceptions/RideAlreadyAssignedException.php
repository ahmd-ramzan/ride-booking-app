<?php

namespace App\Exceptions;

class RideAlreadyAssignedException extends ApiException
{
    public function __construct()
    {
        parent::__construct('This ride has already been accepted by another driver.');
    }

    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'ride_already_assigned';
    }
}
