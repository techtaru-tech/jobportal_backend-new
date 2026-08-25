<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\CandidateProfile;
use App\Models\JobPosting;
use App\Models\OptionItem;
use App\Services\AdminAuditor;
use App\Services\OptionListService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The reference data behind every picker and chip row in the app — skills,
 * qualifications, cities, categories, job types, shifts and the rest.
 *
 * These lists reach further than they look. `skills` is what the Smart Apply
 * form offers a nurse; `categories` drives the home screen's chip row AND the
 * `/jobs/categories` endpoint; `job_types` and `shifts` are validated with
 * `Rule::in` when a job is posted. So a careless edit here is felt on every
 * installed device at once, which is why this controller reports **usage
 * counts** before it lets anything be removed, and audits every change.
 *
 * They live in `option_items` with `config/options.php` as the per-list
 * fallback — see [OptionListService]. Consequences worth knowing:
 *
 *  - A list nobody has edited has no rows and reads from the config file.
 *  - The first edit materialises the config values first, so "add one skill"
 *    never means "delete the other fourteen".
 *  - Values are **suggestions, not whitelists** for most lists: the API accepts
 *    freeform input, so removing a value stops it being offered without
 *    invalidating the profiles and postings already using it.
 */
class OptionListController extends ApiController
{
    public function __construct(
        private readonly OptionListService $options,
        private readonly AdminAuditor $auditor,
    ) {}

    /** GET /admin/option-lists */
    public function index(): JsonResponse
    {
        $overridden = $this->options->overriddenLists();
        $resolved = $this->options->all();

        return ApiResponse::data([
            'lists' => array_map(fn (string $key) => [
                'key' => $key,
                'label' => $this->label($key),
                'count' => count($resolved[$key] ?? []),
                'is_overridden' => in_array($key, $overridden, true),
                'config_count' => count($this->options->configList($key)),
                'validated' => in_array($key, ['job_types', 'shifts'], true),
                'preview' => array_slice($resolved[$key] ?? [], 0, 6),
            ], OptionListService::EDITABLE_LISTS),

            // Named so the panel can explain the absence rather than leaving
            // an admin hunting for a list that is deliberately not here.
            'locked' => [
                [
                    'keys' => ['skill_levels', 'language_levels', 'organisation_industries', 'organisation_sizes'],
                    'reason' => 'Backed by closed enums the API validates against — a value added here would be offered and then rejected on save.',
                ],
                [
                    'keys' => ['genders'],
                    'reason' => 'A closed list duplicated in a validation rule; editing one half only would break profile saves.',
                ],
                [
                    'keys' => ['passing_years'],
                    'reason' => 'Generated from the clock, not stored — the window is a config setting.',
                ],
            ],
        ]);
    }

    /** GET /admin/option-lists/{list} */
    public function show(string $list): JsonResponse
    {
        $this->assertEditable($list);

        $items = OptionItem::forList($list)->ordered()->get();

        return ApiResponse::data([
            'key' => $list,
            'label' => $this->label($list),
            'is_overridden' => $items->isNotEmpty(),
            'validated' => in_array($list, ['job_types', 'shifts'], true),

            // Config values shown alongside, so an admin can see what
            // reverting would restore.
            'config_values' => $this->options->configList($list),

            'items' => $items->map(fn (OptionItem $item) => [
                'id' => $item->id,
                'value' => $item->value,
                'sort_order' => $item->sort_order,
                'is_active' => $item->is_active,
                'usage' => $this->usage($list, $item->value),
            ])->all(),

            // The list as the app would receive it right now.
            'resolved' => $this->options->list($list),
        ]);
    }

    /** POST /admin/option-lists/{list}/items */
    public function store(Request $request, string $list): JsonResponse
    {
        $this->assertEditable($list);

        $validated = $request->validate([
            'value' => ['required', 'string', 'max:120'],
        ]);

        $value = trim($validated['value']);

        // Materialise before inserting — see OptionListService::materialize.
        $this->options->materialize($list);

        $exists = OptionItem::forList($list)
            ->whereNull('group_key')
            ->where('value', $value)
            ->exists();

        if ($exists) {
            return ApiResponse::error("“{$value}” is already in this list.", 422);
        }

        $max = (int) OptionItem::forList($list)->max('sort_order');

        $item = OptionItem::create([
            'list_key' => $list,
            'value' => $value,
            'sort_order' => $max + 10,
            'is_active' => true,
        ]);

        $this->options->flush();

        $this->auditor->log(
            action: 'option_list.add',
            summary: "Added “{$value}” to {$this->label($list)}",
            subjectType: 'OptionList',
            subjectId: $list,
            changes: ['value' => ['from' => null, 'to' => $value]],
        );

        return ApiResponse::data(
            ['id' => $item->id, 'value' => $item->value],
            'Added.',
            201,
        );
    }

