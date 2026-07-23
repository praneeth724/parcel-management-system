<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Parcel;
use App\Models\ParcelImage;
use App\Services\ParcelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ParcelImageController extends Controller
{
    public function __construct(private readonly ParcelService $parcels) {}

    public function store(Request $request, Parcel $parcel): RedirectResponse
    {
        $this->authorize('uploadImages', $parcel);

        $maxImages = (int) config('courier.uploads.max_parcel_images');

        $request->validate([
            'images' => ['required', 'array', "max:{$maxImages}"],
            'images.*' => [
                'image',
                'mimes:'.implode(',', config('courier.uploads.image_mimes')),
                'max:'.config('courier.uploads.max_image_kb'),
            ],
        ], [
            'images.required' => 'Please choose at least one image to upload.',
            'images.max' => "You can upload at most {$maxImages} images at a time.",
        ]);

        try {
            $stored = $this->parcels->attachImages(
                $parcel,
                $request->file('images'),
                $request->user()
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $count = count($stored);

        return back()->with('success', "{$count} ".str('image')->plural($count).' uploaded.');
    }

    public function destroy(ParcelImage $image): RedirectResponse
    {
        // Deleting a photo is an edit of the parcel it belongs to.
        $this->authorize('uploadImages', $image->parcel);

        $this->parcels->deleteImage($image);

        return back()->with('success', 'Image removed.');
    }
}
