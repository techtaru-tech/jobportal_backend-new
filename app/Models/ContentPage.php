<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One legal/support page — Terms, Privacy Policy, About Us, or Contact Us.
 * See the `content_pages` migration for the body/meta format and why this
 * table has no config-file fallback the way `OptionItem` does.
 *
 * `slug` is one of [App\Http\Controllers\Api\Admin\ContentController::SLUGS]
 * — that list, not this model, is the single source of truth for which pages
 * exist, since the app has a fixed screen for each one.
 */
#[Fillable(['slug', 'title', 'body', 'meta'])]
class ContentPage extends Model
{
    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
