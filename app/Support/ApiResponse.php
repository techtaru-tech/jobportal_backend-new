<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

/**
 * The single response envelope every endpoint uses (§1.3, §1.5).
 */
class ApiResponse
{
    public static function data(mixed $data, ?string $message = null, int $status = 200): JsonResponse
    {
        $payload = ['data' => self::resolve($data)];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }

    /**
     * Wraps a paginator: items in `data`, pagination in a sibling `meta` — never
     * nested inside `data` (§1.5).
     */
    public static function paginated(LengthAwarePaginator $paginator, ?string $resourceClass = null, ?string $message = null): JsonResponse
    {
        $items = $paginator->getCollection();

        $data = $resourceClass
            ? $resourceClass::collection($items)->resolve()
            : self::resolve($items);

        $payload = [
            'data' => $data,
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
            ],
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload);
    }

    public static function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    public static function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $payload = ['message' => $message];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    private static function resolve(mixed $data): mixed
    {
        if ($data instanceof ResourceCollection || $data instanceof JsonResource) {
            return $data->resolve();
        }

        if ($data instanceof Collection) {
            return $data->values()->all();
        }

        return $data;
    }
}
