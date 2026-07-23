<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Change password for the signed-in user.
 */
class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'password' => $request->string('password')->toString(),
        ]);

        // Keep this session signed in but invalidate every other one, so a
        // password change actually evicts an intruder.
        Auth::logoutOtherDevices($request->string('password')->toString());
        $request->session()->regenerate();

        $user->tokens()->delete();

        return back()->with('success', 'Your password has been updated. Other devices have been signed out.');
    }
}
