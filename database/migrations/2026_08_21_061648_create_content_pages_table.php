<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The app's Terms, Privacy Policy, About Us and Contact Us screens —
     * legal/support copy that used to be hardcoded as `Text()` widgets in the
     * Flutter source. Editing any of it meant a full app release, which is
     * the wrong cost for fixing a typo in the Privacy Policy or updating a
     * support phone number.
     *
     * Unlike `option_items`, there is no config-file fallback to keep this
     * behaving identically until an admin opts in — the *only* copy of this
     * content today is baked into Dart source, nowhere on the server. So this
     * table is seeded with that exact copy by `ContentSeeder` right after
     * this migration runs, and the app switches from its hardcoded widgets to
     * `GET /content` in the same change. There is deliberately no "empty
     * table = fall back to something" path, because there is nothing to fall
     * back to.
     *
     * `slug` is the fixed, small set of pages the app knows how to render
     * (`terms`, `privacy`, `about`, `contact`) — validated with `Rule::in` at
     * the controller rather than a DB enum, which is painful to extend later.
     *
     * `body` is plain text with a lightweight convention the app's parser
     * understands: a line starting with `## ` is a section heading, and
     * blank-line-separated blocks are paragraphs. No markdown dependency on
     * either side — an admin editing a textarea and a Flutter `Text` widget
     * are all this content has ever needed.
     *
     * `meta` exists only for `contact`, which has structured fields (email,
     * phone, support hours) beyond a single body of text — kept as JSON
     * rather than three more nullable columns that only one row ever uses.
     */
    public function up(): void
    {
        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 30)->unique();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pages');
    }
};
