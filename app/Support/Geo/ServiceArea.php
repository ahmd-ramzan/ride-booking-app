<?php

namespace App\Support\Geo;

/**
 * The geographic bounding box the platform operates in (Lahore by default).
 */
final class ServiceArea
{
    /**
     * @return list<string>
     */
    public static function latitudeRules(): array
    {
        return [
            'required',
            'numeric',
            'between:'.config('rides.service_area.min_latitude').','.config('rides.service_area.max_latitude'),
        ];
    }

    /**
     * @return list<string>
     */
    public static function longitudeRules(): array
    {
        return [
            'required',
            'numeric',
            'between:'.config('rides.service_area.min_longitude').','.config('rides.service_area.max_longitude'),
        ];
    }
}
