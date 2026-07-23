<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

/**
 * A staff account: Super Admin, Branch Manager, Dispatcher or Driver.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property int|null $branch_id
 * @property bool $is_active
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'branch_id',
        'phone',
        'avatar_path',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------

    /**
     * The branch this user works at. Super Admins have none.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * The branch this user manages, if they are a Branch Manager.
     */
    public function managedBranch(): HasOne
    {
        return $this->hasOne(Branch::class, 'manager_id');
    }

    /**
     * The driver profile linked to this account, if the user is a Driver.
     */
    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }

    public function createdParcels(): HasMany
    {
        return $this->hasMany(Parcel::class, 'created_by');
    }

    public function createdCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'created_by');
    }

    public function trackingUpdates(): HasMany
    {
        return $this->hasMany(ParcelTracking::class, 'updated_by');
    }

    public function assignedDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'assigned_by');
    }

    // ---------------------------------------------------------------------
    // Role helpers
    // ---------------------------------------------------------------------

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isBranchManager(): bool
    {
        return $this->role === UserRole::BranchManager;
    }

    public function isDispatcher(): bool
    {
        return $this->role === UserRole::Dispatcher;
    }

    public function isDriver(): bool
    {
        return $this->role === UserRole::Driver;
    }

    /**
     * True when the user holds any of the given roles.
     */
    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, strict: true);
    }

    /**
     * Staff who can see beyond their own queue.
     */
    public function isManagement(): bool
    {
        return $this->hasRole(UserRole::SuperAdmin, UserRole::BranchManager);
    }

    /**
     * Branch the user is restricted to, or null when they may see all of them.
     */
    public function visibleBranchId(): ?int
    {
        return $this->isSuperAdmin() ? null : $this->branch_id;
    }

    // ---------------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------------

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar_path && Storage::disk('public')->exists($this->avatar_path)) {
            return Storage::disk('public')->url($this->avatar_path);
        }

        // Deterministic fallback so the UI never renders a broken image.
        return 'https://ui-avatars.com/api/?name='.urlencode($this->name)
            .'&background=0d6efd&color=fff&bold=true';
    }

    public function getInitialsAttribute(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRole(Builder $query, UserRole|string|null $role): Builder
    {
        return $query->when($role, fn (Builder $q) => $q->where(
            'role',
            $role instanceof UserRole ? $role->value : $role
        ));
    }

    public function scopeOfBranch(Builder $query, ?int $branchId): Builder
    {
        return $query->when($branchId, fn (Builder $q) => $q->where('branch_id', $branchId));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when(filled($term), function (Builder $q) use ($term): void {
            $like = '%'.trim((string) $term).'%';

            $q->where(function (Builder $inner) use ($like): void {
                $inner->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        });
    }

    /**
     * Restrict a query to the rows the given user is allowed to see.
     */
    public function scopeVisibleTo(Builder $query, self $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where('branch_id', $user->branch_id);
    }

    // ---------------------------------------------------------------------
    // Notifications
    // ---------------------------------------------------------------------

    /**
     * Point the reset link at this application's own auth routes rather than
     * the Breeze/Fortify defaults, which are not installed here.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }
}
