<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subject questions with model answers. question_text and model_answer are
 * encrypted at rest (APP_KEY) — API decrypts on read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_bank_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('subject_id');
            $table->string('unit', 80)->nullable();
            $table->string('difficulty', 10); // easy | medium | hard
            $table->string('type', 20); // mcq | short | long | numerical
            $table->decimal('marks', 5, 2);
            $table->text('question_text'); // encrypted
            $table->text('model_answer');  // encrypted
            $table->string('tags', 150)->nullable();
            $table->string('status', 10)->default('draft'); // draft | active | archived
            $table->timestamps();

            $table->foreign(['institution_id', 'subject_id'], 'qbq_institution_subject_foreign')
                ->references(['institution_id', 'id'])->on('subjects')->restrictOnDelete();
            $table->unique(['institution_id', 'id'], 'qbq_institution_id_unique');
            $table->index(['institution_id', 'subject_id', 'status'], 'qbq_subject_status_index');
            $table->index(['institution_id', 'subject_id', 'difficulty'], 'qbq_subject_difficulty_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_bank_questions');
    }
};
