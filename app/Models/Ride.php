<?php

namespace App\Models;

use App\Enums\RideCategory;
use App\Enums\RideStatus;
use App\Support\Geo\Coordinates;
use App\Support\Geo\Location;
use Carbon\CarbonImmutable;
use Database\Factories\RideFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ride state is only changed through the actions in App\Actions\Rides, which
 * lock the row and enforce the RideStatus state machine. Mass assignment is
 * therefore disabled; attributes are set explicitly.
 *
 * @property string $id
 * @property int $rider_id
 * @property int|null $driver_id
 * @property RideCategory $category
 * @property RideStatus $status
 * @property string $pickup_address
 * @property float $pickup_latitude
 * @property float $pickup_longitude
 * @property string $dropoff_address
 * @property float $dropoff_latitude
 * @property float $dropoff_longitude
 * @property int $distance_meters
 * @property int $duration_seconds
 * @property string $currency
 * @property int $estimated_fare
 * @property int|null $final_fare
 * @property array{base: int, distance: int, time: int, minimum_fare_applied: bool} $fare_breakdown
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $rider
 * @property-read Driver|null $driver
 */
class Ride extends Model
{
    /** @use HasFactory<RideFactory> */
    use HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'requested',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => RideCategory::class,
            'status' => RideStatus::class,
            'pickup_latitude' => 'float',
            'pickup_longitude' => 'float',
            'dropoff_latitude' => 'float',
            'dropoff_longitude' => 'float',
            'distance_meters' => 'integer',
            'duration_seconds' => 'integer',
            'estimated_fare' => 'integer',
            'final_fare' => 'integer',
            'fare_breakdown' => 'array',
            'requested_at' => 'datetime',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return HasMany<RideEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(RideEvent::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', RideStatus::active());
    }

    public function pickup(): Location
    {
        return new Location($this->pickup_address, new Coordinates($this->pickup_latitude, $this->pickup_longitude));
    }

    public function dropoff(): Location
    {
        return new Location($this->dropoff_address, new Coordinates($this->dropoff_latitude, $this->dropoff_longitude));
    }

    public function isAssignedTo(Driver $driver): bool
    {
        return $this->driver_id === $driver->id;
    }
}
