<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

/**
 * Lifecycle status stored on the parcel record itself.
 *
 * The finer grained warehouse events (Sorted, Dispatched, ...) live in
 * {@see TrackingStatus} and are recorded on the tracking timeline without
 * necessarily moving the parcel to a new lifecycle status.
 */
enum ParcelStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case PickedUp = 'picked_up';
    case AtWarehouse = 'at_warehouse';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case FailedDelivery = 'failed_delivery';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::PickedUp => 'Picked Up',
            self::AtWarehouse => 'At Warehouse',
            self::OutForDelivery => 'Out for Delivery',
            self::Delivered => 'Delivered',
            self::FailedDelivery => 'Failed Delivery',
            self::Returned => 'Returned',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::PickedUp => 'info',
            self::AtWarehouse => 'primary',
            self::OutForDelivery => 'warning',
            self::Delivered => 'success',
            self::FailedDelivery => 'danger',
            self::Returned => 'dark',
            self::Cancelled => 'light',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'bi-hourglass-split',
            self::PickedUp => 'bi-box-arrow-up',
            self::AtWarehouse => 'bi-building',
            self::OutForDelivery => 'bi-truck',
            self::Delivered => 'bi-check-circle-fill',
            self::FailedDelivery => 'bi-x-octagon-fill',
            self::Returned => 'bi-arrow-return-left',
            self::Cancelled => 'bi-slash-circle',
        };
    }

    /**
     * Statuses the parcel may legally move to next.
     *
     * Enforced by ParcelService so the timeline can never go backwards or skip
     * an impossible step (for example Pending straight to Delivered).
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::PickedUp, self::Cancelled],
            self::PickedUp => [self::AtWarehouse, self::OutForDelivery, self::Returned],
            self::AtWarehouse => [self::OutForDelivery, self::Returned],
            self::OutForDelivery => [self::Delivered, self::FailedDelivery, self::Returned],
            self::FailedDelivery => [self::OutForDelivery, self::AtWarehouse, self::Returned],
            self::Delivered, self::Returned, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), strict: true);
    }

    /**
     * A parcel in a terminal status can no longer be edited or reassigned.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::Returned, self::Cancelled], strict: true);
    }

    /**
     * Statuses that count as "still moving" for the pending deliveries widget.
     *
     * @return array<int, string>
     */
    public static function inTransitValues(): array
    {
        return [
            self::Pending->value,
            self::PickedUp->value,
            self::AtWarehouse->value,
            self::OutForDelivery->value,
        ];
    }

    /**
     * The matching tracking event to write when a parcel enters this status.
     */
    public function trackingStatus(): TrackingStatus
    {
        return match ($this) {
            self::Pending => TrackingStatus::Created,
            self::PickedUp => TrackingStatus::PickedUp,
            self::AtWarehouse => TrackingStatus::AtWarehouse,
            self::OutForDelivery => TrackingStatus::OutForDelivery,
            self::Delivered => TrackingStatus::Delivered,
            self::FailedDelivery => TrackingStatus::FailedDelivery,
            self::Returned => TrackingStatus::Returned,
            self::Cancelled => TrackingStatus::Cancelled,
        };
    }
}
