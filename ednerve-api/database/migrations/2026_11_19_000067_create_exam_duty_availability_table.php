<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_duty_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('exam_id');
            $table->unsignedBigInteger('exam_session_id')->nullable();
            $table->date('duty_date')->nullable();
            $table->boolean('available')->default(true);
            $table->string('reason', 500)->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'user_id'], 'eda_institution_user_foreign')
                ->references(['institution_id', 'id'])->on('users')->cascadeOnDelete();
            $table->foreign(['institution_id', 'exam_id'], 'eda_institution_exam_foreign')
                ->references(['institution_id', 'id'])->on('exams')->cascadeOnDelete();
            $table->foreign(['institution_id', 'exam_session_id'], 'eda_institution_exam_session_foreign')
                ->references(['institution_id', 'id'])->on('exam_sessions')->restrictOnDelete();

            $table->unique(['institution_id', 'user_id', 'exam_id', 'exam_session_id', 'duty_date'], 'eda_user_exam_slot_unique');
            $table->unique(['institution_id', 'id'], 'eda_institution_id_unique');
            $table->index(['institution_id', 'exam_id'], 'eda_exam_index');
            $table->index(['institution_id', 'user_id'], 'eda_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_duty_availability');
    }
};
