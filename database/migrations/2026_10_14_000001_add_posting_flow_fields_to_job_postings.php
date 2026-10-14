<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The fields the reworked Post a Job flow asks for (Job details, Candidate
 * requirements), plus the plan the posting went out on. All nullable: every
 * posting made before this keeps working, and the web employer area does
 * not send them yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            // Step 1 · job details
            $table->unsignedInteger('openings')->nullable()->after('title');
            $table->boolean('charges_fee')->nullable()->after('openings');
            $table->string('state')->nullable()->after('city');
            $table->string('locality', 120)->nullable()->after('state');

            // Step 2 · candidate requirements
            $table->string('min_education', 40)->nullable()->after('experience');
            $table->string('preferred_gender', 10)->nullable()->after('min_education');
            $table->json('skills')->nullable()->after('preferred_gender');
            $table->string('contact_mode', 20)->nullable()->after('skills');
            $table->string('contact_phone', 15)->nullable()->after('contact_mode');
            $table->string('whatsapp_phone', 15)->nullable()->after('contact_phone');

            // Step 4 · the plan it went out on (null for postings made before
            // per-posting plans existed, or under a subscription).
            $table->string('plan_tier', 20)->nullable()->after('posting_status');
            $table->string('plan_reach', 20)->nullable()->after('plan_tier');
            $table->foreignId('job_credit_id')->nullable()->after('plan_reach');
            $table->timestamp('submitted_at')->nullable()->after('job_credit_id');
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn([
                'openings', 'charges_fee', 'state', 'locality',
                'min_education', 'preferred_gender', 'skills',
                'contact_mode', 'contact_phone', 'whatsapp_phone',
                'plan_tier', 'plan_reach', 'job_credit_id', 'submitted_at',
            ]);
        });
    }
};
