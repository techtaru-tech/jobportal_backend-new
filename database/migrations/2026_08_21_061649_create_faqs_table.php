<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Help & Support screen's FAQ list — was a hardcoded `_faqs` const in
     * `HelpSupportView`, now admin-editable (§ Support & Legal, admin panel)
     * and served over `GET /content`.
     *
     * `is_active` is a soft toggle rather than a delete, matching
     * `option_items`' convention: an FAQ an admin wants to hide temporarily
     * (a feature that's mid-change) shouldn't have to be retyped to bring
     * back. `sort_order` is editorial — the most commonly asked question
     * belongs first, and that ordering has to survive a database round trip.
     */
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
