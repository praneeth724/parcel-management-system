<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryStatus;
use Database\Factories\DeliveryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A single driver assignment for a parcel.
 *
 * @property int $id
 * @property DeliveryStatus $status
 * @property int $attempt_number
 */
class Delivery extends Model
{
    /** @use HasFactory<DeliveryFactory> */
    use HasFactory;

    protected $table = 'deliveries';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'parcel_id',
        'driver_id',
        'assigned_by',
        'status',
        'attempt_number',
        'assigned_at',
        'accepted_at',
        'rejected_at',
        'picked_up_at',
        'completed_at',
        'failed_at',
        'rejection_reason',
        'failure_reason',
        'delivery_location',
        'delivery_latitude',
        'delivery_longitude',
        'received_by',
        'receiver_nic',
        'signature_path',
        'proof_image_path',
        'cod_collected',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'attempt_number' => 'integer',
            'assigned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'delivery_latitude' => 'decimal:7',
            'delivery_longitude' => 'decimal:7',
            'cod_collected' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (self $delivery): void {
            Storage::disk('public')->delete(array_filter([
                $delivery->signature_path,
                $delivery->proof_image_path,
            ]));
        });
    }

    // ---------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * The dispatcher or manager who made the assignment.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // ---------------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------------

    public function getSignatureUrlAttribute(): ?string
    {
        return $this->signature_path
            ? Storage::disk('public')->url($this->signature_path)
            : null;
    }

    public function getProofImageUrlAttribute(): ?string
    {
        return $this->proof_image_path
            ? Storage::disk('public')->url($this->proof_image_path)
            : null;
    }

    /**
     * Minutes between accepting the job and closing it out.
     */
    public function getDurationMinutesAttribute(): ?int
    {
        $start = $this->accepted_at ?? $this->assigned_at;
        $end = $this->completed_at ?? $this->failed_at;

        if (! $start || ! $end) {
            return null;
        }

        return (int) $start->diffInMinutes($end);
    }

    public function getDurationForHumansAttribute(): string
    {
        $minutes = $this->duration_minutes;

        if ($minutes === null) {
            return '—';
        }

        return $minutes < 60
            ? "{$minutes} min"
            : floor($minutes / 60).'h '.($minutes % 60).'m';
    }

    /**
     * True while the driver still owns this job.
     */
    public function getIsOpenAttribute(): bool
    {
        return $this->status->isOpen();
    }

    public function getCanBeRespondedToAttribute(): bool
    {
        return $this->status === DeliveryStatus::Assigned;
    }

    public function getCanBeCompletedAttribute(): bool
    {
        return in_array($this->status, [
            DeliveryStatus::Accepted,
            DeliveryStatus::InTransit,
        ], strict: true);
    }

    public function getHasProofOfDeliveryAttribute(): bool
    {
        return filled($this->signature_path) || filled($this->proof_image_path);
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    public function scopeStatus(Builder $query, DeliveryStatus|string|null $status): Builder
    {
        return $query->when($status, fn (Builder $q) => $q->where(
            'status',
            $status instanceof DeliveryStatus ? $status->value : $status
        ));
    }

    public function scopeOfDriver(Builder $query, ?int $driverId): Builder
    {
        return $query->when($driverId, fn (Builder $q) => $q->where('driver_id', $driverId));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', DeliveryStatus::activeValues());
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', DeliveryStatus::Completed);
    }

    public function scopeCompletedToday(Builder $query): Builder
    {
        return $query->where('status', DeliveryStatus::Completed)
            ->whereDate('completed_at', today());
    }

    /**
     * Assignments waiting for the driver to accept or reject.
     */
    public function scopePendingResponse(Builder $query): Builder
    {
        return $query->where('status', DeliveryStatus::Assigned);
    }

    public function scopeDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->whereDate('assigned_at', '>=', Carbon::parse($from)))
            ->when($to, fn (Builder $q) => $q->whereDate('assigned_at', '<=', Carbon::parse($to)));
    }

    public function scopeOfBranch(Builder $query, ?int $branchId): Builder
    {
        return $query->when($branchId, fn (Builder $q) => $q->whereHas(
            'parcel',
            fn (Builder $p) => $p->where('branch_id', $branchId)
        ));
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isDriver()) {
            return $query->where('driver_id', $user->driver?->id ?? 0);
        }

        return $query->whereHas('parcel', fn (Builder $p) => $p->where('branch_id', $user->branch_id));
    }
}
