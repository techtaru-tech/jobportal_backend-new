<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Display;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function perPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', config('options.pagination.per_page'));

        return max(1, min($perPage, config('options.pagination.max_per_page')));
    }

    /**
     * Reads a filter that the app may send either repeated (`?shift[]=Day`) or
     * comma-separated (`?shift=Day,Night`).
     *
     * @return array<int, string>
     */
    protected function listParam(Request $request, string $key): array
    {
        $value = $request->input($key);

        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return Display::cleanList($value);
    }
}
