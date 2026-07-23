<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Parcel;
use App\Services\QrCodeService;
use App\Services\TrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public "where is my parcel?" page.
 *
 * No authentication: a customer with a tracking number can look it up. What is
 * shown is deliberately limited — receiver address and staff names are held
 * back, because anyone who guesses a tracking number would otherwise see them.
 */
class TrackingController extends Controller
{
    public function __construct(
        private readonly TrackingService $tracking,
        private readonly QrCodeService $qrCode,
    ) {}

    public function index(): View
    {
        return view('track.index');
    }

    /**
     * Handle the search box: normalise the input and redirect to the parcel.
     */
    public function lookup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tracking_number' => ['required', 'string', 'max:40'],
        ], [
            'tracking_number.required' => 'Please enter a tracking number.',
        ]);

        $trackingNumber = strtoupper(trim($validated['tracking_number']));

        $exists = Parcel::query()->where('tracking_number', $trackingNumber)->exists();

        if (! $exists) {
            return redirect()
                ->route('track.index')
                ->withInput()
                ->withErrors([
                    'tracking_number' => "We could not find a shipment with tracking number {$trackingNumber}.",
                ]);
        }

        return redirect()->route('track.show', $trackingNumber);
    }

    public function show(Parcel $parcel): View
    {
        $parcel->load([
            'trackings.updatedBy:id,name',
            'customer:id,full_name,company_name,city',
            'branch:id,name,city',
        ]);

        return view('track.show', [
            'parcel' => $parcel,
            'timeline' => $this->tracking->publicTimeline($parcel),
            'qrSvg' => $this->qrCode->svg($parcel, 180),
        ]);
    }
}
