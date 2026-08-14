<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * §7 — a recruiter account is not tied to a single company. Staffing
     * agencies hire for several employers, so the contact person (one per
     * account) and the employers they hire for (many) are separate things.
     */
    public function up(): void
    {
        Schema::create('recruiter_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('contact_person_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('industry', 40)->nullable();
            $table->string('size', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('website')->nullable();
            $table->string('gst_number', 30)->nullable();
            $table->text('about')->nullable();

            $table->string('logo_path')->nullable();

            // §7.3 — the artefact verification runs against. Stored privately.
            $table->string('document_name')->nullable();
            $table->string('document_path')->nullable();

            // §7.2 — server-owned. Never writable by a client; a self-service
            // verified badge would defeat its purpose.
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
        Schema::dropIfExists('recruiter_profiles');
    }
};
