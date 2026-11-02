<?php

namespace App\Models;

use App\Enums\DriverStatus;
use App\Enums\RideCategory;
use App\Support\Geo\Coordinates;
use Carbon\CarbonImmutable;
use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property RideCategory $vehicle_category
 * @property string $vehicle_model
 * @property string $vehicle_plate
 * @property DriverStatus $status
 * @property float|null $latitude
 * @property float|null $longitude
 * @property CarbonImmutable|null $location_updated_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Fillable(['vehicle_category', 'vehicle_model', 'vehicle_plate'])]
class Driver extends Model
{
    /** @use HasFactory<DriverFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'offline',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vehicle_category' => RideCategory::class,
            'status' => DriverStatus::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'location_updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Ride, $this>
     */
    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }

    public function coordinates(): ?Coordinates
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return new Coordinates($this->latitude, $this->longitude);
    }

    public function moveTo(Coordinates $coordinates): void
    {
        $this->latitude = $coordinates->latitude;
        $this->longitude = $coordinates->longitude;
        $this->location_updated_at = now();
    }
}
