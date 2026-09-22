<?php

namespace App\Services;

use App\Models\OptionItem;
use Illuminate\Support\Facades\Cache;

/**
 * The single reader for every admin-editable reference list.
 *
 * `config/options.php` used to be the source of truth for the option lists
 * behind every picker in the app. It still is — until an admin overrides a
 * given list, at which point `option_items` takes over **for that list only**.
 *
 * That per-list granularity is the whole design:
 *
 *  - An empty `option_items` table behaves exactly like the old code path, so
 *    installing this feature changes nothing until somebody uses it.
 *  - Overriding `skills` does not silently blank `cities`.
 *  - A list an admin empties on purpose stays empty — [overriddenLists] records
 *    the intent separately from the row count, so "no rows" and "no override"
 *    are distinguishable. Without that, deleting the last skill would fall
 *    back to the config file and the deletion would appear not to work.
 *
 * Cached because `GET /config/options` is called on every app cold start and
 * these lists change a few times a year. Every write path must call [flush].
 */
class OptionListService
{
    /** Bumped with the shape of what is cached, not with its contents. */
    private const CACHE_KEY = 'option_lists.v1';

    private const CACHE_TTL_SECONDS = 3600;

    /**
     * The lists an admin may edit.
     *
     * Everything absent from here is deliberately excluded:
     *
     *  - `language_levels`, `organisation_industries`,
     *    `organisation_sizes` mirror closed enums in `app/Enums/` that the API
     *    validates against. A value added here would be offered by the picker
     *    and then rejected on save.
     *  - `genders` is a closed list duplicated in a `Rule::in` in
     *    `CandidateProfileController` — same problem.
     *  - `passing_years` is computed from the clock; the window is a config
     *    setting, not a list of values.
     *  - `uploads`, `otp`, `pagination`, `token_ttl_days` are settings with
     *    their own shapes, not lists of strings.
     *
     * @var list<string>
     */
    public const EDITABLE_LISTS = [
        'categories',
        'experience_bands',
        'qualifications',
        'job_types',
        'shifts',
        'cities',
        'languages',
        'salary_steps',
        'salary_filters',
        'designations',
        'institutes',
        'departments',
    ];

    /**
     * The job-filter declaration list — groups, not values.
     *
     * Deliberately **not** in [EDITABLE_LISTS]: those are flat lists of
     * strings and the generic editor writes exactly that, whereas a filter
     * group is a label plus four pieces of behaviour carried in `meta`. It
     * has its own admin endpoints for that reason.
     */
    public const FILTER_LIST = 'job_filters';

    /**
     * The `job_postings` columns a filter group may match on.
     *
     * A whitelist, not a convenience: without it, whoever can edit reference
     * data could point a filter at any column on the table and read it back
     * through `GET /jobs` one value at a time.
     *
     * @var list<string>
     */
    public const FILTER_COLUMNS = [
        'role',
        'city',
        'experience',
        'type',
        'shift',
        'salary_min',
    ];

    /**
     * `in` — any of the picked values. `min` — the lowest numeric threshold
     * picked, matched with `>=` (see [RANGE_CEILING] for what it is matched
     * against).
     *
     * @var list<string>
     */
    public const FILTER_TYPES = ['in', 'min'];

    /**
     * For a `min` filter whose column is the floor of a range, the column
     * holding the top of it.
     *
     * A posting's salary is a range, and "₹20K+" asks whether the posting can
     * pay ₹20K — which is a question about its ceiling. Matching the floor
     * alone hid a ₹16K–₹26K job from somebody asking for ₹20K+, and a
     * ₹70K–₹1L job from somebody asking for ₹75K+.
     *
     * Kept here, keyed by the declared column, rather than as a field on the
     * declaration itself: the admin panel can edit a filter group, and a
     * seventh field it does not know to write would be dropped on the first
     * save — quietly restoring the bug. Column names come from this constant
     * and never from a request.
     *
     * @var array<string, string>
     */
    public const RANGE_CEILING = ['salary_min' => 'salary_max'];

    /**
     * Lists that are maps rather than flat arrays, and so are edited through
     * their own endpoint: `city_coordinates` (city => {lat, lng}).
     *
     * @var list<string>
     */
    public const MAP_LISTS = [
        'city_coordinates',
    ];

