<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invigilator assignments per exam subject.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_invigilations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('exam_subject_id');
            $table->unsignedBigInteger('user_id');
            $table->string('duty', 20)->default('invigilator'); // invigilator | chief
            $table->timestamps();

            $table->foreign(['institution_id', 'exam_subject_id'], 'ei_institution_exam_subject_foreign')
                ->references(['institution_id', 'id'])->on('exam_subjects')->cascadeOnDelete();
            $table->foreign(['institution_id', 'user_id'], 'ei_institution_user_foreign')
                ->references(['institution_id', 'id'])->on('users')->cascadeOnDelete();
            $table->unique(['institution_id', 'exam_subject_id', 'user_id'], 'ei_subject_user_unique');
            $table->unique(['institution_id', 'id'], 'ei_institution_id_unique');
            $table->index(['institution_id', 'user_id'], 'ei_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_invigilations');
    }
};
