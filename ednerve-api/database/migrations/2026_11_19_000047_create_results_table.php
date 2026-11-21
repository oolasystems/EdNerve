<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Published result snapshot; version bumps on republish (SoD-gated).
 * breakdown stores internal/external split computed by the ordinance engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('version')->default(1); // S1: bump on every publish
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('exam_id');
            $table->unsignedBigInteger('student_profile_id');
            $table->decimal('total_obtained', 9, 2);
            $table->decimal('total_max', 9, 2);
            $table->decimal('percentage', 6, 2);
            $table->string('grade', 3);
            $table->string('result_status', 10); // pass | fail
            $table->boolean('revaluation_applied')->default(false);
            $table->unsignedBigInteger('ordinance_id')->nullable();
            $table->decimal('external_percent', 6, 2)->nullable();
            $table->json('breakdown')->nullable(); // {internal:{obtained,max,percent}, external:{...|null}, criteria:{...}, ordinance:'LEGACY'}
            $table->dateTime('computed_at')->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'exam_id'], 'rs_institution_exam_foreign')
                ->references(['institution_id', 'id'])->on('exams')->cascadeOnDelete();
            $table->foreign(['institution_id', 'student_profile_id'], 'rs_institution_student_foreign')
                ->references(['institution_id', 'id'])->on('student_profiles')->cascadeOnDelete();
            $table->foreign(['ordinance_id'], 'results_ordinance_foreign')->references('id')->on('examination_ordinances')->nullOnDelete();
            $table->unique(['institution_id', 'exam_id', 'student_profile_id'], 'rs_exam_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