    /**
     * A flat list, DB override first and the config file otherwise.
     *
     * @return list<string>
     */
    public function list(string $listKey): array
    {
        return $this->all()[$listKey] ?? $this->configList($listKey);
    }

    /**
     * Every editable flat list, resolved. Shape: `list_key => list<string>`.
     *
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn () => $this->resolveAll(),
        );
    }

    /**
     * The job-filter groups the app renders its filter sheet from, each with
     * its chip values already resolved.
     *
     * Declared in `config('options.job_filters')` — see the comment there for
     * the shape and why both sides read the same declaration. Resolving the
     * values *here* is what makes an admin edit show up as a filter chip: the
     * group points at an option list, and [list] already prefers the DB
     * override over the config file.
     *
     * A group naming a list that resolves to nothing is dropped rather than
     * served empty, so a filter heading is never offered with no chips under
     * it.
     *
     * @return list<array{key: string, label: string, param: string, type: string, options: list<string>}>
     */
    public function jobFilterGroups(): array
    {
        $groups = [];

        foreach ($this->jobFilterDeclarations() as $group) {
            $options = $this->list($group['list']);

            if ($options === []) {
                continue;
            }

            $groups[] = [
                'key' => $group['key'],
                'label' => $group['label'],
                'param' => $group['param'],
                'type' => $group['type'],
                'options' => $options,
            ];
        }

        return $groups;
    }

    /**
     * The same declaration keyed by query parameter, for the `/jobs` filter
     * whitelist. Carries `column`, which the app has no use for and is not
     * served.
     *
     * @return array<string, array{column: string, type: string}>
     */
    public function jobFilterParams(): array
    {
        $params = [];

        foreach ($this->jobFilterDeclarations() as $group) {
            $params[$group['param']] = [
                'column' => $group['column'],
                'type' => $group['type'],
            ];
        }

        return $params;
    }

