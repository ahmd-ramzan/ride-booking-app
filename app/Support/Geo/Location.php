<?php

namespace App\Support\Geo;

final readonly class Location
{
    public function __construct(
        public string $address,
        public Coordinates $coordinates,
    ) {}

    /**
     * @param  array{address: string, latitude: float|int|string, longitude: float|int|string}  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            $attributes['address'],
            new Coordinates((float) $attributes['latitude'], (float) $attributes['longitude']),
        );
    }
}
