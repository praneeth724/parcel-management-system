<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Http\Resources\ParcelResource;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Branch::class);

        $branches = Branch::query()
            ->visibleTo($request->user())
            ->with('manager:id,name,email')
            ->withCount(['drivers', 'staff', 'customers', 'parcels'])
            ->search($request->string('search')->toString())
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return BranchResource::collection($branches)
            ->additional(['success' => true])
            ->response();
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = Branch::create($request->validated());

        return $this->created(
            new BranchResource($branch->load('manager')),
            "Branch {$branch->name} created."
        );
    }

    public function show(Branch $branch): JsonResponse
    {
        $this->authorize('view', $branch);

        return $this->success(new BranchResource(
            $branch->load('manager')->loadCount(['drivers', 'staff', 'customers', 'parcels'])
        ));
    }

    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $branch->update($request->validated());

        return $this->success(
            new BranchResource($branch->fresh()->load('manager')),
            'Branch updated.'
        );
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $this->authorize('delete', $branch);

        $liveParcels = $branch->parcels()->inTransit()->count();

        if ($liveParcels > 0) {
            return $this->error(
                "This branch still has {$liveParcels} parcels in transit. Close them out first."
            );
        }

        $branch->delete();

        return $this->success(null, 'Branch archived.');
    }

    /**
     * Shipments handled by a branch.
     */
    public function parcels(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('viewShipments', $branch);

        $parcels = $branch->parcels()
            ->with(['customer:id,full_name,customer_code', 'activeDelivery.driver:id,full_name'])
            ->search($request->string('search')->toString())
            ->status($request->string('status')->toString() ?: null)
            ->dateRange(
                $request->string('from')->toString() ?: null,
                $request->string('to')->toString() ?: null
            )
            ->latest()
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ParcelResource::collection($parcels)
            ->additional(['success' => true])
            ->response();
    }
}
