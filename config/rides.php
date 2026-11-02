<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Service area
    |--------------------------------------------------------------------------
    |
    | Bounding box for Lahore. Pickup and destination coordinates outside of it
    | are rejected by validation.
    |
    */

    'service_area' => [
        'name' => 'Lahore',
        'min_latitude' => 31.20,
        'max_latitude' => 31.75,
        'min_longitude' => 74.05,
        'max_longitude' => 74.60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Route estimation
    |--------------------------------------------------------------------------
    |
    | Without a routing provider we approximate road distance as the straight
    | line distance multiplied by a detour factor, and travel time from an
    | average urban speed.
    |
    */

    'routing' => [
        'road_distance_factor' => (float) env('RIDES_ROAD_DISTANCE_FACTOR', 1.3),
        'average_speed_kmh' => (float) env('RIDES_AVERAGE_SPEED_KMH', 22),
        'minimum_eta_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Driver matching
    |--------------------------------------------------------------------------
    */

    'matching' => [
        'max_pickup_distance_km' => (float) env('RIDES_MAX_PICKUP_DISTANCE_KM', 8),
        'offers_limit' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fares (PKR, whole rupees)
    |--------------------------------------------------------------------------
    */

    'currency' => 'PKR',

    'fares' => [
        'economy' => [
            'base' => 120,
            'per_km' => 38,
            'per_minute' => 5,
            'minimum' => 200,
        ],
        'comfort' => [
            'base' => 200,
            'per_km' => 60,
            'per_minute' => 8,
            'minimum' => 350,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    |
    | Stored responses are replayed for retries that reuse an Idempotency-Key.
    | A key left "in progress" past the lock timeout (for example because the
    | worker crashed) may be reclaimed by the next retry.
    |
    */

    'idempotency' => [
        'retention_hours' => 24,
        'lock_timeout_seconds' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Known places
    |--------------------------------------------------------------------------
    |
    | A small gazetteer used in place of a geocoding provider.
    |
    */

    'places' => [
        ['name' => 'Liberty Market, Gulberg III', 'latitude' => 31.5102, 'longitude' => 74.3441],
        ['name' => 'MM Alam Road, Gulberg', 'latitude' => 31.5175, 'longitude' => 74.3520],
        ['name' => 'Gaddafi Stadium', 'latitude' => 31.5134, 'longitude' => 74.3335],
        ['name' => 'Packages Mall, Walton Road', 'latitude' => 31.4711, 'longitude' => 74.3555],
        ['name' => 'Emporium Mall, Johar Town', 'latitude' => 31.4673, 'longitude' => 74.2660],
        ['name' => 'Model Town Park', 'latitude' => 31.4826, 'longitude' => 74.3255],
        ['name' => 'Y Block Market, DHA Phase 3', 'latitude' => 31.4697, 'longitude' => 74.3790],
        ['name' => 'LUMS, DHA', 'latitude' => 31.4706, 'longitude' => 74.4111],
        ['name' => 'Fortress Stadium, Cantt', 'latitude' => 31.5317, 'longitude' => 74.3626],
        ['name' => 'Allama Iqbal International Airport', 'latitude' => 31.5216, 'longitude' => 74.4036],
        ['name' => 'Lahore Railway Station', 'latitude' => 31.5776, 'longitude' => 74.3365],
        ['name' => 'Anarkali Bazaar', 'latitude' => 31.5690, 'longitude' => 74.3090],
        ['name' => 'Badshahi Mosque', 'latitude' => 31.5880, 'longitude' => 74.3107],
        ['name' => 'Minar-e-Pakistan', 'latitude' => 31.5925, 'longitude' => 74.3095],
        ['name' => 'Bahria Town, Lahore', 'latitude' => 31.3680, 'longitude' => 74.1850],
    ],

];
