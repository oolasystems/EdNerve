<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-session student course enrolment/registration (core + elective) with elective enforcement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('student_profile_id');
            $table->unsignedBigInteger('academic_session_id');
            $table->unsignedBigInteger('programme_course_id');
            $table->unsignedBigInteger('elective_group_id')->nullable();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->string('status', 20)->default('registered'); // registered|dropped|completed
            $table->timestamp('registered_at')->nullable();
            $table->unsignedBigInteger('registered_by')->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'student_profile_id'], 'creg_institution_student_foreign')
                ->references(['institution_id', 'id'])->on('student_profiles')->cascadeOnDelete();
            $table->foreign(['institution_id', 'academic_session_id'], 'creg_institution_session_foreign')
                ->references(['institution_id', 'id'])->on('academic_sessions')->restrictOnDelete();
            $table->foreign('programme_course_id', 'creg_pcourse_foreign')
                ->references('id')->on('programme_courses')->restrictOnDelete();
            $table->foreign(['institution_id', 'elective_group_id'], 'creg_institution_egroup_foreign')
                ->references(['institution_id', 'id'])->on('elective_groups')->restrictOnDelete();
            $table->foreign('registered_by', 'creg_registered_by_foreign')
                ->references('id')->on('users')->restrictOnDelete();

            $table->unique(['institution_id', 'id'], 'creg_institution_id_unique');
            $table->unique(
                ['institution_id', 'student_profile_id', 'academic_session_id', 'programme_course_id'],
                'creg_student_session_course_unique'
            );
            $table->index(['institution_id', 'student_profile_id', 'academic_session_id'], 'creg_student_session_index');
            $table->index(['institution_id', 'academic_session_id', 'semester'], 'creg_session_sem_index');
            $table->index(['institution_id', 'elective_group_id'], 'creg_egroup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_registrations');
    }
};
