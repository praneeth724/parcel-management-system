<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\TrackingStatus;
use App\Http\Requests\StoreParcelRequest;
use App\Http\Requests\UpdateParcelRequest;
use App\Http\Resources\ParcelResource;
use App\Http\Resources\ParcelTrackingResource;
use App\Models\Parcel;
use App\Services\ParcelService;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class ParcelController extends ApiController
{
    public function __construct(
        private readonly ParcelService $parcels,
        private readonly QrCodeService $qrCode,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Parcel::class);

        $parcels = Parcel::query()
            ->visibleTo($request->user())
            ->with([
                'customer:id,full_name,customer_code,mobile',
                'branch:id,name,code',
                'activeDelivery.driver:id,full_name,driver_code',
            ])
            ->search($request->string('search')->toString())
            ->status($request->string('status')->toString() ?: null)
            ->priority($request->string('priority')->toString() ?: null)
            ->ofCustomer($request->integer('customer_id') ?: null)
            ->ofBranch($request->integer('branch_id') ?: null)
            ->ofDriver($request->integer('driver_id') ?: null)
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

    public function store(StoreParcelRequest $request): JsonResponse
    {
        $parcel = $this->parcels->create(
            data: $request->parcelData(),
            actor: $request->user(),
            images: $request->file('images', []),
        );

        return $this->created(
            new ParcelResource($parcel->load('customer', 'branch', 'trackings')),
            "Parcel booked. Tracking number: {$parcel->tracking_number}"
        );
    }

    public function show(Parcel $parcel): JsonResponse
    {
        $this->authorize('view', $parcel);

        return $this->success(new ParcelResource($parcel->load([
            'customer', 'branch', 'creator:id,name', 'images',
            'trackings.updatedBy:id,name',
            'deliveries.driver:id,full_name,driver_code,phone',
        ])));
    }

    public function update(UpdateParcelRequest $request, Parcel $parcel): JsonResponse
    {
        try {
            $this->parcels->update($parcel, $request->validated(), $request->user());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->success(
            new ParcelResource($parcel->fresh()->load('customer', 'branch')),
            'Parcel updated.'
        );
    }

    public function destroy(Parcel $parcel): JsonResponse
    {
        $this->authorize('delete', $parcel);

        $parcel->delete();

        return $this->success(null, 'Parcel archived.');
    }

    /**
     * Cancel a parcel and release any driver holding it.
     */
    public function cancel(Request $request, Parcel $parcel): JsonResponse
    {
        $this->authorize('cancel', $parcel);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        try {
            $this->parcels->cancel($parcel, $request->user(), $validated['reason']);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->success(
            new ParcelResource($parcel->fresh()),
            "Parcel {$parcel->tracking_number} cancelled."
        );
    }

    /**
     * The parcel's tracking timeline.
     */
    public function trackings(Parcel $parcel): JsonResponse
    {
        $this->authorize('view', $parcel);

        return $this->success(
            ParcelTrackingResource::collection(
                $parcel->trackings()->with('updatedBy:id,name')->newestFirst()->get()
            )
        );
    }

    /**
     * Log a tracking event, moving the parcel forward when the event implies a
     * status change.
     */
    public function addTracking(Request $request, Parcel $parcel): JsonResponse
    {
        $this->authorize('addTracking', $parcel);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(TrackingStatus::manualOptions()))],
            'location' => ['nullable', 'string', 'max:191'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->parcels->logTrackingEvent(
                parcel: $parcel,
                event: TrackingStatus::from($validated['status']),
                actor: $request->user(),
                location: $validated['location'] ?? null,
                remarks: $validated['remarks'] ?? null,
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->created(
            new ParcelResource($parcel->fresh()->load('trackings.updatedBy:id,name')),
            'Tracking event recorded.'
        );
    }

    /**
     * Everything a QR scan should reveal, as JSON.
     */
    public function qrPayload(Parcel $parcel): JsonResponse
    {
        $this->authorize('view', $parcel);

        return $this->success([
            ...$this->qrCode->payload($parcel->load('customer')),
            'qr_svg_data_uri' => $this->qrCode->dataUri($parcel),
        ]);
    }
}
