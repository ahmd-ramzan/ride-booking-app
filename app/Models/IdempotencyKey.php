<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $key
 * @property string $request_fingerprint
 * @property int|null $response_status
 * @property array<string, mixed>|null $response_body
 * @property CarbonImmutable|null $locked_until
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class IdempotencyKey extends Model
{
    use MassPrunable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'response_body' => 'array',
            'locked_until' => 'datetime',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->response_status !== null;
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subHours((int) config('rides.idempotency.retention_hours')));
    }
}
