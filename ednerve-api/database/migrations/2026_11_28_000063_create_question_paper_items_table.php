<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Items picked per paper; marks_override rescales; choice_group = OR-alternatives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_paper_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('paper_id');
            $table->unsignedBigInteger('section_id');
            $table->unsignedBigInteger('question_id');
            $table->unsignedTinyInteger('position')->default(1);
            $table->unsignedTinyInteger('choice_group')->nullable(); // internal "or" questions picked at print time
            $table->decimal('marks_override', 5, 2)->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'paper_id'], 'qpi_institution_paper_foreign')
                ->references(['institution_id', 'id'])->on('question_papers')->cascadeOnDelete();
            $table->foreign(['institution_id', 'section_id'], 'qpi_institution_section_foreign')
                ->references(['institution_id', 'id'])->on('question_paper_sections')->cascadeOnDelete();
            $table->foreign(['institution_id', 'question_id'], 'qpi_institution_question_foreign')
                ->references(['institution_id', 'id'])->on('question_bank_questions')->restrictOnDelete();
            $table->unique(['institution_id', 'paper_id', 'question_id'], 'qpi_paper_question_unique');
            $table->unique(['institution_id', 'id'], 'qpi_institution_id_unique');
            $table->index(['institution_id', 'section_id'], 'qpi_section_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_paper_items');
    }
};
