<?php

namespace App\Support\Geo;

final readonly class Coordinates
{
    private const int EARTH_RADIUS_METERS = 6_371_000;

    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {}

    /**
     * Great-circle (haversine) distance in meters.
     */
    public function distanceTo(self $other): float
    {
        $latitudeDelta = deg2rad($other->latitude - $this->latitude);
        $longitudeDelta = deg2rad($other->longitude - $this->longitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($this->latitude)) * cos(deg2rad($other->latitude)) * sin($longitudeDelta / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * asin(min(1.0, sqrt($a)));
    }

    /**
     * @return array{latitude: float, longitude: float}
     */
    public function toArray(): array
    {
        return ['latitude' => $this->latitude, 'longitude' => $this->longitude];
    }
}
