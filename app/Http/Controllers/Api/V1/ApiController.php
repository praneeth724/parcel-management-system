<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Base class for the v1 API.
 *
 * Gives every endpoint the same JSON envelope and the same page-size handling,
 * so clients can rely on one shape across the whole API.
 */
abstract class ApiController extends Controller
{
    /**
     * A successful response carrying data.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function success(
        mixed $data = null,
        ?string $message = null,
        int $status = Response::HTTP_OK,
        array $meta = [],
    ): JsonResponse {
        return response()->json(array_filter([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta ?: null,
        ], fn ($value) => $value !== null), $status);
    }

    /**
     * A newly created resource.
     */
    protected function created(mixed $data, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message, Response::HTTP_CREATED);
    }

    /**
     * A successful action with nothing to return.
     */
    protected function noContent(): Response
    {
        return response()->noContent();
    }

    /**
     * A failed action that is the caller's fault but not a validation error —
     * typically a business rule such as an illegal status transition.
     *
     * @param  array<string, mixed>  $errors
     */
    protected function error(
        string $message,
        int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
        array $errors = [],
    ): JsonResponse {
        return response()->json(array_filter([
            'success' => false,
            'message' => $message,
            'errors' => $errors ?: null,
        ], fn ($value) => $value !== null), $status);
    }

    /**
     * Page size from `?per_page=`, clamped to the configured maximum so a
     * client cannot ask for the entire table in one request.
     */
    protected function perPage(Request $request): int
    {
        $default = (int) config('courier.pagination.api');
        $max = (int) config('courier.pagination.api_max');

        $requested = (int) $request->integer('per_page', $default);

        return max(1, min($requested ?: $default, $max));
    }
}
