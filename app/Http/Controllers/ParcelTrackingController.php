<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TrackingStatus;
use App\Models\Parcel;
use App\Services\ParcelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Logs a warehouse or handling event onto a parcel's timeline.
 */
class ParcelTrackingController extends Controller
{
    public function __construct(private readonly ParcelService $parcels) {}

    public function store(Request $request, Parcel $parcel): RedirectResponse
    {
        $this->authorize('addTracking', $parcel);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(TrackingStatus::manualOptions()))],
            'location' => ['nullable', 'string', 'max:191'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], [
            'status.in' => 'Choose a valid tracking event.',
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
            // Thrown when the event would be an illegal status transition.
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Tracking event recorded.');
    }
}
