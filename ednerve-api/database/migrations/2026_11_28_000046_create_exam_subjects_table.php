<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subject-in-exam (date/session/room/venue/threshold); pass_percent default 40.
 * Composite FK to subject/session/room; unique per exam x subject.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('exam_id');
            $table->unsignedBigInteger('subject_id');
            $table->date('exam_date');
            $table->unsignedBigInteger('exam_session_id')->nullable();
            $table->unsignedBigInteger('exam_room_id')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('venue', 50)->nullable();
            $table->decimal('pass_percent', 5, 2)->default(40.00);
            $table->text('notes')->nullable();
            $table->text('instructions')->nullable();
            $table->decimal('eligibility_threshold', 5, 2)->nullable(); // null -> institution default (75)
            $table->timestamps();

            $table->foreign(['institution_id', 'exam_id'], 'es_institution_exam_foreign')
                ->references(['institution_id', 'id'])->on('exams')->cascadeOnDelete();
            $table->foreign(['institution_id', 'subject_id'], 'es_institution_subject_foreign')
                ->references(['institution_id', 'id'])->on('subjects')->restrictOnDelete();
            $table->foreign(['exam_session_id'], 'exam_subjects_session_foreign')->references('id')->on('exam_sessions')->nullOnDelete();
            $table->foreign(['exam_room_id'], 'exam_subjects_room_foreign')->references('id')->on('exam_rooms')->nullOnDelete();
            $table->unique(['institution_id', 'exam_id', 'subject_id'], 'es_exam_subject_unique');
            $table->unique(['institution_id', 'id'], 'es_institution_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_subjects');
    }
};
