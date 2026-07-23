@extends('layouts.app')

@section('title', 'Customers')

@section('content')
    <x-page-header title="Customers"
                   :subtitle="$customers->total().' '.str('customer')->plural($customers->total()).' on record'">
        <x-slot:actions>
            @can('create', App\Models\Customer::class)
                <a href="{{ route('customers.create') }}" class="btn btn-primary">
                    <i class="bi bi-person-plus me-1"></i> New customer
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Search & filters --}}
    <form method="GET" action="{{ route('customers.index') }}" class="filter-bar">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="search" class="form-label small fw-semibold">Search</label>
                <input type="search"
                       id="search"
                       name="search"
                       value="{{ $filters['search'] ?? '' }}"
                       class="form-control"
                       placeholder="Name, mobile, email, NIC or customer ID">
            </div>

            <div class="col-6 col-md-3">
                <label for="status" class="form-label small fw-semibold">Status</label>
                <select id="status" name="status" class="form-select" data-auto-submit>
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-md-3">
                <label for="city" class="form-label small fw-semibold">City</label>
                <select id="city" name="city" class="form-select" data-auto-submit>
                    <option value="">All cities</option>
                    @foreach ($cities as $city)
                        <option value="{{ $city }}" @selected(($filters['city'] ?? '') === $city)>{{ $city }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel"></i> Filter
                </button>
                <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary" title="Clear filters">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>

        @can('view-trash')
            <div class="form-check mt-3">
                <input type="checkbox"
                       name="trashed"
                       id="trashed"
                       value="1"
                       class="form-check-input"
                       data-auto-submit
                       @checked($filters['trashed'] ?? false)>
                <label for="trashed" class="form-check-label small">Show archived customers only</label>
            </div>
        @endcan
    </form>

    <div class="table-card">
        @if ($customers->isEmpty())
            <x-empty-state icon="bi-people"
                           title="No customers found"
                           message="Try widening your filters, or add the first customer.">
                @can('create', App\Models\Customer::class)
                    <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm">Add a customer</a>
                @endcan
            </x-empty-state>
        @else
            <div class="table-card__scroll">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Customer ID</th>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>City</th>
                            <th>Branch</th>
                            <th class="text-end">Shipments</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $customer)
                            <tr>
                                <td><span class="tracking-code small">{{ $customer->customer_code }}</span></td>
                                <td>
                                    <a href="{{ route('customers.show', $customer) }}" class="text-decoration-none fw-semibold">
                                        {{ $customer->full_name }}
                                    </a>
                                    @if ($customer->company_name)
                                        <div><small class="text-muted">{{ $customer->company_name }}</small></div>
                                    @endif
                                </td>
                                <td>
                                    <a href="tel:{{ $customer->mobile }}" class="text-decoration-none">{{ $customer->mobile }}</a>
                                    @if ($customer->email)
                                        <div><small class="text-muted">{{ $customer->email }}</small></div>
                                    @endif
                                </td>
                                <td>{{ $customer->city }}</td>
                                <td><small>{{ $customer->branch?->name ?? '—' }}</small></td>
                                <td class="text-end fw-semibold">{{ $customer->parcels_count }}</td>
                                <td><x-status-badge :status="$customer->status" /></td>
                                <td>
                                    <div class="table-actions">
                                        @if ($customer->trashed())
                                            @can('restore', $customer)
                                                <form method="POST" action="{{ route('customers.restore', $customer) }}">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-success" title="Restore">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        @else
                                            <a href="{{ route('customers.show', $customer) }}"
                                               class="btn btn-sm btn-outline-secondary" title="View profile">
                                                <i class="bi bi-eye"></i>
                                            </a>

                                            @can('update', $customer)
                                                <a href="{{ route('customers.edit', $customer) }}"
                                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            @endcan

                                            @can('delete', $customer)
                                                <form method="POST"
                                                      action="{{ route('customers.destroy', $customer) }}"
                                                      data-confirm="Archive {{ $customer->full_name }}? Their shipment history is kept.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger" title="Archive">
                                                        <i class="bi bi-archive"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-3 border-top">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
@endsection
