<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Ends the session of a user who was deactivated after they signed in.
 *
 * Login already refuses deactivated accounts; this catches the case where an
 * administrator switches someone off while they are mid-session.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            $message = 'Your account has been deactivated. Please contact your administrator.';

            if ($request->expectsJson()) {
                // API tokens are revoked outright — there is no session to end.
                $user->currentAccessToken()?->delete();

                return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
