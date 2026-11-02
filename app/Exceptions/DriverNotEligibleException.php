<?php

namespace App\Exceptions;

class DriverNotEligibleException extends ApiException
{
    public const string NOT_AVAILABLE = 'driver_not_available';

    public const string CATEGORY_MISMATCH = 'vehicle_category_mismatch';

    public const string TOO_FAR = 'too_far_from_pickup';

    public const string LOCATION_UNKNOWN = 'driver_location_unknown';

    private const array MESSAGES = [
        self::NOT_AVAILABLE => 'You must be available (online and not on a trip) to accept a ride.',
        self::CATEGORY_MISMATCH => 'Your vehicle category does not match the requested ride category.',
        self::TOO_FAR => 'You are too far from the pickup location to accept this ride.',
        self::LOCATION_UNKNOWN => 'Share your location before accepting rides.',
    ];

    public function __construct(public readonly string $reason)
    {
        parent::__construct(self::MESSAGES[$reason] ?? 'You are not eligible to accept this ride.');
    }

    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'driver_not_eligible';
    }

    public function details(): array
    {
        return ['reason' => $this->reason];
    }
}
