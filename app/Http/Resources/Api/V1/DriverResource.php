<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Driver
 */
class DriverResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->user->name,
            'phone' => $this->user->phone,
            'status' => $this->status->value,
            'vehicle' => [
                'category' => $this->vehicle_category->value,
                'model' => $this->vehicle_model,
                'plate' => $this->vehicle_plate,
            ],
            'location' => $this->latitude === null ? null : [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'updated_at' => $this->location_updated_at?->toIso8601String(),
            ],
        ];
    }
}
