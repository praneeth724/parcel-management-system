<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryPriority;
use App\Enums\DeliveryStatus;
use App\Enums\ParcelStatus;
use App\Enums\ParcelType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\ParcelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A shipment booked by a customer.
 *
 * The tracking number is generated here on create; every status change is
 * recorded on the {@see ParcelTracking} timeline by
 * {@see \App\Services\ParcelService}.
 *
 * @property int $id
 * @property string $tracking_number
 * @property ParcelStatus $status
 * @property DeliveryPriority $priority
 */
class Parcel extends Model
{
    /** @use HasFactory<ParcelFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tracking_number',
        'customer_id',
        'branch_id',
        'receiver_name',
        'receiver_phone',
        'receiver_address',
        'receiver_city',
        'receiver_postal_code',
        'pickup_address',
        'parcel_type',
        'weight',
        'length_cm',
        'width_cm',
        'height_cm',
        'delivery_charge',
        'cod_amount',
        'payment_method',
        'payment_status',
        'priority',
        'status',
        'qr_path',
        'special_instructions',
        'expected_delivery_at',
        'picked_up_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
        'delivery_attempts',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parcel_type' => ParcelType::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'priority' => DeliveryPriority::class,
            'status' => ParcelStatus::class,
            'weight' => 'decimal:3',
            'length_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'delivery_charge' => 'decimal:2',
            'cod_amount' => 'decimal:2',
            'expected_delivery_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'delivery_attempts' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $parcel): void {
            $parcel->tracking_number ??= self::generateTrackingNumber();

            if ($parcel->expected_delivery_at === null) {
                $priority = $parcel->priority ?? DeliveryPriority::Normal;
                $parcel->expected_delivery_at = $priority->expectedDeliveryFrom(now());
            }
        });
    }

    /**
     * Build a unique, customer-friendly tracking number: SWT-20260722-K4F9C2.
     *
     * The random suffix (rather than a sequence) means a customer cannot guess
     * another customer's tracking number by incrementing their own.
     */
    public static function generateTrackingNumber(): string
    {
        do {
            $candidate = sprintf(
                'SWT-%s-%s',
                now()->format('Ymd'),
                Str::upper(Str::random(6))
            );
        } while (self::withTrashed()->where('tracking_number', $candidate)->exists());

        return $candidate;
    }

