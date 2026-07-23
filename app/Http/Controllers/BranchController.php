<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ParcelStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Branch::class);

        $branches = Branch::query()
            ->visibleTo($request->user())
            ->with('manager:id,name,email')
            ->withCount(['drivers', 'parcels', 'staff', 'customers'])
            ->search($request->string('search')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where(
                'is_active',
                $request->string('status')->toString() === 'active'
            ))
            ->orderBy('name')
            ->paginate(config('courier.pagination.web'))
            ->withQueryString();

        return view('branches.index', [
            'branches' => $branches,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Branch::class);

        return view('branches.create', [
            'managers' => $this->availableManagers(),
        ]);
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $branch = Branch::create($request->validated());

        // Keep the manager's own branch assignment in step with the branch
        // they were just put in charge of.
        if ($branch->manager_id) {
            User::whereKey($branch->manager_id)->update(['branch_id' => $branch->id]);
        }

        return redirect()
            ->route('branches.show', $branch)
            ->with('success', "Branch {$branch->name} was created.");
    }

    public function show(Branch $branch): View
    {
        $this->authorize('view', $branch);

        $branch->load(['manager:id,name,email,phone']);

        $branch->loadCount(['drivers', 'staff', 'customers', 'parcels']);

        return view('branches.show', [
            'branch' => $branch,
            'summary' => [
                'delivered' => $branch->parcels()->where('status', ParcelStatus::Delivered)->count(),
                'in_transit' => $branch->parcels()->inTransit()->count(),
                'failed' => $branch->parcels()->where('status', ParcelStatus::FailedDelivery)->count(),
                'revenue' => (float) $branch->parcels()
                    ->where('payment_status', PaymentStatus::Paid)
                    ->sum('delivery_charge'),
                'today' => $branch->parcels()->whereDate('created_at', today())->count(),
            ],
            'drivers' => $branch->drivers()
                ->orderBy('full_name')
                ->get(['id', 'driver_code', 'full_name', 'phone', 'vehicle_number', 'vehicle_type', 'status']),
            'staff' => $branch->staff()
                ->orderBy('role')
                ->get(['id', 'name', 'email', 'role', 'is_active']),
        ]);
    }

    public function edit(Branch $branch): View
    {
        $this->authorize('update', $branch);

        return view('branches.edit', [
            'branch' => $branch,
            'managers' => $this->availableManagers($branch),
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $previousManager = $branch->manager_id;

        $branch->update($request->validated());

        if ($branch->manager_id && $branch->manager_id !== $previousManager) {
            User::whereKey($branch->manager_id)->update(['branch_id' => $branch->id]);
        }

        return redirect()
            ->route('branches.show', $branch)
            ->with('success', "Branch {$branch->name} was updated.");
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('delete', $branch);

        // Closing a branch that still holds live parcels would strand them.
        $liveParcels = $branch->parcels()->inTransit()->count();

        if ($liveParcels > 0) {
            return back()->with(
                'error',
                "{$branch->name} still has {$liveParcels} parcels in transit. Close them out before archiving the branch."
            );
        }

        $branch->delete();

        return redirect()
            ->route('branches.index')
            ->with('success', "Branch {$branch->name} was archived. Its staff and history are preserved.");
    }

    /**
     * All shipments handled by a branch, with the usual filters.
     */
    public function shipments(Request $request, Branch $branch): View
    {
        $this->authorize('viewShipments', $branch);

        $parcels = $branch->parcels()
            ->with(['customer:id,full_name,customer_code', 'activeDelivery.driver:id,full_name'])
            ->search($request->string('search')->toString())
            ->status($request->string('status')->toString() ?: null)
            ->dateRange($request->string('from')->toString() ?: null, $request->string('to')->toString() ?: null)
            ->latest()
            ->paginate(config('courier.pagination.web'))
            ->withQueryString();

        return view('branches.shipments', [
            'branch' => $branch,
            'parcels' => $parcels,
            'statuses' => ParcelStatus::options(),
            'filters' => $request->only(['search', 'status', 'from', 'to']),
        ]);
    }

    /**
     * Branch Managers who are free to take a branch, plus the current holder.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function availableManagers(?Branch $branch = null): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('role', UserRole::BranchManager)
            ->active()
            ->where(function ($query) use ($branch): void {
                $query->whereDoesntHave('managedBranch');

                if ($branch?->manager_id) {
                    $query->orWhere('id', $branch->manager_id);
                }
            })
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => "{$user->name} ({$user->email})"]);
    }
}
