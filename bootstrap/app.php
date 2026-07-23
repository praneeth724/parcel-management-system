<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'active' => EnsureUserIsActive::class,
        ]);

        // Every authenticated web request re-checks that the account is still
        // enabled, so deactivation takes effect immediately.
        $middleware->web(append: [
            EnsureUserIsActive::class,
        ]);

        $middleware->api(append: [
            EnsureUserIsActive::class,
        ]);

        // Guests hitting a protected page are sent to our own login route.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->expectsJson()
            ? null
            : route('login'));

        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The API always answers with JSON, never an HTML error page.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY),

                $e instanceof AuthenticationException => response()->json([
                    'message' => 'Unauthenticated. Provide a valid bearer token.',
                ], Response::HTTP_UNAUTHORIZED),

                $e instanceof AuthorizationException,
                $e instanceof AccessDeniedHttpException => response()->json([
                    'message' => $e->getMessage() ?: 'This action is unauthorized.',
                ], Response::HTTP_FORBIDDEN),

                $e instanceof ModelNotFoundException => response()->json([
                    'message' => 'The requested resource was not found.',
                ], Response::HTTP_NOT_FOUND),

                $e instanceof NotFoundHttpException => response()->json([
                    'message' => 'The requested endpoint was not found.',
                ], Response::HTTP_NOT_FOUND),

                $e instanceof HttpExceptionInterface => response()->json([
                    'message' => $e->getMessage() ?: 'Request failed.',
                ], $e->getStatusCode()),

                default => null,
            };
        });
    })->create();