    /**
     * The filter groups as declared — DB override first, config file
     * otherwise, exactly like every other list here.
     *
     * Unlike the others this one is not a list of strings: a group is a label
     * *plus* the parameter it sends, the list its chips come from, the column
     * it matches and how. That rides in `option_items.meta`, which is why
     * [FILTER_LIST] is kept out of [EDITABLE_LISTS] — the generic value
     * editor has nowhere to put any of it, and would write a group with no
     * behaviour.
     *
     * Anything malformed is dropped rather than served: a group naming a
     * column outside [FILTER_COLUMNS] would otherwise let whoever edited it
     * filter on a column the API never meant to expose.
     *
     * @return list<array{id: int|null, key: string, label: string, param: string, list: string, column: string, type: string}>
     */
    public function jobFilterDeclarations(): array
    {
        $rows = OptionItem::forList(self::FILTER_LIST)->active()->ordered()->get();

        $source = $rows->isEmpty()
            ? (array) config('options.'.self::FILTER_LIST, [])
            : $rows->map(fn (OptionItem $row) => [
                'id' => $row->id,
                'label' => $row->value,
                ...($row->meta ?? []),
            ])->all();

        $groups = [];

        foreach ($source as $group) {
            $declaration = $this->normaliseFilter($group);
            if ($declaration !== null) {
                $groups[] = $declaration;
            }
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array{id: int|null, key: string, label: string, param: string, list: string, column: string, type: string}|null
     */
    private function normaliseFilter(array $group): ?array
    {
        $key = trim((string) ($group['key'] ?? ''));
        $label = trim((string) ($group['label'] ?? ''));
        $param = trim((string) ($group['param'] ?? ''));
        $list = trim((string) ($group['list'] ?? ''));
        $column = trim((string) ($group['column'] ?? ''));
        $type = trim((string) ($group['type'] ?? 'in'));

        if ($key === '' || $label === '' || $param === '' || $list === '') {
            return null;
        }

        if (! in_array($column, self::FILTER_COLUMNS, true)) {
            return null;
        }

        if (! in_array($type, self::FILTER_TYPES, true)) {
            return null;
        }

        if (! in_array($list, self::EDITABLE_LISTS, true)) {
            return null;
        }

        return [
            'id' => isset($group['id']) ? (int) $group['id'] : null,
            'key' => $key,
            'label' => $label,
            'param' => $param,
            'list' => $list,
            'column' => $column,
            'type' => $type,
        ];
    }

    /**
     * `city_coordinates`, DB override merged over the config map.
     *
     * A city missing here silently drops every manually-located job in it out
     * of distance sorting, so this merges rather than replaces too.
     *
     * @return array<string, array{lat: float, lng: float}>
     */
    public function cityCoordinates(): array
    {
        $config = (array) config('options.city_coordinates', []);
        $rows = OptionItem::forList('city_coordinates')->active()->ordered()->get();

        if ($rows->isEmpty()) {
            return $config;
        }

        $overrides = [];
        foreach ($rows as $row) {
            $lat = $row->meta['lat'] ?? null;
            $lng = $row->meta['lng'] ?? null;
            if ($lat === null || $lng === null) {
                continue;
            }
            $overrides[$row->value] = ['lat' => (float) $lat, 'lng' => (float) $lng];
        }

        return array_merge($config, $overrides);
    }

    /**
     * Which lists currently have a DB override, whether or not it has rows.
     *
     * Read from `option_items` including inactive rows: an admin who
     * deactivates every value in a list has still overridden it, and must not
     * be silently given the config file's values back.
     *
     * @return list<string>
     */
    public function overriddenLists(): array
    {
        return OptionItem::query()
            ->select('list_key')
            ->distinct()
            ->pluck('list_key')
            ->all();
    }

    /**
     * Copies a list's config-file values into `option_items`, once, so it can
     * be edited as a whole.
     *
     * **Every write path must call this first.** Without it, adding one skill
     * to a list nobody had edited yet would leave `option_items` holding
     * exactly that one row — and since any row at all counts as an override
     * (see [resolveAll]), the other fourteen config values would vanish from
     * every picker in the app. The admin's intent was "add a skill", and the
     * result would be "delete fourteen skills".
     *
     * A no-op once the list has any row, so it is safe to call unconditionally.
     */
    public function materialize(string $listKey): void
    {
        if (OptionItem::forList($listKey)->exists()) {
            return;
        }

        $values = $this->configList($listKey);

        // Nothing to copy: record the override anyway with no rows, so an
        // admin adding the first value to a genuinely empty list still gets
        // an override rather than silently falling back to config forever.
        if ($values === []) {
            return;
        }

        $now = now();
        OptionItem::insert(
            array_map(fn (string $value, int $index) => [
                'list_key' => $listKey,
                'value' => $value,
                // Positions spaced by 10 so a later insert can be slotted
                // between two values without renumbering the whole list.
                'sort_order' => ($index + 1) * 10,
                'is_active' => true,
                'group_key' => null,
                'meta' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $values, array_keys($values)),
        );

        $this->flush();
    }

    /** Call after every write to `option_items`. */
    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The config-file value for a list, always a list of strings.
     *
     * @return list<string>
     */
    public function configList(string $listKey): array
    {
        return array_values(array_map('strval', (array) config("options.{$listKey}", [])));
    }

    /**
     * @return array<string, list<string>>
     */
    private function resolveAll(): array
    {
        // Whether a list is overridden is decided by the presence of ANY row,
        // active or not — checked before reading values, because "the admin
        // deactivated every skill" and "no admin has touched skills" both
        // yield zero active rows and must resolve differently. Getting this
        // backwards would make deleting the last value in a list look broken:
        // the config file's values would reappear.
        $overridden = $this->overriddenLists();

        // One query for every flat list, rather than one per list.
        $grouped = OptionItem::query()
            ->whereIn('list_key', self::EDITABLE_LISTS)
            ->active()
            ->ordered()
            ->get()
            ->groupBy('list_key');

        $resolved = [];

        foreach (self::EDITABLE_LISTS as $listKey) {
            $resolved[$listKey] = in_array($listKey, $overridden, true)
                ? ($grouped->get($listKey)?->pluck('value')->values()->all() ?? [])
                : $this->configList($listKey);
        }

        return $resolved;
    }
}
