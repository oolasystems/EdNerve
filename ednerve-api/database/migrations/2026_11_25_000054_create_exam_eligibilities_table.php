<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance-gated hall-ticket eligibility, per subject; overrides audited+MFA.
 * Recompute never clobbers an 'overridden' row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_eligibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('exam_subject_id');
            $table->unsignedBigInteger('student_profile_id');
            $table->string('status', 12)->default('short'); // eligible | short | overridden
            $table->decimal('attendance_pct', 5, 2)->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'exam_subject_id'], 'ee_institution_exam_subject_foreign')
                ->references(['institution_id', 'id'])->on('exam_subjects')->cascadeOnDelete();
            $table->foreign(['institution_id', 'student_profile_id'], 'ee_institution_student_foreign')
                ->references(['institution_id', 'id'])->on('student_profiles')->cascadeOnDelete();
            $table->foreign(['decided_by'], 'ee_decided_by_foreign')->references('id')->on('users')->nullOnDelete();
            $table->unique(['exam_subject_id', 'student_profile_id'], 'ee_subject_student_unique');
            $table->unique(['institution_id', 'id'], 'exam_eligibilities_institution_id_unique');
            $table->index(['institution_id', 'exam_subject_id', 'status'], 'ee_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_eligibilities');
    }
};
