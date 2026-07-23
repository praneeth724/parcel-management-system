<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

enum DriverStatus: string
{
    use HasEnumHelpers;

    case Available = 'available';
    case OnDelivery = 'on_delivery';
    case OffDuty = 'off_duty';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::OnDelivery => 'On Delivery',
            self::OffDuty => 'Off Duty',
            self::Inactive => 'Inactive',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::OnDelivery => 'warning',
            self::OffDuty => 'secondary',
            self::Inactive => 'danger',
        };
    }

    /**
     * Only available drivers may be handed a new parcel.
     */
    public function canAcceptAssignments(): bool
    {
        return $this === self::Available;
    }
}
