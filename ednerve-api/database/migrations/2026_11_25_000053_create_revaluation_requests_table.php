<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Student revaluation requests; finance note stored as amount; manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revaluation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('exam_id');
            $table->unsignedBigInteger('student_profile_id');
            $table->unsignedBigInteger('subject_id');
            $table->text('reason');
            $table->decimal('fee_amount', 8, 2)->nullable();
            $table->string('status', 15)->default('requested'); // requested | in_review | completed | rejected
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'exam_id'], 'rr_institution_exam_foreign')
                ->references(['institution_id', 'id'])->on('exams')->cascadeOnDelete();
            $table->foreign(['institution_id', 'student_profile_id'], 'rr_institution_student_foreign')
                ->references(['institution_id', 'id'])->on('student_profiles')->cascadeOnDelete();
            $table->foreign(['institution_id', 'subject_id'], 'rr_institution_subject_foreign')
                ->references(['institution_id', 'id'])->on('subjects')->restrictOnDelete();
            $table->foreign(['requested_by'], 'rr_requested_by_foreign')->references('id')->on('users')->restrictOnDelete();
            $table->foreign(['handled_by'], 'rr_handled_by_foreign')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['institution_id', 'exam_id', 'student_profile_id', 'subject_id'], 'rr_unique');
            $table->index(['institution_id', 'status'], 'rr_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revaluation_requests');
    }
};
