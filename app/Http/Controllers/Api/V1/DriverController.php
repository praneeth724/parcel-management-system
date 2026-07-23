<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeliveryStatus;
use App\Http\Requests\StoreDriverRequest;
use App\Http\Requests\UpdateDriverRequest;
use App\Http\Resources\DeliveryResource;
use App\Http\Resources\DriverResource;
use App\Models\Driver;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends ApiController
{
    public function __construct(private readonly FileUploadService $uploads) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Driver::class);

        $drivers = Driver::query()
            ->visibleTo($request->user())
            ->with('branch:id,name,code,city')
            ->withCount([
                'deliveries as completed_deliveries_count' => fn ($q) => $q
                    ->where('status', DeliveryStatus::Completed),
                'deliveries as failed_deliveries_count' => fn ($q) => $q
                    ->where('status', DeliveryStatus::Failed),
                'deliveries as active_deliveries_count' => fn ($q) => $q
                    ->whereIn('status', DeliveryStatus::activeValues()),
            ])
            ->search($request->string('search')->toString())
            ->status($request->string('status')->toString() ?: null)
            ->ofBranch($request->integer('branch_id') ?: null)
            ->orderBy('full_name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return DriverResource::collection($drivers)
            ->additional(['success' => true])
            ->response();
    }

    public function store(StoreDriverRequest $request): JsonResponse
    {
        $data = $request->driverData();

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $this->uploads->store($request->file('photo'), 'driver_photos');
        }

        $driver = Driver::create($data);

        return $this->created(
            new DriverResource($driver->load('branch')),
            "Driver added with code {$driver->driver_code}."
        );
    }

    public function show(Driver $driver): JsonResponse
    {
        $this->authorize('view', $driver);

        $driver->load('branch')->loadCount([
            'deliveries as completed_deliveries_count' => fn ($q) => $q
                ->where('status', DeliveryStatus::Completed),
            'deliveries as failed_deliveries_count' => fn ($q) => $q
                ->where('status', DeliveryStatus::Failed),
            'deliveries as active_deliveries_count' => fn ($q) => $q
                ->whereIn('status', DeliveryStatus::activeValues()),
        ]);

        return $this->success(new DriverResource($driver));
    }

    public function update(UpdateDriverRequest $request, Driver $driver): JsonResponse
    {
        $data = $request->driverData();

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $this->uploads->replace(
                $request->file('photo'),
                'driver_photos',
                $driver->photo_path
            );
        }

        $driver->update($data);

        return $this->success(
            new DriverResource($driver->fresh()->load('branch')),
            'Driver updated.'
        );
    }

    public function destroy(Driver $driver): JsonResponse
    {
        $this->authorize('delete', $driver);

        $open = $driver->deliveries()->whereIn('status', DeliveryStatus::activeValues())->count();

        if ($open > 0) {
            return $this->error(
                "This driver still has {$open} open ".str('delivery')->plural($open)
                    .'. Reassign them before archiving.'
            );
        }

        $driver->delete();
        $driver->user?->update(['is_active' => false]);

        return $this->success(null, 'Driver archived.');
    }

    /**
     * A driver's assignments.
     */
    public function deliveries(Request $request, Driver $driver): JsonResponse
    {
        $this->authorize('viewDeliveries', $driver);

        $deliveries = $driver->deliveries()
            ->with('parcel:id,tracking_number,receiver_name,receiver_city,status,priority')
            ->status($request->string('status')->toString() ?: null)
            ->paginate($this->perPage($request))
            ->withQueryString();

        return DeliveryResource::collection($deliveries)
            ->additional(['success' => true])
            ->response();
    }
}
