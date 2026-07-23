<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Route-level role gate: `->middleware('role:super_admin,branch_manager')`.
 *
 * This is a coarse filter that keeps whole sections of the app off-limits.
 * Per-record decisions ("may this manager edit that parcel?") belong in the
 * policies under app/Policies.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): SymfonyResponse
    {
        $user = Auth::user();

        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        $allowed = array_filter(array_map(
            static fn (string $role): ?UserRole => UserRole::tryFrom($role),
            $roles
        ));

        // A Super Admin passes every role gate by definition.
        if ($user->isSuperAdmin() || $user->hasRole(...$allowed)) {
            return $next($request);
        }

        throw new AccessDeniedHttpException(
            'Your role does not have access to this area.'
        );
    }
}
