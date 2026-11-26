<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sections within a paper (Section A/B …).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_paper_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('paper_id');
            $table->string('title', 60); // Section A
            $table->string('instruction', 150)->nullable();
            $table->unsignedTinyInteger('position')->default(1);
            $table->timestamps();

            $table->foreign(['institution_id', 'paper_id'], 'qps_institution_paper_foreign')
                ->references(['institution_id', 'id'])->on('question_papers')->cascadeOnDelete();
            $table->unique(['institution_id', 'paper_id', 'position'], 'qps_paper_position_unique');
            $table->unique(['institution_id', 'id'], 'qps_institution_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_paper_sections');
    }
};
