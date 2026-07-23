<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Enums\ParcelStatus;
use App\Enums\PaymentStatus;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Branch;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $user = $request->user();

        $customers = Customer::query()
            ->visibleTo($user)
            ->with('branch:id,name,code')
            ->withCount('parcels')
            ->search($request->string('search')->toString())
            ->status($request->string('status')->toString() ?: null)
            ->city($request->string('city')->toString() ?: null)
            ->when(
                $request->boolean('trashed') && $user->can('view-trash'),
                fn ($q) => $q->onlyTrashed()
            )
            ->latest()
            ->paginate(config('courier.pagination.web'))
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'statuses' => CustomerStatus::options(),
            'cities' => Customer::query()
                ->visibleTo($user)
                ->distinct()
                ->orderBy('city')
                ->pluck('city', 'city'),
            'filters' => $request->only(['search', 'status', 'city', 'trashed']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('customers.create', [
            'statuses' => CustomerStatus::options(),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', "Customer {$customer->full_name} was created with ID {$customer->customer_code}.");
    }

    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        $customer->load(['branch:id,name,code,city', 'creator:id,name']);

        // Shipment history plus the headline figures shown on the profile.
        $parcels = $customer->parcels()
            ->with('branch:id,name,code')
            ->paginate(10, ['*'], 'parcels');

        return view('customers.show', [
            'customer' => $customer,
            'parcels' => $parcels,
            'summary' => [
                'total' => $customer->parcels()->count(),
                'delivered' => $customer->parcels()->where('status', ParcelStatus::Delivered)->count(),
                'in_transit' => $customer->parcels()->inTransit()->count(),
                'failed' => $customer->parcels()->where('status', ParcelStatus::FailedDelivery)->count(),
                'total_spend' => (float) $customer->parcels()
                    ->where('payment_status', PaymentStatus::Paid)
                    ->sum('delivery_charge'),
                'last_shipment' => $customer->parcels()->max('created_at'),
            ],
        ]);
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('customers.edit', [
            'customer' => $customer,
            'statuses' => CustomerStatus::options(),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', "Customer {$customer->full_name} was updated.");
    }

    /**
     * Soft delete — the shipment history is kept for reporting.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success', "Customer {$customer->full_name} was archived. Their shipment history is preserved.");
    }

    public function restore(Customer $customer): RedirectResponse
    {
        $this->authorize('restore', $customer);

        $customer->restore();

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', "Customer {$customer->full_name} was restored.");
    }

    /**
     * Only a Super Admin picks the branch; everyone else is pinned to theirs.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function branchOptions(): \Illuminate\Support\Collection
    {
        return auth()->user()->isSuperAdmin()
            ? Branch::active()->orderBy('name')->get()->pluck('label', 'id')
            : collect();
    }
}
