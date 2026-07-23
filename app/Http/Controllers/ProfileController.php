<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Rules\SriLankanMobile;
use App\Services\FileUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The signed-in user's own profile. Role and branch are deliberately not
 * editable here — only an administrator may change those, via UserController.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly FileUploadService $uploads) {}

    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user()->load('branch', 'driver'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => [
                'required', 'email:rfc', 'max:191',
                Rule::unique('users', 'email')->ignore($user->id)->withoutTrashed(),
            ],
            'phone' => ['nullable', 'string', new SriLankanMobile],
            'avatar' => [
                'nullable', 'image',
                'mimes:'.implode(',', config('courier.uploads.image_mimes')),
                'max:'.config('courier.uploads.max_image_kb'),
            ],
        ]);

        $validated['phone'] = SriLankanMobile::normalize($validated['phone'] ?? null);

        // Changing the email address invalidates the previous verification.
        $emailChanged = $validated['email'] !== $user->email;

        if ($request->hasFile('avatar')) {
            $validated['avatar_path'] = $this->uploads->replace(
                $request->file('avatar'),
                'user_avatars',
                $user->avatar_path
            );
        }

        unset($validated['avatar']);

        $user->fill($validated);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return back()->with(
                'success',
                'Profile updated. Please check your new email address for a verification link.'
            );
        }

        return back()->with('success', 'Your profile has been updated.');
    }
}
