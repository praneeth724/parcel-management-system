<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

enum PaymentMethod: string
{
    use HasEnumHelpers;

    case CashOnDelivery = 'cash_on_delivery';
    case Prepaid = 'prepaid';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'Cash on Delivery',
            self::Prepaid => 'Prepaid (Cash)',
            self::Card => 'Credit / Debit Card',
            self::BankTransfer => 'Bank Transfer',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'warning',
            self::Prepaid => 'success',
            self::Card => 'info',
            self::BankTransfer => 'primary',
        };
    }

    /**
     * Cash on delivery is the only method collected by the driver at the door;
     * everything else is settled before the parcel moves.
     */
    public function isCollectedOnDelivery(): bool
    {
        return $this === self::CashOnDelivery;
    }
}
