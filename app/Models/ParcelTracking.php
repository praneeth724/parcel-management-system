<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TrackingStatus;
use Database\Factories\ParcelTrackingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable event on a parcel's tracking timeline.
 *
 * @property int $id
 * @property TrackingStatus $status
 * @property \Illuminate\Support\Carbon $happened_at
 */
class ParcelTracking extends Model
{
    /** @use HasFactory<ParcelTrackingFactory> */
    use HasFactory;

    protected $table = 'parcel_trackings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'parcel_id',
        'status',
        'location',
        'remarks',
        'updated_by',
        'branch_id',
        'happened_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TrackingStatus::class,
            'happened_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    /**
     * The staff member who logged this event. Null for system events.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // ---------------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------------

    /**
     * Name shown on the public tracking page, where staff identities are
     * deliberately reduced to "Courier Team".
     */
    public function getActorNameAttribute(): string
    {
        return $this->updatedBy?->name ?? 'System';
    }

    public function getPublicActorNameAttribute(): string
    {
        return $this->updatedBy ? 'Courier Team' : 'System';
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    public function scopeStatus(Builder $query, TrackingStatus|string|null $status): Builder
    {
        return $query->when($status, fn (Builder $q) => $q->where(
            'status',
            $status instanceof TrackingStatus ? $status->value : $status
        ));
    }

    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('happened_at')->orderBy('id');
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('happened_at')->orderByDesc('id');
    }
}
