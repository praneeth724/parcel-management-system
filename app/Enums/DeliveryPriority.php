<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;
use Carbon\CarbonInterface;

enum DeliveryPriority: string
{
    use HasEnumHelpers;

    case Normal = 'normal';
    case Express = 'express';
    case SameDay = 'same_day';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Express => 'Express',
            self::SameDay => 'Same Day',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Normal => 'secondary',
            self::Express => 'warning',
            self::SameDay => 'danger',
        };
    }

    /**
     * Surcharge multiplier applied on top of the base weight-and-distance rate.
     */
    public function chargeMultiplier(): float
    {
        return match ($this) {
            self::Normal => 1.0,
            self::Express => 1.5,
            self::SameDay => 2.25,
        };
    }

    /**
     * Working days allowed before the parcel is considered late.
     */
    public function slaDays(): int
    {
        return match ($this) {
            self::Normal => 3,
            self::Express => 1,
            self::SameDay => 0,
        };
    }

    public function expectedDeliveryFrom(CarbonInterface $createdAt): CarbonInterface
    {
        return $this === self::SameDay
            ? $createdAt->copy()->endOfDay()
            : $createdAt->copy()->addWeekdays($this->slaDays())->endOfDay();
    }
}
