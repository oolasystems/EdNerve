<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frozen result snapshots for S1 versioning (no silent overwrite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('results_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('exam_id');
            $table->unsignedBigInteger('student_profile_id');
            $table->decimal('total_obtained', 9, 2);
            $table->decimal('total_max', 9, 2);
            $table->decimal('percentage', 6, 2);
            $table->string('grade', 3);
            $table->string('result_status', 10);
            $table->boolean('revaluation_applied')->default(false);
            $table->unsignedTinyInteger('version');
            $table->unsignedBigInteger('superseded_by')->nullable();
            $table->dateTime('computed_at')->nullable();
            $table->dateTime('superseded_at')->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'student_profile_id'], 'rh_institution_student_foreign')
                ->references(['institution_id', 'id'])->on('student_profiles')->cascadeOnDelete();
            $table->foreign(['exam_id'], 'rh_exam_foreign')->references('id')->on('exams')->cascadeOnDelete();
            $table->foreign(['superseded_by'], 'rh_superseded_by_foreign')->references('id')->on('users')->restrictOnDelete();
            $table->index(['institution_id', 'exam_id'], 'rh_exam_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('results_history');
    }
};
