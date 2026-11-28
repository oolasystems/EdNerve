<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Semester x subject mapping per programme; credit_override/teaching_hours are optional overrides
 * (fall back to subject credits/hours).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('programme_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedTinyInteger('semester'); // 1-based semester number
            $table->string('requirement_type', 20)->default('core'); // core | elective
            $table->unsignedTinyInteger('position')->default(0); // display order within semester
            $table->unsignedTinyInteger('credit_override')->nullable();
            $table->unsignedSmallInteger('teaching_hours')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->foreign(['institution_id', 'programme_id'], 'ps_institution_programme_foreign')
                ->references(['institution_id', 'id'])->on('programmes')->cascadeOnDelete();
            $table->foreign(['institution_id', 'subject_id'], 'ps_institution_subject_foreign')
                ->references(['institution_id', 'id'])->on('subjects')->restrictOnDelete();
            $table->unique(['institution_id', 'id'], 'ps_institution_id_unique');
            $table->unique(['institution_id', 'programme_id', 'id'], 'ps_institution_programme_id_unique');
            $table->unique(['institution_id', 'programme_id', 'subject_id'], 'ps_institution_programme_subject_unique');
            $table->index(['institution_id', 'programme_id', 'semester'], 'ps_institution_programme_semester_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_subjects');
    }
};
