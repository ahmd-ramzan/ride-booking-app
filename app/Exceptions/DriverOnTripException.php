<?php

namespace App\Exceptions;

class DriverOnTripException extends ApiException
{
    public function __construct()
    {
        parent::__construct('You cannot change availability while on a trip.');
    }

    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'driver_on_trip';
    }
}
