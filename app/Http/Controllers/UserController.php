<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $currentUser = $request->user();

        $users = User::query()
            ->visibleTo($currentUser)
            ->with('branch:id,name,code')
            ->search($request->string('search')->toString())
            ->role($request->string('role')->toString() ?: null)
            ->ofBranch($request->integer('branch_id') ?: null)
            ->when($request->filled('status'), fn ($q) => $q->where(
                'is_active',
                $request->string('status')->toString() === 'active'
            ))
            ->when(
                $request->boolean('trashed') && $currentUser->can('view-trash'),
                fn ($q) => $q->onlyTrashed()
            )
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(config('courier.pagination.web'))
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => $this->assignableRoles($currentUser),
            'branches' => $this->visibleBranches($currentUser),
            'filters' => $request->only(['search', 'role', 'branch_id', 'status', 'trashed']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', User::class);

        return view('users.create', [
            'roles' => $this->assignableRoles($request->user()),
            'branches' => $this->visibleBranches($request->user()),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        // New staff accounts still have to prove they own the mailbox.
        if (config('courier.registration.requires_email_verification')) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()
            ->route('users.show', $user)
            ->with('success', "{$user->name} was added as a {$user->role->label()}.");
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        $user->load(['branch:id,name,code,city', 'driver:id,driver_code,full_name,status', 'managedBranch:id,name']);

        return view('users.show', [
            'user' => $user,
            'activity' => [
                'parcels_created' => $user->createdParcels()->count(),
                'customers_created' => $user->createdCustomers()->count(),
                'tracking_updates' => $user->trackingUpdates()->count(),
                'deliveries_assigned' => $user->assignedDeliveries()->count(),
            ],
            'recentParcels' => $user->createdParcels()
                ->with('customer:id,full_name')
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', [
            'user' => $user,
            'roles' => $this->assignableRoles($request->user()),
            'branches' => $this->visibleBranches($request->user()),
            'canAssignRole' => $request->user()->can('assignRole', $user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->userData());

        return redirect()
            ->route('users.show', $user)
            ->with('success', "{$user->name}'s account was updated.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        // A driver mid-run must not lose their account underneath them.
        if ($user->driver?->activeDeliveries()->exists()) {
            return back()->with(
                'error',
                "{$user->name} has open deliveries. Reassign them before archiving this account."
            );
        }

        $user->tokens()->delete();
        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', "{$user->name}'s account was archived.");
    }

    public function restore(User $user): RedirectResponse
    {
        $this->authorize('restore', $user);

        $user->restore();

        return redirect()
            ->route('users.show', $user)
            ->with('success', "{$user->name}'s account was restored.");
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        $this->authorize('toggleStatus', $user);

        $activating = ! $user->is_active;

        // Never switch off the last active Super Admin.
        if (! $activating && $user->isSuperAdmin() && $this->activeSuperAdminCount($user) === 0) {
            return back()->with(
                'error',
                'This is the last active Super Admin. Promote another user before deactivating this account.'
            );
        }

        $user->update(['is_active' => $activating]);

        // Deactivating revokes API access immediately as well as the session.
        if (! $activating) {
            $user->tokens()->delete();
        }

        return back()->with(
            'success',
            "{$user->name}'s account is now ".($activating ? 'active' : 'deactivated').'.'
        );
    }

    private function activeSuperAdminCount(User $excluding): int
    {
        return User::query()
            ->where('role', UserRole::SuperAdmin)
            ->where('is_active', true)
            ->whereKeyNot($excluding->id)
            ->count();
    }

    /**
     * A Branch Manager may only hand out the two roles beneath them.
     *
     * @return array<string, string>
     */
    private function assignableRoles(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return UserRole::options();
        }

        return [
            UserRole::Dispatcher->value => UserRole::Dispatcher->label(),
            UserRole::Driver->value => UserRole::Driver->label(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function visibleBranches(User $user): \Illuminate\Support\Collection
    {
        return Branch::query()
            ->visibleTo($user)
            ->active()
            ->orderBy('name')
            ->get()
            ->pluck('label', 'id');
    }
}
