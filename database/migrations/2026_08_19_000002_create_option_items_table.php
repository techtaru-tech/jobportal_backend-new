<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Runtime-editable reference data — the option lists behind every picker
     * and chip row in the app (`GET /config/options`).
     *
     * Those lists have always lived in `config/options.php`. A config file is
     * fine until someone needs to add a qualification without shipping a
     * release, and it is actively wrong once `config:cache` runs — the cached
     * array is what the app would keep serving. So the lists move here, and
     * `OptionListService` reads this table FIRST and falls back to the config
     * file for any list nobody has touched yet.
     *
     * That fallback is the point: this migration seeds nothing and changes no
     * behaviour on its own. `config/options.php` stays the source of truth
     * until an admin overrides a specific list, so an empty table and a
     * populated one both serve exactly the values the app serves today.
     *
     * Deliberately NOT stored here: the closed enums (`skill_levels`,
     * `language_levels`, `organisation_industries`, `organisation_sizes`) and
     * `genders`. Those are validated against `app/Enums/` or a `Rule::in`, so
     * editing them from a UI would produce values the API then rejects — a
     * setting that silently does nothing is worse than no setting.
     */
    public function up(): void
    {
        Schema::create('option_items', function (Blueprint $table) {
            $table->id();

            // Which list this value belongs to — the `config/options.php` key
            // ('skills', 'cities', 'qualifications', …).
            $table->string('list_key', 40);

            $table->string('value');

            // Display order. The app renders these lists in array order, and
            // that order is editorial (common answers first), so it has to
            // survive a round trip through the database.
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Soft retirement. A value in use by existing rows must be
            // removable from the pickers without being deleted out from under
            // the profiles and postings that already reference it.
            $table->boolean('is_active')->default(true);

            // For `skills_by_category` and `city_coordinates`, which are maps
            // rather than flat lists: the parent key (a category name / a city
            // name) and the JSON payload (lat+lng) respectively.
            $table->string('group_key')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['list_key', 'group_key', 'value']);
            $table->index(['list_key', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('option_items');
    }
};
