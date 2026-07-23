<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

enum VehicleType: string
{
    use HasEnumHelpers;

    case Motorbike = 'motorbike';
    case ThreeWheeler = 'three_wheeler';
    case Car = 'car';
    case Van = 'van';
    case Lorry = 'lorry';
    case Truck = 'truck';

    public function label(): string
    {
        return match ($this) {
            self::Motorbike => 'Motorbike',
            self::ThreeWheeler => 'Three Wheeler',
            self::Car => 'Car',
            self::Van => 'Van',
            self::Lorry => 'Lorry',
            self::Truck => 'Truck',
        };
    }

    public function color(): string
    {
        return 'secondary';
    }

    /**
     * Maximum payload in kilograms, used to warn dispatchers when a parcel is
     * assigned to a driver whose vehicle cannot carry it.
     */
    public function capacityKg(): float
    {
        return match ($this) {
            self::Motorbike => 20.0,
            self::ThreeWheeler => 300.0,
            self::Car => 400.0,
            self::Van => 1000.0,
            self::Lorry => 3000.0,
            self::Truck => 10000.0,
        };
    }
}
