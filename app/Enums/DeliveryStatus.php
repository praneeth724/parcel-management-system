<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

/**
 * State of a single driver assignment.
 *
 * A parcel can accumulate several delivery rows over its life: a rejected
 * assignment or a failed attempt is kept for the audit trail and a fresh row is
 * created when the parcel is reassigned.
 */
enum DeliveryStatus: string
{
    use HasEnumHelpers;

    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case InTransit = 'in_transit';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'Awaiting Driver',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::InTransit => 'In Transit',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Assigned => 'secondary',
            self::Accepted => 'info',
            self::Rejected => 'danger',
            self::InTransit => 'warning',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Cancelled => 'light',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Assigned => 'bi-hourglass',
            self::Accepted => 'bi-hand-thumbs-up',
            self::Rejected => 'bi-hand-thumbs-down',
            self::InTransit => 'bi-truck',
            self::Completed => 'bi-check-circle-fill',
            self::Failed => 'bi-x-octagon',
            self::Cancelled => 'bi-slash-circle',
        };
    }

    /**
     * Assignment is still the driver's responsibility.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Assigned, self::Accepted, self::InTransit], strict: true);
    }

    /**
     * Statuses that occupy a driver, used by the "Drivers on Delivery" widget.
     *
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return [
            self::Assigned->value,
            self::Accepted->value,
            self::InTransit->value,
        ];
    }
}
