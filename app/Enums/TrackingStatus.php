<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

/**
 * Events written to the parcel tracking timeline.
 *
 * This is a superset of {@see ParcelStatus}: it adds the intermediate warehouse
 * milestones from the specification's example flow (Sorted, Dispatched) plus a
 * generic note event, none of which change the parcel's lifecycle status.
 */
enum TrackingStatus: string
{
    use HasEnumHelpers;

    case Created = 'created';
    case PickedUp = 'picked_up';
    case AtWarehouse = 'at_warehouse';
    case Sorted = 'sorted';
    case Dispatched = 'dispatched';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case FailedDelivery = 'failed_delivery';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
    case AssignedToDriver = 'assigned_to_driver';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Package Created',
            self::PickedUp => 'Picked Up',
            self::AtWarehouse => 'Received at Warehouse',
            self::Sorted => 'Sorted',
            self::Dispatched => 'Dispatched',
            self::OutForDelivery => 'Out for Delivery',
            self::Delivered => 'Delivered',
            self::FailedDelivery => 'Failed Delivery',
            self::Returned => 'Returned to Sender',
            self::Cancelled => 'Cancelled',
            self::AssignedToDriver => 'Assigned to Driver',
            self::Note => 'Update',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Created => 'secondary',
            self::PickedUp, self::Sorted => 'info',
            self::AtWarehouse, self::Dispatched => 'primary',
            self::OutForDelivery, self::AssignedToDriver => 'warning',
            self::Delivered => 'success',
            self::FailedDelivery => 'danger',
            self::Returned => 'dark',
            self::Cancelled => 'light',
            self::Note => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Created => 'bi-plus-circle',
            self::PickedUp => 'bi-box-arrow-up',
            self::AtWarehouse => 'bi-building-check',
            self::Sorted => 'bi-diagram-3',
            self::Dispatched => 'bi-send',
            self::OutForDelivery => 'bi-truck',
            self::Delivered => 'bi-check-circle-fill',
            self::FailedDelivery => 'bi-x-octagon-fill',
            self::Returned => 'bi-arrow-return-left',
            self::Cancelled => 'bi-slash-circle',
            self::AssignedToDriver => 'bi-person-badge',
            self::Note => 'bi-chat-left-text',
        };
    }

    /**
     * Events a warehouse operator may log manually, in the order they occur.
     *
     * @return array<string, string>
     */
    public static function manualOptions(): array
    {
        $manual = [
            self::PickedUp,
            self::AtWarehouse,
            self::Sorted,
            self::Dispatched,
            self::OutForDelivery,
            self::Delivered,
            self::FailedDelivery,
            self::Returned,
            self::Note,
        ];

        $options = [];

        foreach ($manual as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * The lifecycle status this event moves the parcel to, if any.
     */
    public function parcelStatus(): ?ParcelStatus
    {
        return match ($this) {
            self::Created => ParcelStatus::Pending,
            self::PickedUp => ParcelStatus::PickedUp,
            self::AtWarehouse => ParcelStatus::AtWarehouse,
            self::OutForDelivery => ParcelStatus::OutForDelivery,
            self::Delivered => ParcelStatus::Delivered,
            self::FailedDelivery => ParcelStatus::FailedDelivery,
            self::Returned => ParcelStatus::Returned,
            self::Cancelled => ParcelStatus::Cancelled,
            // Sorted / Dispatched / Assigned / Note are timeline-only events.
            self::Sorted, self::Dispatched, self::AssignedToDriver, self::Note => null,
        };
    }
}
