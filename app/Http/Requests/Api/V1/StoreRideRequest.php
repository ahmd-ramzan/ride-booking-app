<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\RideCategory;
use App\Models\Ride;
use App\Support\Geo\Location;
use App\Support\Geo\ServiceArea;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRideRequest extends FormRequest
{
    public const int MINIMUM_TRIP_METERS = 200;

    public function authorize(): bool
    {
        Gate::authorize('create', Ride::class);

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(RideCategory::class)],
            'pickup' => ['required', 'array:address,latitude,longitude'],
            'pickup.address' => ['required', 'string', 'max:255'],
            'pickup.latitude' => ServiceArea::latitudeRules(),
            'pickup.longitude' => ServiceArea::longitudeRules(),
            'dropoff' => ['required', 'array:address,latitude,longitude'],
            'dropoff.address' => ['required', 'string', 'max:255'],
            'dropoff.latitude' => ServiceArea::latitudeRules(),
            'dropoff.longitude' => ServiceArea::longitudeRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.latitude.between' => 'The :attribute must be inside the '.config('rides.service_area.name').' service area.',
            '*.longitude.between' => 'The :attribute must be inside the '.config('rides.service_area.name').' service area.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pickup.address' => 'pickup address',
            'pickup.latitude' => 'pickup latitude',
            'pickup.longitude' => 'pickup longitude',
            'dropoff.address' => 'destination address',
            'dropoff.latitude' => 'destination latitude',
            'dropoff.longitude' => 'destination longitude',
            'dropoff' => 'destination',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->pickup()->coordinates->distanceTo($this->dropoff()->coordinates) < self::MINIMUM_TRIP_METERS) {
                    $validator->errors()->add('dropoff', 'The destination must be at least '.self::MINIMUM_TRIP_METERS.' m away from the pickup.');
                }
            },
        ];
    }

    public function category(): RideCategory
    {
        return RideCategory::from($this->string('category')->toString());
    }

    public function pickup(): Location
    {
        /** @var array{address: string, latitude: float|int|string, longitude: float|int|string} $pickup */
        $pickup = $this->validated('pickup');

        return Location::fromArray($pickup);
    }

    public function dropoff(): Location
    {
        /** @var array{address: string, latitude: float|int|string, longitude: float|int|string} $dropoff */
        $dropoff = $this->validated('dropoff');

        return Location::fromArray($dropoff);
    }
}
