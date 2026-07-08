<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Standardizes all API responses to the format:
 *
 * Success: { "success": true,  "message": "...", "data": {} }
 * Error:   { "success": false, "message": "...", "errors": [] }
 *
 * Requirements: 8.1, 8.2, 8.3
 */
trait ApiResponseTrait
{
    /**
     * Return a successful JSON response.
     */
    protected function success(string $message, mixed $data = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Return a created (201) JSON response.
     */
    protected function created(string $message, mixed $data = []): JsonResponse
    {
        return $this->success($message, $data, 201);
    }

    /**
     * Return an error JSON response.
     */
    protected function error(string $message, array $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }

    /**
     * Return a 422 validation error response.
     */
    protected function validationError(string $message, array $errors = []): JsonResponse
    {
        return $this->error($message, $errors, 422);
    }

    /**
     * Return a 401 unauthenticated response.
     */
    protected function unauthorized(string $message = 'Não autenticado.'): JsonResponse
    {
        return $this->error($message, [], 401);
    }

    /**
     * Return a 403 forbidden response.
     */
    protected function forbidden(string $message = 'Acesso não autorizado.'): JsonResponse
    {
        return $this->error($message, [], 403);
    }

    /**
     * Return a 404 not found response.
     */
    protected function notFound(string $message = 'Recurso não encontrado.'): JsonResponse
    {
        return $this->error($message, [], 404);
    }

    /**
     * Return a paginated response preserving the standard envelope.
     */
    protected function paginated(string $message, \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ], 200);
    }
}
