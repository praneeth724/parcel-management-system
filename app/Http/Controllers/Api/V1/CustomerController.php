<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\ParcelResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::query()
            ->visibleTo($request->user())
            ->with('branch:id,name,code,city')
            ->withCount('parcels')
            ->search($request->string('search')->toString())
            ->status($request->string('status')->toString() ?: null)
            ->city($request->string('city')->toString() ?: null)
            ->latest()
            ->paginate($this->perPage($request))
            ->withQueryString();

        return CustomerResource::collection($customers)
            ->additional(['success' => true])
            ->response();
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return $this->created(
            new CustomerResource($customer->load('branch')),
            "Customer created with ID {$customer->customer_code}."
        );
    }

    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return $this->success(
            new CustomerResource($customer->load('branch')->loadCount('parcels'))
        );
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->validated());

        return $this->success(
            new CustomerResource($customer->fresh()->load('branch')),
            'Customer updated.'
        );
    }

    /**
     * Soft delete — the shipment history is preserved.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return $this->success(null, 'Customer archived. Their shipment history is preserved.');
    }

    /**
     * A customer's full shipment history.
     */
    public function parcels(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('viewShipmentHistory', $customer);

        $parcels = $customer->parcels()
            ->with(['branch:id,name,code', 'activeDelivery.driver:id,full_name,driver_code'])
            ->status($request->string('status')->toString() ?: null)
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ParcelResource::collection($parcels)
            ->additional(['success' => true])
            ->response();
    }
}
