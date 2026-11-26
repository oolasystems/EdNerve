<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lesson plans (lite): unit-wise teaching progress per subject offering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_plan_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->unsignedBigInteger('syllabus_id')->nullable();
            $table->unsignedBigInteger('faculty_user_id')->nullable();
            $table->unsignedBigInteger('programme_id')->nullable();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->string('section', 20)->nullable();
            $table->string('unit_number', 30)->nullable();
            $table->string('topic', 255);
            $table->decimal('planned_hours', 5, 2)->default(0);
            $table->decimal('taught_hours', 5, 2)->default(0);
            $table->date('planned_date')->nullable();
            $table->date('taught_date')->nullable();
            $table->string('status', 20)->default('planned'); // planned|in_progress|completed|deferred
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'subject_id'], 'lpe_institution_subject_foreign')
                ->references(['institution_id', 'id'])->on('subjects')->restrictOnDelete();
            $table->foreign(['institution_id', 'academic_session_id'], 'lpe_institution_session_foreign')
                ->references(['institution_id', 'id'])->on('academic_sessions')->restrictOnDelete();
            $table->foreign(['institution_id', 'syllabus_id'], 'lpe_institution_syllabus_foreign')
                ->references(['institution_id', 'id'])->on('syllabi')->restrictOnDelete();
            $table->foreign(['institution_id', 'faculty_user_id'], 'lpe_institution_faculty_foreign')
                ->references(['institution_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['institution_id', 'programme_id'], 'lpe_institution_programme_foreign')
                ->references(['institution_id', 'id'])->on('programmes')->restrictOnDelete();
            $table->foreign(['institution_id', 'updated_by'], 'lpe_institution_updated_by_foreign')
                ->references(['institution_id', 'id'])->on('users')->restrictOnDelete();

            $table->unique(['institution_id', 'id'], 'lpe_institution_id_unique');
            $table->index(['institution_id', 'subject_id', 'academic_session_id'], 'lpe_subject_session_index');
            $table->index(['institution_id', 'faculty_user_id'], 'lpe_faculty_index');
            $table->index(['institution_id', 'status'], 'lpe_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_plan_entries');
    }
};
