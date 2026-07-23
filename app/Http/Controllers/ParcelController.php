<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DeliveryPriority;
use App\Enums\ParcelStatus;
use App\Enums\ParcelType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TrackingStatus;
use App\Http\Requests\StoreParcelRequest;
use App\Http\Requests\UpdateParcelRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Parcel;
use App\Models\User;
use App\Services\ParcelService;
use App\Services\PricingService;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;

class ParcelController extends Controller
{
    public function __construct(
        private readonly ParcelService $parcels,
        private readonly PricingService $pricing,
        private readonly QrCodeService $qrCode,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Parcel::class);

        $user = $request->user();

        $parcels = Parcel::query()
            ->visibleTo($user)
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
            ->when(
                $request->boolean('trashed') && $user->can('view-trash'),
                fn ($q) => $q->onlyTrashed()
            )
            ->latest()
            ->paginate(config('courier.pagination.web'))
            ->withQueryString();

        return view('parcels.index', [
            'parcels' => $parcels,
            'statuses' => ParcelStatus::options(),
            'priorities' => DeliveryPriority::options(),
            'branches' => $this->visibleBranches($user),
            'drivers' => Driver::query()
                ->visibleTo($user)
                ->orderBy('full_name')
                ->get()
                ->pluck('label', 'id'),
            'filters' => $request->only([
                'search', 'status', 'priority', 'customer_id',
                'branch_id', 'driver_id', 'from', 'to', 'trashed',
            ]),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Parcel::class);

        $user = $request->user();

        return view('parcels.create', [
            'customers' => $this->customerOptions($user),
            'branches' => $this->visibleBranches($user),
            'parcelTypes' => ParcelType::options(),
            'paymentMethods' => PaymentMethod::options(),
            'priorities' => DeliveryPriority::options(),
            'pricing' => config('courier.pricing'),
            // Pre-select a customer when arriving from their profile page.
            'selectedCustomer' => $request->integer('customer_id') ?: null,
        ]);
    }

    public function store(StoreParcelRequest $request): RedirectResponse
    {
        $parcel = $this->parcels->create(
            data: $request->parcelData(),
            actor: $request->user(),
            images: $request->file('images', []),
        );

        return redirect()
            ->route('parcels.show', $parcel)
            ->with('success', "Parcel booked. Tracking number: {$parcel->tracking_number}");
    }

    public function show(Request $request, Parcel $parcel): View
    {
        $this->authorize('view', $parcel);

        $parcel->load([
            'customer',
            'branch:id,name,code,city,contact_number',
            'creator:id,name',
            'images.uploader:id,name',
            'trackings.updatedBy:id,name',
            'deliveries.driver:id,full_name,driver_code,phone,vehicle_number',
            'deliveries.assignedBy:id,name',
        ]);

        return view('parcels.show', [
            'parcel' => $parcel,
            'qrSvg' => $this->qrCode->svg($parcel, 200),
            'trackingOptions' => TrackingStatus::manualOptions(),
            'maxImages' => (int) config('courier.uploads.max_parcel_images'),

            // Drivers this parcel could be handed to right now.
            'assignableDrivers' => $request->user()->can('assignDriver', $parcel)
                ? Driver::query()
                    ->ofBranch($parcel->branch_id)
                    ->available()
                    ->orderBy('full_name')
                    ->get()
                : collect(),
        ]);
    }

    public function edit(Request $request, Parcel $parcel): View
    {
        $this->authorize('update', $parcel);

        return view('parcels.edit', [
            'parcel' => $parcel->load('customer:id,full_name,customer_code,city'),
            'parcelTypes' => ParcelType::options(),
            'paymentMethods' => PaymentMethod::options(),
            'paymentStatuses' => PaymentStatus::options(),
            'priorities' => DeliveryPriority::options(),
        ]);
    }

    public function update(UpdateParcelRequest $request, Parcel $parcel): RedirectResponse
    {
        try {
            $this->parcels->update($parcel, $request->validated(), $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('parcels.show', $parcel)
            ->with('success', "Parcel {$parcel->tracking_number} was updated.");
    }

    public function destroy(Parcel $parcel): RedirectResponse
    {
        $this->authorize('delete', $parcel);

        $parcel->delete();

        return redirect()
            ->route('parcels.index')
            ->with('success', "Parcel {$parcel->tracking_number} was archived.");
    }

    public function cancel(Request $request, Parcel $parcel): RedirectResponse
    {
        $this->authorize('cancel', $parcel);

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'cancellation_reason.required' => 'Please say why this parcel is being cancelled.',
        ]);

        try {
            $this->parcels->cancel($parcel, $request->user(), $validated['cancellation_reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Parcel {$parcel->tracking_number} was cancelled.");
    }

    /**
     * Printable shipping label (HTML, sized for a 100 × 150 mm thermal label).
     */
    public function label(Parcel $parcel): View
    {
        $this->authorize('printLabel', $parcel);

        return view('parcels.label', [
            'parcel' => $parcel->load('customer', 'branch'),
            'qrSvg' => $this->qrCode->svg($parcel, 260),
        ]);
    }

    /**
     * The same label as a downloadable PDF.
     */
    public function labelPdf(Parcel $parcel): Response
    {
        $this->authorize('printLabel', $parcel);

        $pdf = Pdf::loadView('parcels.label-pdf', [
            'parcel' => $parcel->load('customer', 'branch'),
            'qrSvg' => $this->qrCode->svg($parcel, 260),
        ])->setPaper([0, 0, 283.46, 425.20]); // 100 × 150 mm in points

        return $pdf->download("shipping-label-{$parcel->tracking_number}.pdf");
    }

    /**
     * Live delivery-charge quote for the booking form.
     */
    public function quote(Request $request): JsonResponse
    {
        $this->authorize('create', Parcel::class);

        $validated = $request->validate([
            'weight' => ['required', 'numeric', 'gt:0'],
            'priority' => ['required', 'string'],
            'parcel_type' => ['required', 'string'],
            'origin_city' => ['nullable', 'string', 'max:100'],
            'destination_city' => ['nullable', 'string', 'max:100'],
            'length_cm' => ['nullable', 'numeric', 'gt:0'],
            'width_cm' => ['nullable', 'numeric', 'gt:0'],
            'height_cm' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $breakdown = $this->pricing->breakdown(
            weightKg: (float) $validated['weight'],
            priority: DeliveryPriority::from($validated['priority']),
            type: ParcelType::from($validated['parcel_type']),
            originCity: $validated['origin_city'] ?? null,
            destinationCity: $validated['destination_city'] ?? null,
            lengthCm: isset($validated['length_cm']) ? (float) $validated['length_cm'] : null,
            widthCm: isset($validated['width_cm']) ? (float) $validated['width_cm'] : null,
            heightCm: isset($validated['height_cm']) ? (float) $validated['height_cm'] : null,
        );

        return response()->json([
            'data' => [
                ...$breakdown,
                'formatted_total' => $this->pricing->format($breakdown['total']),
            ],
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function customerOptions(User $user): \Illuminate\Support\Collection
    {
        return Customer::query()
            ->visibleTo($user)
            ->where('status', \App\Enums\CustomerStatus::Active)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'customer_code', 'mobile', 'city', 'address'])
            ->mapWithKeys(fn (Customer $c): array => [
                $c->id => "{$c->full_name} — {$c->customer_code} ({$c->mobile})",
            ]);
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
