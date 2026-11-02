<?php

namespace App\Enums;

/**
 * The ride lifecycle state machine.
 *
 *   requested ──► accepted ──► in_progress ──► completed
 *       │             │
 *       └──► cancelled ◄┘
 */
enum RideStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Requested => [self::Accepted, self::Cancelled],
            self::Accepted => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Statuses during which a ride still occupies its rider (and driver, once assigned).
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Requested, self::Accepted, self::InProgress];
    }
}
