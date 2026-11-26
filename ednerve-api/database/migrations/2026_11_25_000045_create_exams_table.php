<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Examination header (kind: class_test|mid_sem|end_sem|supplementary); publish via SoD+MFA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->string('title', 150);
            $table->string('kind', 20)->default('end_sem');
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->unsignedBigInteger('programme_id');
            $table->unsignedTinyInteger('semester');
            $table->string('section', 20)->default('A');
            $table->text('instructions')->nullable();
            $table->string('status', 20)->default('scheduled'); // scheduled | completed | published
            $table->dateTime('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'academic_session_id'], 'exams_institution_session_foreign')
                ->references(['institution_id', 'id'])->on('academic_sessions')->restrictOnDelete();
            $table->foreign(['institution_id', 'programme_id'], 'exams_institution_programme_foreign')
                ->references(['institution_id', 'id'])->on('programmes')->restrictOnDelete();
            $table->foreign(['published_by'], 'exams_published_by_foreign')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['institution_id', 'id'], 'exams_institution_id_unique');
            $table->index(['institution_id', 'programme_id', 'semester'], 'exams_programme_semester_index');
            $table->index(['institution_id', 'status'], 'exams_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
