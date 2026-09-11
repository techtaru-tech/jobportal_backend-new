<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\OptionItem;
use App\Services\AdminAuditor;
use App\Services\OptionListService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The filter sheet the app draws, as data.
 *
 * Reference data already let an admin change a filter's *values* — add a city
 * and it appears as a chip. What it could not do was add a filter at all: the
 * groups were declared in `config/options.php`, so a new one meant a code
 * change and a deploy.
 *
 * A group is not a value, which is why this is not the generic option-list
 * editor. It carries behaviour: the query parameter the app sends, the list
 * its chips come from, the column it matches, and whether it matches as a set
 * or a threshold. That rides in `option_items.meta`.
 *
 * Two things are deliberately constrained rather than free text:
 *
 *  - **[OptionListService::FILTER_COLUMNS]** — a filter may only match a
 *    column on that list. Without it, whoever can edit reference data could
 *    point a filter at any column on `job_postings` and read it back through
 *    the public `GET /jobs` one value at a time.
 *  - **[OptionListService::EDITABLE_LISTS]** — the chips must come from a
 *    list that exists and that an admin can maintain, or the group would be
 *    served with nothing under it.
 *
 * Writes go through [OptionListService::flush] because `GET /config/options`
 * is cached for an hour, and an admin who adds a filter expects to see it.
 */
class JobFilterController extends ApiController
{
    public function __construct(
        private readonly OptionListService $options,
        private readonly AdminAuditor $auditor,
    ) {}

    /** GET /admin/job-filters */
    public function index(): JsonResponse
    {
        $rows = OptionItem::forList(OptionListService::FILTER_LIST)->ordered()->get();

        return ApiResponse::data([
            'groups' => $this->options->jobFilterDeclarations(),

            // False while the groups still come from the config file. The
            // panel says so, because "delete this one" behaves differently
            // before and after the first edit materialises the set.
            'is_overridden' => $rows->isNotEmpty(),

            // The choices the form may offer. Served rather than duplicated
            // in the panel, so the whitelist has exactly one definition.
            'columns' => OptionListService::FILTER_COLUMNS,
            'types' => OptionListService::FILTER_TYPES,
            'lists' => OptionListService::EDITABLE_LISTS,
        ]);
    }

    /** POST /admin/job-filters */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        $this->materialise();

        if ($this->keyTaken($validated['key'])) {
            return ApiResponse::error("A filter with the key “{$validated['key']}” already exists.", 422);
        }

        $max = (int) OptionItem::forList(OptionListService::FILTER_LIST)->max('sort_order');

        $item = OptionItem::create([
            'list_key' => OptionListService::FILTER_LIST,
            'value' => $validated['label'],
            'sort_order' => $max + 10,
            'is_active' => true,
            'meta' => $this->meta($validated),
        ]);

        $this->options->flush();

        $this->auditor->log(
            action: 'job_filter.add',
            summary: "Added the “{$validated['label']}” job filter",
            subjectType: 'JobFilter',
            subjectId: (string) $item->id,
            changes: ['label' => ['from' => null, 'to' => $validated['label']]],
        );