    /** PATCH /admin/option-lists/{list}/items/{itemId} */
    public function update(Request $request, string $list, int $itemId): JsonResponse
    {
        $this->assertEditable($list);
        $item = $this->findItem($list, $itemId);

        $validated = $request->validate([
            'value' => ['sometimes', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $before = ['value' => $item->value, 'is_active' => $item->is_active];

        if (array_key_exists('value', $validated)) {
            $item->value = trim($validated['value']);
        }
        if (array_key_exists('is_active', $validated)) {
            $item->is_active = $validated['is_active'];
        }

        $item->save();
        $this->options->flush();

        $after = ['value' => $item->value, 'is_active' => $item->is_active];
        $changes = AdminAuditor::diff($before, $after);

        if ($changes !== []) {
            $this->auditor->log(
                action: 'option_list.update',
                summary: "Updated “{$before['value']}” in {$this->label($list)}",
                subjectType: 'OptionList',
                subjectId: $list,
                changes: $changes,
            );
        }

        return ApiResponse::data(
            ['id' => $item->id, 'value' => $item->value, 'is_active' => $item->is_active],
            'Saved.',
        );
    }

    /**
     * DELETE /admin/option-lists/{list}/items/{itemId}
     *
     * Hard delete. Safe because these lists are suggestion sets rather than
     * foreign keys — profiles and postings store the string, so removing a
     * value stops it being *offered* without orphaning anything.
     *
     * Refuses to remove the **last** row, because zero rows is ambiguous:
     * "this list is overridden and empty" and "nobody has edited this list"
     * look identical in the table, and [OptionListService] has to read the
     * second one as "fall back to the config file". Deleting the last value
     * would therefore restore all the shipped ones — the opposite of what was
     * asked. Both unambiguous intents already have a path, so the error points
     * at them rather than guessing.
     */
    public function destroy(string $list, int $itemId): JsonResponse
    {
        $this->assertEditable($list);
        $item = $this->findItem($list, $itemId);

        if (OptionItem::forList($list)->count() === 1) {
            return ApiResponse::error(
                'This is the last value in the list. Turn it off to leave the list empty, or reset the list to restore the shipped defaults.',
                422,
            );
        }

        $usage = $this->usage($list, $item->value);
        $value = $item->value;

        $item->delete();
        $this->options->flush();

        $this->auditor->log(
            action: 'option_list.remove',
            summary: "Removed “{$value}” from {$this->label($list)}"
                .($usage > 0 ? " (still used by {$usage} record(s))" : ''),
            subjectType: 'OptionList',
            subjectId: $list,
            changes: ['value' => ['from' => $value, 'to' => null], 'usage_at_removal' => ['from' => null, 'to' => $usage]],
        );

        return ApiResponse::message('Removed.');
    }

    /**
     * PUT /admin/option-lists/{list}/reorder
     *
     * Order is editorial — the app renders these in array order and common
     * answers belong first — so it has to be settable.
     */
    public function reorder(Request $request, string $list): JsonResponse
    {
        $this->assertEditable($list);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $items = OptionItem::forList($list)->whereIn('id', $validated['ids'])->get()->keyBy('id');

        $position = 10;
        foreach ($validated['ids'] as $id) {
            $item = $items->get($id);
            if ($item === null) {
                continue;
            }
            $item->forceFill(['sort_order' => $position])->save();
            $position += 10;
        }

        $this->options->flush();

        $this->auditor->log(
            action: 'option_list.reorder',
            summary: "Reordered {$this->label($list)}",
            subjectType: 'OptionList',
            subjectId: $list,
        );

        return ApiResponse::data(['resolved' => $this->options->list($list)], 'Order saved.');
    }

    /**
     * DELETE /admin/option-lists/{list}/override
     *
     * Drops every row for this list, handing it back to `config/options.php`.
     * The escape hatch for an edit that went wrong — reverting to the shipped
     * defaults should not require knowing what they were.
     */
    public function resetToDefault(string $list): JsonResponse
    {
        $this->assertEditable($list);

        $removed = OptionItem::forList($list)->count();
        OptionItem::forList($list)->delete();
        $this->options->flush();

        $this->auditor->log(
            action: 'option_list.reset',
            summary: "Reset {$this->label($list)} to the shipped defaults ({$removed} row(s) dropped)",
            subjectType: 'OptionList',
            subjectId: $list,
            changes: ['rows_removed' => ['from' => $removed, 'to' => 0]],
        );

        return ApiResponse::data(
            ['resolved' => $this->options->list($list)],
            'Reverted to defaults.',
        );
    }

    /**
     * How many existing records already use a value.
     *
     * The point is to make removal an informed decision: taking "Nurse" out of
     * `categories` while 40 postings carry that role does not break them, but
     * it does mean nobody can filter for them any more.
     */
    private function usage(string $list, string $value): int
    {
        return match ($list) {
            'categories' => JobPosting::where('role', $value)->count(),
            'cities' => JobPosting::where('city', $value)->count()
                + CandidateProfile::where('home_city', $value)->count(),
            'job_types' => JobPosting::where('type', $value)->count(),
            'shifts' => JobPosting::where('shift', $value)->count(),
            'qualifications' => CandidateProfile::where('qualification', $value)->count(),
            'experience_bands' => CandidateProfile::where('experience', $value)->count()
                + JobPosting::where('experience', $value)->count(),

            // The JSON-column lists (skills, certifications, languages,
            // specializations) would need a JSON search per row to count
            // exactly; -1 means "not counted" so the panel can say so rather
            // than imply a confident zero.
            default => -1,
        };
    }

    private function label(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }

    private function assertEditable(string $list): void
    {
        if (! in_array($list, OptionListService::EDITABLE_LISTS, true)) {
            throw new NotFoundHttpException('That option list is not editable.');
        }
    }

    private function findItem(string $list, int $itemId): OptionItem
    {
        $item = OptionItem::forList($list)->find($itemId);

        if (! $item) {
            throw new NotFoundHttpException('That option was not found.');
        }

        return $item;
    }
}
