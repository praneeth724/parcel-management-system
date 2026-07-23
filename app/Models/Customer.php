<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Enums\ParcelStatus;
use App\Models\Concerns\GeneratesReferenceCode;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A sender who books parcels with the courier.
 *
 * @property int $id
 * @property string $customer_code
 * @property string $full_name
 * @property CustomerStatus $status
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use GeneratesReferenceCode, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_code',
        'full_name',
        'nic_passport',
        'mobile',
        'email',
        'address',
        'city',
        'postal_code',
        'company_name',
        'status',
        'branch_id',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
        ];
    }

    public function referenceCodeColumn(): string
    {
        return 'customer_code';
    }

    public static function referenceCodePrefix(): string
    {
        return 'CUS';
    }

    // ---------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Full shipment history, newest first.
     */
    public function parcels(): HasMany
    {
        return $this->hasMany(Parcel::class)->latest();
    }

    public function deliveredParcels(): HasMany
    {
        return $this->hasMany(Parcel::class)->where('status', ParcelStatus::Delivered);
    }

    // ---------------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------------

    public function getFullAddressAttribute(): string
    {
        return collect([$this->address, $this->city, $this->postal_code])
            ->filter()
            ->implode(', ');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->company_name
            ? "{$this->full_name} — {$this->company_name}"
            : $this->full_name;
    }

    /**
     * True when new parcels may be booked against this customer.
     */
    public function getCanBookAttribute(): bool
    {
        return $this->status->canBookParcels();
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    /**
     * Name, mobile or email — the three fields the spec asks to search on.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when(filled($term), function (Builder $q) use ($term): void {
            $like = '%'.trim((string) $term).'%';

            $q->where(function (Builder $inner) use ($like): void {
                $inner->where('full_name', 'like', $like)
                    ->orWhere('mobile', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('customer_code', 'like', $like)
                    ->orWhere('nic_passport', 'like', $like)
                    ->orWhere('company_name', 'like', $like);
            });
        });
    }

    public function scopeStatus(Builder $query, CustomerStatus|string|null $status): Builder
    {
        return $query->when($status, fn (Builder $q) => $q->where(
            'status',
            $status instanceof CustomerStatus ? $status->value : $status
        ));
    }

    public function scopeCity(Builder $query, ?string $city): Builder
    {
        return $query->when(filled($city), fn (Builder $q) => $q->where('city', $city));
    }

    public function scopeOfBranch(Builder $query, ?int $branchId): Builder
    {
        return $query->when($branchId, fn (Builder $q) => $q->where('branch_id', $branchId));
    }

    /**
     * Customers with no branch are visible to everyone: they were registered
     * centrally and any branch may ship for them.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user): void {
            $q->where('branch_id', $user->branch_id)->orWhereNull('branch_id');
        });
    }
}
