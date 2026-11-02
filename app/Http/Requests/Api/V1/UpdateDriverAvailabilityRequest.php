<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\DriverStatus;
use App\Support\Geo\Coordinates;
use App\Support\Geo\ServiceArea;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDriver() === true && $this->user()->driver !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $requiredWhenGoingOnline = Rule::requiredIf(fn (): bool => $this->input('status') === DriverStatus::Available->value);

        return [
            'status' => ['required', Rule::enum(DriverStatus::class)->only([DriverStatus::Available, DriverStatus::Offline])],
            'latitude' => [$requiredWhenGoingOnline, 'nullable', ...array_slice(ServiceArea::latitudeRules(), 1)],
            'longitude' => [$requiredWhenGoingOnline, 'nullable', ...array_slice(ServiceArea::longitudeRules(), 1)],
        ];
    }

    public function status(): DriverStatus
    {
        return DriverStatus::from($this->string('status')->toString());
    }

    public function location(): ?Coordinates
    {
        if ($this->input('latitude') === null || $this->input('longitude') === null) {
            return null;
        }

        return new Coordinates($this->float('latitude'), $this->float('longitude'));
    }
}
