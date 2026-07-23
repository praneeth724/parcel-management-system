<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

enum ParcelType: string
{
    use HasEnumHelpers;

    case Document = 'document';
    case Package = 'package';
    case Fragile = 'fragile';
    case Electronics = 'electronics';
    case Perishable = 'perishable';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Document => 'Document',
            self::Package => 'Package',
            self::Fragile => 'Fragile',
            self::Electronics => 'Electronics',
            self::Perishable => 'Perishable',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Document => 'info',
            self::Package => 'primary',
            self::Fragile => 'danger',
            self::Electronics => 'warning',
            self::Perishable => 'success',
            self::Other => 'secondary',
        };
    }

    /**
     * Handling surcharge in LKR added to the calculated delivery charge.
     */
    public function handlingFee(): float
    {
        return match ($this) {
            self::Document => 0.0,
            self::Package => 0.0,
            self::Fragile => 250.0,
            self::Electronics => 200.0,
            self::Perishable => 350.0,
            self::Other => 0.0,
        };
    }
}
