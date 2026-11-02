<?php

namespace App\Models;

use App\Enums\RideStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail of ride status changes (and driver assignments).
 *
 * @property int $id
 * @property string $ride_id
 * @property RideStatus|null $from_status
 * @property RideStatus $to_status
 * @property int|null $actor_id
 * @property int|null $driver_id
 * @property CarbonImmutable $created_at
 */
class RideEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => RideStatus::class,
            'to_status' => RideStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Ride, $this>
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }
}
