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
     *  - `skill_levels`, `language_levels`, `organisation_industries`,
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
        'skills',
        'job_types',
        'shifts',
        'cities',
        'certifications',
        'languages',
        'salary_steps',
        'salary_filters',
        'specializations',
        'designations',
        'institutes',
        'departments',
    ];

    /**
     * Lists that are maps rather than flat arrays, and so are edited through
     * their own endpoints: `skills_by_category` (category => skills) and
     * `city_coordinates` (city => {lat, lng}).
     *
     * @var list<string>
     */
    public const MAP_LISTS = [
        'skills_by_category',
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
     * `skills_by_category`, DB override merged over the config map.
     *
     * Merged per category rather than wholesale: overriding the skills for
     * "Nurse" must not delete the curated lists for the other seven roles.
     *
     * @return array<string, list<string>>
     */
    public function skillsByCategory(): array
    {
        $config = (array) config('options.skills_by_category', []);
        $rows = OptionItem::forList('skills_by_category')->active()->ordered()->get();

        if ($rows->isEmpty()) {
            return $config;
        }

        $overrides = $rows
            ->groupBy('group_key')
            ->map(fn ($items) => $items->pluck('value')->values()->all())
            ->all();

        return array_merge($config, $overrides);
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
