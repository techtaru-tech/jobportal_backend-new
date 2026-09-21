<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the candidate bought the watermark-free resume, or null if they have
 * not.
 *
 * A timestamp rather than a boolean because it is also the receipt: support
 * answering "I paid for this" needs to know when, and it lines up with the
 * `payment_orders` row that granted it.
 *
 * Stamped only by `PaymentService::capture`, after the gateway reports the
 * money — never by anything the client sends.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->timestamp('resume_watermark_free_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn('resume_watermark_free_at');
        });
    }
};