    // ---------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ParcelImage::class);
    }

    /**
     * The full audit trail, oldest first — the order the timeline renders in.
     */
    public function trackings(): HasMany
    {
        return $this->hasMany(ParcelTracking::class)
            ->orderBy('happened_at')
            ->orderBy('id');
    }

    public function latestTracking(): HasOne
    {
        return $this->hasOne(ParcelTracking::class)->latestOfMany('happened_at');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->latest('assigned_at');
    }

    /**
     * The assignment currently responsible for this parcel, if any.
     */
    public function activeDelivery(): HasOne
    {
        return $this->hasOne(Delivery::class)
            ->whereIn('status', DeliveryStatus::activeValues())
            ->latestOfMany('assigned_at');
    }

    public function latestDelivery(): HasOne
    {
        return $this->hasOne(Delivery::class)->latestOfMany('assigned_at');
    }

    // ---------------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------------

    public function getDimensionsAttribute(): ?string
    {
        if (! $this->length_cm || ! $this->width_cm || ! $this->height_cm) {
            return null;
        }

        return sprintf(
            '%s × %s × %s cm',
            rtrim(rtrim((string) $this->length_cm, '0'), '.'),
            rtrim(rtrim((string) $this->width_cm, '0'), '.'),
            rtrim(rtrim((string) $this->height_cm, '0'), '.')
        );
    }

    /**
     * Volumetric weight (industry standard divisor 5000) — carriers bill on
     * whichever is greater, actual or volumetric.
     */
    public function getVolumetricWeightAttribute(): ?float
    {
        if (! $this->length_cm || ! $this->width_cm || ! $this->height_cm) {
            return null;
        }

        return round(
            ((float) $this->length_cm * (float) $this->width_cm * (float) $this->height_cm) / 5000,
            3
        );
    }

    public function getChargeableWeightAttribute(): float
    {
        return max((float) $this->weight, $this->volumetric_weight ?? 0.0);
    }

    public function getReceiverFullAddressAttribute(): string
    {
        return collect([$this->receiver_address, $this->receiver_city, $this->receiver_postal_code])
            ->filter()
            ->implode(', ');
    }

    /**
     * Past the promised delivery window and not yet delivered.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->expected_delivery_at !== null
            && $this->expected_delivery_at->isPast()
            && ! in_array($this->status, [
                ParcelStatus::Delivered,
                ParcelStatus::Returned,
                ParcelStatus::Cancelled,
            ], strict: true);
    }

    public function getIsEditableAttribute(): bool
    {
        return ! $this->status->isFinal();
    }

    public function getCanBeCancelledAttribute(): bool
    {
        return in_array($this->status, [
            ParcelStatus::Pending,
            ParcelStatus::PickedUp,
            ParcelStatus::AtWarehouse,
        ], strict: true);
    }

    /**
     * Public URL a scanned QR code resolves to.
     */
    public function getTrackingUrlAttribute(): string
    {
        return route('track.show', $this->tracking_number);
    }

    public function getTransitDaysAttribute(): ?int
    {
        return $this->delivered_at?->diffInDays($this->created_at);
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when(filled($term), function (Builder $q) use ($term): void {
            $like = '%'.trim((string) $term).'%';

            $q->where(function (Builder $inner) use ($like): void {
                $inner->where('tracking_number', 'like', $like)
                    ->orWhere('receiver_name', 'like', $like)
                    ->orWhere('receiver_phone', 'like', $like)
                    ->orWhere('receiver_city', 'like', $like)
                    ->orWhereHas('customer', function (Builder $customer) use ($like): void {
                        $customer->where('full_name', 'like', $like)
                            ->orWhere('mobile', 'like', $like)
                            ->orWhere('customer_code', 'like', $like);
                    });
            });
        });
    }

    public function scopeStatus(Builder $query, ParcelStatus|string|null $status): Builder
    {
        return $query->when($status, fn (Builder $q) => $q->where(
            'status',
            $status instanceof ParcelStatus ? $status->value : $status
        ));
    }

    public function scopePriority(Builder $query, DeliveryPriority|string|null $priority): Builder
    {
        return $query->when($priority, fn (Builder $q) => $q->where(
            'priority',
            $priority instanceof DeliveryPriority ? $priority->value : $priority
        ));
    }

    public function scopeOfCustomer(Builder $query, ?int $customerId): Builder
    {
        return $query->when($customerId, fn (Builder $q) => $q->where('customer_id', $customerId));
    }

    public function scopeOfBranch(Builder $query, ?int $branchId): Builder
    {
        return $query->when($branchId, fn (Builder $q) => $q->where('branch_id', $branchId));
    }

    /**
     * Parcels whose most recent assignment belongs to the given driver.
     */
    public function scopeOfDriver(Builder $query, ?int $driverId): Builder
    {
        return $query->when($driverId, fn (Builder $q) => $q->whereHas(
            'deliveries',
            fn (Builder $d) => $d->where('driver_id', $driverId)
        ));
    }

    /**
     * Inclusive date range on the booking date.
     */
    public function scopeDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->whereDate('created_at', '>=', Carbon::parse($from)))
            ->when($to, fn (Builder $q) => $q->whereDate('created_at', '<=', Carbon::parse($to)));
    }

    public function scopeCreatedToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeDeliveredToday(Builder $query): Builder
    {
        return $query->whereDate('delivered_at', today());
    }

    public function scopeInTransit(Builder $query): Builder
    {
        return $query->whereIn('status', ParcelStatus::inTransitValues());
    }

    /**
     * Parcels ready to be handed to a driver: not cancelled, not finished, and
     * without an assignment already in flight.
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [
                ParcelStatus::Pending->value,
                ParcelStatus::PickedUp->value,
                ParcelStatus::AtWarehouse->value,
                ParcelStatus::FailedDelivery->value,
            ])
            ->whereDoesntHave('deliveries', fn (Builder $d) => $d->whereIn(
                'status',
                DeliveryStatus::activeValues()
            ));
    }

    public function scopeRevenueCounted(Builder $query): Builder
    {
        return $query->where('payment_status', PaymentStatus::Paid);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isDriver()) {
            // Drivers only ever see parcels they have been assigned.
            return $query->whereHas('deliveries', fn (Builder $d) => $d->where(
                'driver_id',
                $user->driver?->id ?? 0
            ));
        }

        return $query->where('branch_id', $user->branch_id);
    }
}
