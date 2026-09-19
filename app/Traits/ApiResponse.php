<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponse
{
    protected function successResponse(mixed $data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message ?? 'Success',
            'data' => $data,
        ], $code);
    }

    protected function createdResponse(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->successResponse($data, $message ?? 'Created successfully', 201);
    }

    protected function noContentResponse(?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message ?? 'Deleted successfully',
        ], 204);
    }

    protected function errorResponse(
        ?string $message = null,
        int $code = 400,
        mixed $errors = null,
        ?string $errorCode = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message ?? 'Error',
        ];

        if ($errorCode !== null) {
            $response['error_code'] = $errorCode;
        }

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    protected function unauthorizedResponse(?string $message = null): JsonResponse
    {
        return $this->errorResponse($message ?? 'Unauthorized', 401, errorCode: 'UNAUTHENTICATED');
    }

    protected function forbiddenResponse(?string $message = null): JsonResponse
    {
        return $this->errorResponse($message ?? 'Forbidden', 403, errorCode: 'FORBIDDEN');
    }

    protected function notFoundResponse(?string $message = null): JsonResponse
    {
        return $this->errorResponse($message ?? 'Resource not found', 404, errorCode: 'NOT_FOUND');
    }

    protected function validationErrorResponse(mixed $errors, ?string $message = null): JsonResponse
    {
        return $this->errorResponse($message ?? 'Validation failed', 422, $errors, 'VALIDATION_ERROR');
    }

    protected function businessErrorResponse(string $errorCode, ?string $message = null, int $code = 422): JsonResponse
    {
        return $this->errorResponse($message, $code, errorCode: $errorCode);
    }

    protected function paginatedResponse(LengthAwarePaginator $paginator, ?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message ?? 'Success',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    protected function resourceResponse(JsonResource $resource, ?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message ?? 'Success',
            'data' => $resource,
        ]);
    }

    protected function collectionResponse(ResourceCollection $collection, ?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message ?? 'Success',
            'data' => $collection,
        ]);
    }
}
