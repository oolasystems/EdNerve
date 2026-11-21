<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-student room/seat assignment (generation deferred).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_seatings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('exam_subject_id');
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('student_profile_id');
            $table->unsignedInteger('seat_number');
            $table->string('roll_number', 40)->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'exam_subject_id'], 'es_institution_exam_subject_foreign')
                ->references(['institution_id', 'id'])->on('exam_subjects')->cascadeOnDelete();
            $table->foreign(['institution_id', 'room_id'], 'es_institution_room_foreign')
                ->references(['institution_id', 'id'])->on('exam_rooms')->cascadeOnDelete();
            $table->foreign(['institution_id', 'student_profile_id'], 'es_institution_student_foreign')
                ->references(['institution_id', 'id'])->on('student_profiles')->cascadeOnDelete();
            $table->unique(['exam_subject_id', 'student_profile_id'], 'es_subject_student_unique');
            $table->unique(['exam_subject_id', 'room_id', 'seat_number'], 'es_room_seat_unique');
            $table->index(['institution_id', 'exam_subject_id'], 'es_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_seatings');
    }
};
