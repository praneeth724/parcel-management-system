<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    // Invalidate "remember me" cookies issued before the reset,
                    // so a stolen cookie cannot outlive the password change.
                    'remember_token' => Str::random(60),
                ])->save();

                // Revoke any API tokens the old password may have created.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('login')
                ->with('status', 'Your password has been reset. You can sign in now.');
        }

        return back()->withInput($request->only('email'))->withErrors([
            'email' => match ($status) {
                Password::INVALID_TOKEN => 'This reset link is invalid or has expired. Please request a new one.',
                Password::INVALID_USER => 'We could not find an account with that email address.',
                default => 'We could not reset your password. Please request a new link.',
            },
        ]);
    }
}
