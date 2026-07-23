<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Enums\DriverStatus;
use App\Enums\VehicleType;
use App\Models\Concerns\GeneratesReferenceCode;
use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * A delivery driver, optionally linked to a login account.
 *
 * @property int $id
 * @property string $driver_code
 * @property string $full_name
 * @property DriverStatus $status
 * @property VehicleType $vehicle_type
 */
class Driver extends Model
{
    /** @use HasFactory<DriverFactory> */
    use GeneratesReferenceCode, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'driver_code',
        'user_id',
        'full_name',
        'phone',
        'email',
        'vehicle_number',
        'license_number',
        'vehicle_type',
        'branch_id',
        'photo_path',
        'status',
        'license_expiry',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DriverStatus::class,
            'vehicle_type' => VehicleType::class,
            'license_expiry' => 'date',
        ];
    }

    public function referenceCodeColumn(): string
    {
        return 'driver_code';
    }

    public static function referenceCodePrefix(): string
    {
        return 'DRV';
    }

    // ---------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->latest('assigned_at');
    }

    /**
     * Assignments still on this driver's plate.
     */
    public function activeDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)
            ->whereIn('status', DeliveryStatus::activeValues());
    }

    public function completedDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->where('status', DeliveryStatus::Completed);
    }

    public function failedDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->where('status', DeliveryStatus::Failed);
    }

    /**
     * The parcels reachable through this driver's assignments.
     */
    public function parcels(): HasManyThrough
    {
        return $this->hasManyThrough(
            Parcel::class,
            Delivery::class,
            'driver_id',
            'id',
            'id',
            'parcel_id'
        );
    }

    // ---------------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------------

    public function getPhotoUrlAttribute(): string
    {
        if ($this->photo_path && Storage::disk('public')->exists($this->photo_path)) {
            return Storage::disk('public')->url($this->photo_path);
        }

        return 'https://ui-avatars.com/api/?name='.urlencode($this->full_name)
            .'&background=198754&color=fff&bold=true';
    }

    public function getLabelAttribute(): string
    {
        return "{$this->full_name} ({$this->driver_code}) — {$this->vehicle_number}";
    }

    /**
     * True when the driver may be handed another parcel right now.
     */
    public function getIsAssignableAttribute(): bool
    {
        return $this->status->canAcceptAssignments() && ! $this->license_has_expired;
    }

    public function getLicenseHasExpiredAttribute(): bool
    {
        return $this->license_expiry !== null && $this->license_expiry->isPast();
    }

    /**
     * Percentage of finished attempts that ended in a successful delivery.
     *
     * Reads from the `*_deliveries_count` aggregates when they have been
     * eager loaded via withCount(), and falls back to a query otherwise.
     */
    public function getSuccessRateAttribute(): float
    {
        $completed = $this->completed_deliveries_count
            ?? $this->completedDeliveries()->count();
        $failed = $this->failed_deliveries_count
            ?? $this->failedDeliveries()->count();

        $finished = $completed + $failed;

        return $finished === 0 ? 0.0 : round(($completed / $finished) * 100, 1);
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when(filled($term), function (Builder $q) use ($term): void {
            $like = '%'.trim((string) $term).'%';

            $q->where(function (Builder $inner) use ($like): void {
                $inner->where('full_name', 'like', $like)
                    ->orWhere('driver_code', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('vehicle_number', 'like', $like)
                    ->orWhere('license_number', 'like', $like);
            });
        });
    }

    public function scopeStatus(Builder $query, DriverStatus|string|null $status): Builder
    {
        return $query->when($status, fn (Builder $q) => $q->where(
            'status',
            $status instanceof DriverStatus ? $status->value : $status
        ));
    }

    public function scopeOfBranch(Builder $query, ?int $branchId): Builder
    {
        return $query->when($branchId, fn (Builder $q) => $q->where('branch_id', $branchId));
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', DriverStatus::Available);
    }

    public function scopeOnDelivery(Builder $query): Builder
    {
        return $query->where('status', DriverStatus::OnDelivery);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        // A driver may only ever see their own record.
        if ($user->isDriver()) {
            return $query->where('user_id', $user->id);
        }

        return $query->where('branch_id', $user->branch_id);
    }
}
