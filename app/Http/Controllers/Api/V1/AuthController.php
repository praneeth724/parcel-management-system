<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Token authentication for the API, backed by Laravel Sanctum.
 */
class AuthController extends ApiController
{
    /**
     * Exchange credentials for a personal access token.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $throttleKey = Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, maxAttempts: 5)) {
            return $this->error(
                'Too many login attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        // `is_active` is part of the credential check so a deactivated account
        // can never receive a token.
        if (! Auth::validate([...$request->only('email', 'password'), 'is_active' => true])) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        RateLimiter::clear($throttleKey);

        $user = Auth::getLastAttempted();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        // Token abilities mirror the user's role, so a leaked driver token
        // cannot be replayed against management endpoints.
        $token = $user->createToken(
            $credentials['device_name'] ?? 'api-token',
            [$user->role->value]
        );

        return $this->success([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('branch', 'driver')),
        ], 'Signed in successfully.');
    }

    /**
     * The authenticated user's own profile.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success(
            new UserResource($request->user()->load('branch', 'driver'))
        );
    }

    /**
     * Revoke the token used for this request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Signed out. This token has been revoked.');
    }

    /**
     * Revoke every token belonging to the user.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $count = $request->user()->tokens()->count();
        $request->user()->tokens()->delete();

        return $this->success(null, "Signed out of {$count} ".str('device')->plural($count).'.');
    }

    /**
     * Trigger the password reset email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        // The response never reveals whether the address is registered.
        return match ($status) {
            Password::RESET_THROTTLED => $this->error(
                'Please wait before requesting another reset link.',
                Response::HTTP_TOO_MANY_REQUESTS
            ),
            default => $this->success(
                null,
                'If that email address is registered, a password reset link has been sent.'
            ),
        };
    }

    /**
     * Change the password of the signed-in user, revoking other tokens.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();

        $user->update(['password' => $request->string('password')->toString()]);

        // Every other token dies with the old password; this one survives so
        // the caller is not logged out mid-session.
        $user->tokens()->whereKeyNot($currentToken->id)->delete();

        return $this->success(null, 'Password updated. All other tokens have been revoked.');
    }
}
