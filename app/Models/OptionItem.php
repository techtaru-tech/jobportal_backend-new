<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One value in one admin-editable reference list — see the `option_items`
 * migration for why these moved out of `config/options.php`.
 *
 * Read through [App\Services\OptionListService], never directly: that service
 * owns the config-file fallback and the cache, and reading this table on its
 * own would silently return an empty list for every list nobody has edited yet.
 */
#[Fillable(['list_key', 'value', 'sort_order', 'is_active', 'group_key', 'meta'])]
class OptionItem extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'meta' => 'array',
        ];
    }

    public function scopeForList(Builder $query, string $listKey): Builder
    {
        return $query->where('list_key', $listKey);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Editorial order first, then alphabetical so ties are at least stable. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('value');
    }
}
