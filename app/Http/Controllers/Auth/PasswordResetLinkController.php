<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        // Always report success. Telling an anonymous visitor whether an email
        // is registered would let them enumerate staff accounts.
        return match ($status) {
            Password::RESET_LINK_SENT, Password::INVALID_USER => back()->with(
                'status',
                'If that email address is registered, a password reset link is on its way.'
            ),
            Password::RESET_THROTTLED => back()->withInput()->withErrors([
                'email' => 'Please wait a moment before requesting another reset link.',
            ]),
            default => back()->withInput()->withErrors([
                'email' => 'We could not send a reset link right now. Please try again shortly.',
            ]),
        };
    }
}
