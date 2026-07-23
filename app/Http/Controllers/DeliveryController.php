<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DeliveryStatus;
use App\Models\Branch;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Parcel;
use App\Models\User;
use App\Services\DeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class DeliveryController extends Controller
{
    public function __construct(private readonly DeliveryService $deliveries) {}

    /**
     * "My Deliveries" for a driver; the full assignment list for everyone else.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Delivery::class);

        $user = $request->user();

        $deliveries = Delivery::query()
            ->visibleTo($user)
            ->with([
                'parcel:id,tracking_number,receiver_name,receiver_city,receiver_phone,status,priority,payment_method,cod_amount',
                'parcel.customer:id,full_name,mobile',
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
            ->paginate(config('courier.pagination.web'))
            ->withQueryString();

        return view('deliveries.index', [
            'deliveries' => $deliveries,
            'statuses' => DeliveryStatus::options(),
            'drivers' => $user->isDriver()
                ? collect()
                : Driver::query()->visibleTo($user)->orderBy('full_name')->get()->pluck('label', 'id'),
            'filters' => $request->only(['status', 'driver_id', 'from', 'to']),
        ]);
    }

    public function show(Delivery $delivery): View
    {
        $this->authorize('view', $delivery);

        $delivery->load([
            'parcel.customer',
            'parcel.branch:id,name,code,city',
            'driver.branch:id,name',
            'assignedBy:id,name',
        ]);

        return view('deliveries.show', ['delivery' => $delivery]);
    }

    /**
     * The dispatch board: unassigned parcels on one side, free drivers on the
     * other.
     */
    public function assignBoard(Request $request): View
    {
        $this->authorize('assign-deliveries');

        $user = $request->user();
        $branchId = $request->integer('branch_id') ?: $user->visibleBranchId();

        $parcels = Parcel::query()
            ->visibleTo($user)
            ->unassigned()
            ->ofBranch($branchId)
            ->with(['customer:id,full_name,mobile,customer_code', 'branch:id,name,code'])
            ->search($request->string('search')->toString())
            // Same-day first, then express, then normal; oldest booking wins
            // inside each band.
            ->orderByRaw("FIELD(priority, 'same_day', 'express', 'normal')")
            ->oldest()
            ->paginate(config('courier.pagination.web'))
            ->withQueryString();

        return view('deliveries.assign', [
            'parcels' => $parcels,
            'drivers' => Driver::query()
                ->visibleTo($user)
                ->ofBranch($branchId)
                ->available()
                ->with('branch:id,name')
                ->withCount(['deliveries as active_deliveries_count' => fn ($q) => $q
                    ->whereIn('status', DeliveryStatus::activeValues()),
                ])
                ->orderBy('full_name')
                ->get(),
            'branches' => $this->visibleBranches($user),
            'filters' => $request->only(['search', 'branch_id']),
        ]);
    }

    /**
     * Assign a parcel to a driver.
     */
    public function store(Request $request): RedirectResponse
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
            $this->deliveries->assign($parcel, $driver, $request->user(), $validated['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            "{$parcel->tracking_number} was assigned to {$driver->full_name}."
        );
    }

    // -----------------------------------------------------------------
    // Driver actions
    // -----------------------------------------------------------------

    public function accept(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->authorize('accept', $delivery);

        try {
            $this->deliveries->accept($delivery, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Delivery accepted. Collect the parcel and mark it in transit when you set off.');
    }

    public function reject(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->authorize('reject', $delivery);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'rejection_reason.required' => 'Please give a reason so the dispatcher can reassign this parcel.',
        ]);

        try {
            $this->deliveries->reject($delivery, $request->user(), $validated['rejection_reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Delivery declined. The dispatcher will reassign this parcel.');
    }

    public function markInTransit(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->authorize('update', $delivery);

        $validated = $request->validate([
            'location' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $this->deliveries->markInTransit($delivery, $request->user(), $validated['location'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Parcel marked as out for delivery.');
    }

    public function complete(Request $request, Delivery $delivery): RedirectResponse
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

            // Bonus: signature pad output and a doorstep photo.
            'signature' => ['nullable', 'string'],
            'proof_image' => [
                'nullable', 'image',
                'mimes:'.implode(',', config('courier.uploads.image_mimes')),
                'max:'.config('courier.uploads.max_image_kb'),
            ],
        ], [
            'received_by.required' => 'Record the name of the person who took delivery.',
        ]);

        try {
            $this->deliveries->complete($delivery, $request->user(), [
                ...$validated,
                'proof_image' => $request->file('proof_image'),
            ]);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('deliveries.show', $delivery)
            ->with('success', "Delivery completed. {$delivery->parcel->tracking_number} is marked as delivered.");
    }

    public function markFailed(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->authorize('markFailed', $delivery);

        $validated = $request->validate([
            'failure_reason' => ['required', 'string', 'min:5', 'max:255'],
            'location' => ['nullable', 'string', 'max:191'],
        ], [
            'failure_reason.required' => 'Please record why the delivery could not be completed.',
        ]);

        try {
            $this->deliveries->markFailed(
                $delivery,
                $request->user(),
                $validated['failure_reason'],
                $validated['location'] ?? null
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('deliveries.index')
            ->with('status', 'The failed attempt has been recorded.');
    }

    /**
     * Management pulls a job back from a driver.
     */
    public function cancelAssignment(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->authorize('reassign', $delivery);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        try {
            $this->deliveries->cancelAssignment($delivery, $request->user(), $validated['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'The assignment was cancelled and the parcel is back in the pool.');
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
