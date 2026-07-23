<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Parcel;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * The delivery lifecycle over HTTP — this is the surface a driver's mobile app
 * would talk to.
 */
class DeliveryController extends ApiController
{
    public function __construct(private readonly DeliveryService $deliveries) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Delivery::class);

        $deliveries = Delivery::query()
            ->visibleTo($request->user())
            ->with([
                'parcel:id,tracking_number,receiver_name,receiver_phone,receiver_address,receiver_city,status,priority,payment_method,cod_amount,delivery_charge',
                'driver:id,full_name,driver_code,phone',
                'assignedBy:id,name',
            ])
            ->status($request->string('status')->toString() ?: null)
            ->ofDriver($request->integer('driver_id') ?: null)
            ->dateRange(
                $request->string('from')->toString() ?: null,
                $request->string('to')->toString() ?: null
            )
            ->latest('assigned_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return DeliveryResource::collection($deliveries)
            ->additional(['success' => true])
            ->response();
    }

    public function show(Delivery $delivery): JsonResponse
    {
        $this->authorize('view', $delivery);

        return $this->success(new DeliveryResource(
            $delivery->load(['parcel.customer', 'parcel.branch:id,name,code', 'driver', 'assignedBy:id,name'])
        ));
    }

    /**
     * Assign a parcel to a driver.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parcel_id' => ['required', 'integer', Rule::exists('parcels', 'id')->whereNull('deleted_at')],
            'driver_id' => ['required', 'integer', Rule::exists('drivers', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $parcel = Parcel::findOrFail($validated['parcel_id']);
        $driver = Driver::findOrFail($validated['driver_id']);

        $this->authorize('assignDriver', $parcel);

        try {
            $delivery = $this->deliveries->assign(
                $parcel,
                $driver,
                $request->user(),
                $validated['notes'] ?? null
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->created(
            new DeliveryResource($delivery->load('parcel', 'driver')),
            "{$parcel->tracking_number} assigned to {$driver->full_name}."
        );
    }

    public function accept(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorize('accept', $delivery);

        try {
            $this->deliveries->accept($delivery, $request->user());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->success(new DeliveryResource($delivery->fresh()), 'Delivery accepted.');
    }

    public function reject(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorize('reject', $delivery);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        try {
            $this->deliveries->reject($delivery, $request->user(), $validated['reason']);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->success(new DeliveryResource($delivery->fresh()), 'Delivery declined.');
    }

    public function markInTransit(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorize('update', $delivery);

        $validated = $request->validate([
            'location' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $this->deliveries->markInTransit($delivery, $request->user(), $validated['location'] ?? null);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->success(
            new DeliveryResource($delivery->fresh()->load('parcel')),
            'Parcel marked as out for delivery.'
        );
    }

    /**
     * Complete a delivery, optionally with a signature and proof photo.
     */
    public function complete(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorize('complete', $delivery);

        $validated = $request->validate([
            'received_by' => ['required', 'string', 'min:3', 'max:150'],
            'receiver_nic' => ['nullable', 'string', 'max:30'],
            'delivery_location' => ['nullable', 'string', 'max:191'],
            'delivery_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'cod_collected' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],

            // Base64 data URL, so a mobile client can post it as plain JSON.
            'signature' => ['nullable', 'string'],
            'proof_image' => [
                'nullable', 'image',
                'mimes:'.implode(',', config('courier.uploads.image_mimes')),
                'max:'.config('courier.uploads.max_image_kb'),
            ],
        ]);

        try {
            $this->deliveries->complete($delivery, $request->user(), [
                ...$validated,
                'proof_image' => $request->file('proof_image'),
            ]);
        } catch (RuntimeException | \InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }

        return $this->success(
            new DeliveryResource($delivery->fresh()->load('parcel')),
            'Delivery completed.'
        );
    }

    public function markFailed(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorize('markFailed', $delivery);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
            'location' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $this->deliveries->markFailed(
                $delivery,
                $request->user(),
                $validated['reason'],
                $validated['location'] ?? null
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->success(
            new DeliveryResource($delivery->fresh()->load('parcel')),
            'Failed attempt recorded.'
        );
    }

    /**
     * Management pulls an assignment back from a driver.
     */
    public function cancelAssignment(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorize('reassign', $delivery);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        try {
            $this->deliveries->cancelAssignment($delivery, $request->user(), $validated['reason']);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->success(
            new DeliveryResource($delivery->fresh()),
            'Assignment cancelled; the parcel is back in the pool.'
        );
    }
}