        return ApiResponse::data($this->options->jobFilterDeclarations(), 'Filter added.', 201);
    }

    /** PATCH /admin/job-filters/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $item = $this->find($id);
        $validated = $this->validated($request, $id);

        $before = ['label' => $item->value, ...($item->meta ?? [])];

        if ($this->keyTaken($validated['key'], $id)) {
            return ApiResponse::error("A filter with the key “{$validated['key']}” already exists.", 422);
        }

        $item->fill([
            'value' => $validated['label'],
            'meta' => $this->meta($validated),
            'is_active' => $request->boolean('is_active', $item->is_active),
        ])->save();

        $this->options->flush();

        $after = ['label' => $item->value, ...($item->meta ?? [])];
        $changes = AdminAuditor::diff($before, $after);

        if ($changes !== []) {
            $this->auditor->log(
                action: 'job_filter.update',
                summary: "Updated the “{$item->value}” job filter",
                subjectType: 'JobFilter',
                subjectId: (string) $item->id,
                changes: $changes,
            );
        }

        return ApiResponse::data($this->options->jobFilterDeclarations(), 'Filter updated.');
    }

    /** DELETE /admin/job-filters/{id} */
    public function destroy(int $id): JsonResponse
    {
        $item = $this->find($id);
        $label = $item->value;

        // Unlike a value list, an empty set of filters is a legitimate
        // choice — "no filter sheet" — so there is no last-row guard here.
        // The override row count is what keeps it empty rather than falling
        // back to the config file.
        $item->delete();

        $this->options->flush();

        $this->auditor->log(
            action: 'job_filter.remove',
            summary: "Removed the “{$label}” job filter",
            subjectType: 'JobFilter',
            subjectId: (string) $id,
            changes: ['label' => ['from' => $label, 'to' => null]],
        );

        return ApiResponse::data($this->options->jobFilterDeclarations(), 'Filter removed.');
    }

    /** PUT /admin/job-filters/reorder — the order the sheet renders them in. */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $this->materialise();

        foreach (array_values($validated['ids']) as $index => $id) {
            OptionItem::forList(OptionListService::FILTER_LIST)
                ->whereKey($id)
                ->update(['sort_order' => ($index + 1) * 10]);
        }

        $this->options->flush();

        return ApiResponse::data($this->options->jobFilterDeclarations(), 'Order saved.');
    }

    /** DELETE /admin/job-filters/override — back to the shipped filters. */
    public function resetToDefault(): JsonResponse
    {
        OptionItem::forList(OptionListService::FILTER_LIST)->delete();

        $this->options->flush();

        $this->auditor->log(
            action: 'job_filter.reset',
            summary: 'Reset the job filters to the shipped defaults',
            subjectType: 'JobFilter',
            subjectId: null,
        );

        return ApiResponse::data($this->options->jobFilterDeclarations(), 'Reverted to defaults.');
    }

    /** @return array<string, string> */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/'],
            'label' => ['required', 'string', 'max:60'],
            'param' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/'],
            'list' => ['required', 'string', Rule::in(OptionListService::EDITABLE_LISTS)],
            'column' => ['required', 'string', Rule::in(OptionListService::FILTER_COLUMNS)],
            'type' => ['required', 'string', Rule::in(OptionListService::FILTER_TYPES)],
        ], [
            'key.regex' => 'The key may use lowercase letters, numbers and underscores only.',
            'param.regex' => 'The parameter may use lowercase letters, numbers and underscores only.',
            'column.in' => 'That column cannot be filtered on.',
        ]);
    }

    /** @param  array<string, string>  $validated */
    private function meta(array $validated): array
    {
        return [
            'key' => $validated['key'],
            'param' => $validated['param'],
            'list' => $validated['list'],
            'column' => $validated['column'],
            'type' => $validated['type'],
        ];
    }

    /**
     * Copies the config-file filters into `option_items` before the first
     * write, for the same reason the value lists do it: any row at all counts
     * as an override, so adding one filter to an un-edited set would leave
     * that filter as the *only* one and silently delete the shipped five.
     */
    private function materialise(): void
    {
        if (OptionItem::forList(OptionListService::FILTER_LIST)->exists()) {
            return;
        }

        $now = now();
        $shipped = $this->options->jobFilterDeclarations();

        if ($shipped === []) {
            return;
        }

        OptionItem::insert(
            array_map(fn (array $group, int $index) => [
                'list_key' => OptionListService::FILTER_LIST,
                'value' => $group['label'],
                'sort_order' => ($index + 1) * 10,
                'is_active' => true,
                'group_key' => null,
                'meta' => json_encode([
                    'key' => $group['key'],
                    'param' => $group['param'],
                    'list' => $group['list'],
                    'column' => $group['column'],
                    'type' => $group['type'],
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ], $shipped, array_keys($shipped)),
        );
    }

    private function keyTaken(string $key, ?int $ignoreId = null): bool
    {
        return OptionItem::forList(OptionListService::FILTER_LIST)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->get()
            ->contains(fn (OptionItem $item) => ($item->meta['key'] ?? null) === $key);
    }

    private function find(int $id): OptionItem
    {
        // Materialised first: an admin editing a shipped filter is editing
        // something that has no row yet, and the id they were given came from
        // the config-file declaration (which has none).
        $this->materialise();

        $item = OptionItem::forList(OptionListService::FILTER_LIST)->find($id);

        if (! $item) {
            throw new NotFoundHttpException('That filter was not found.');
        }

        return $item;
    }
}
