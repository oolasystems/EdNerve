<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academic terms (e.g. 2026-27); one active per institution (code-enforced).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['institution_id', 'code'], 'academic_sessions_institution_code_unique');
            $table->unique(['institution_id', 'id'], 'academic_sessions_institution_id_unique');
            $table->index(['institution_id', 'is_active'], 'academic_sessions_institution_active_index');
            $table->index(['institution_id', 'status'], 'academic_sessions_institution_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_sessions');
    }
};
