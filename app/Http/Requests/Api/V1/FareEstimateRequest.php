<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Geo\Coordinates;
use App\Support\Geo\ServiceArea;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FareEstimateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pickup_latitude' => ServiceArea::latitudeRules(),
            'pickup_longitude' => ServiceArea::longitudeRules(),
            'dropoff_latitude' => ServiceArea::latitudeRules(),
            'dropoff_longitude' => ServiceArea::longitudeRules(),
        ];
    }

    public function pickup(): Coordinates
    {
        return new Coordinates($this->float('pickup_latitude'), $this->float('pickup_longitude'));
    }

    public function dropoff(): Coordinates
    {
        return new Coordinates($this->float('dropoff_latitude'), $this->float('dropoff_longitude'));
    }
}
