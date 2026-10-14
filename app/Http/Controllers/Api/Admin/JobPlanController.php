<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\JobPlanPrice;
use App\Services\AdminAuditor;
use App\Services\JobPlanCatalog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The price grid Post a Job ▸ Choose plan shows: per-posting price and MRP
 * for every tier × reach × posting count.
 *
 * Editable here rather than in config so a price change is not a deploy.
 * The app renders whatever comes back, and the server charges exactly these
 * numbers — there is no second copy to keep in step.
 */
class JobPlanController extends ApiController
{
    public function __construct(
        private readonly JobPlanCatalog $catalog,
        private readonly AdminAuditor $auditor,
    ) {}

    /** GET /admin/job-plans */
    public function index(): JsonResponse
    {
        return ApiResponse::data($this->payload());
    }

    /**
     * PUT /admin/job-plans — replaces the cells sent, in rupees as typed by
     * an operator. A cell not sent is left as it was.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.tier' => ['required', Rule::in(array_keys($this->catalog->tiers()))],
            'prices.*.reach' => ['required', Rule::in(array_keys($this->catalog->reaches()))],
            'prices.*.quantity' => ['required', 'integer', Rule::in($this->catalog->quantities())],
            'prices.*.price' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'prices.*.mrp' => ['nullable', 'numeric', 'min:0', 'max:1000000', 'gte:prices.*.price'],
        ], [
            'prices.*.mrp.gte' => 'The MRP must be at least the price.',
        ]);

        $changed = [];

        DB::transaction(function () use ($validated, &$changed) {
            foreach ($validated['prices'] as $cell) {
                $price = (int) round($cell['price'] * 100);
                $mrp = isset($cell['mrp']) && $cell['mrp'] > 0 ? (int) round($cell['mrp'] * 100) : null;

                $row = JobPlanPrice::firstOrNew([
                    'tier' => $cell['tier'],
                    'reach' => $cell['reach'],
                    'quantity' => (int) $cell['quantity'],
                ]);

                if ($row->exists && $row->price_paise === $price && $row->mrp_paise === $mrp) {
                    continue;
                }

                $changed["{$cell['tier']}/{$cell['reach']}/{$cell['quantity']}"] = [
                    'from' => $row->exists ? [$row->price_paise, $row->mrp_paise] : null,
                    'to' => [$price, $mrp],
                ];

                $row->fill(['price_paise' => $price, 'mrp_paise' => $mrp])->save();
            }
        });

        if ($changed !== []) {
            $this->auditor->log(
                action: 'job_plans.update',
                summary: 'Updated '.count($changed).' job-plan price(s)',
                subjectType: 'JobPlanPrice',
                subjectId: null,
                changes: $changed,
            );
        }

        return ApiResponse::data(
            $this->payload(),
            $changed === [] ? 'No prices changed.' : count($changed).' price(s) updated.',
        );
    }

    private function payload(): array
    {
        $catalog = $this->catalog->toApi();

        return [
            'tiers' => $catalog['tiers'],
            'reaches' => $catalog['reaches'],
            'quantities' => $catalog['quantities'],
            'max_quantity' => $catalog['max_quantity'],
            'prices' => $this->catalog->grid()
                ->map(fn (array $r) => [
                    'tier' => $r['tier'],
                    'reach' => $r['reach'],
                    'quantity' => $r['quantity'],
                    'price' => $r['price_paise'] / 100,
                    'mrp' => $r['mrp_paise'] === null ? null : $r['mrp_paise'] / 100,
                ])
                ->values()
                ->all(),
        ];
    }
}
