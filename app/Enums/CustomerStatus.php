<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

enum CustomerStatus: string
{
    use HasEnumHelpers;

    case Active = 'active';
    case Inactive = 'inactive';
    case Blacklisted = 'blacklisted';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Blacklisted => 'Blacklisted',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'secondary',
            self::Blacklisted => 'danger',
        };
    }

    /**
     * Inactive and blacklisted customers cannot have new parcels booked.
     */
    public function canBookParcels(): bool
    {
        return $this === self::Active;
    }
}
