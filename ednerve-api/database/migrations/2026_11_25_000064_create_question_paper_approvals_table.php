<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only SoD trail: prepared|checked|approved|ratified stages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_paper_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('paper_id');
            $table->string('stage', 20);     // prepared | checked | approved | ratified
            $table->string('decision', 20);  // approved | rejected
            $table->unsignedBigInteger('actor_id');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'paper_id'], 'qpa_institution_paper_foreign')
                ->references(['institution_id', 'id'])->on('question_papers')->cascadeOnDelete();
            $table->foreign(['actor_id'], 'qpa_actor_foreign')->references('id')->on('users')->restrictOnDelete();
            $table->index(['institution_id', 'paper_id', 'stage'], 'qpa_paper_stage_index');
            $table->unique(['institution_id', 'id'], 'qpa_institution_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_paper_approvals');
    }
};
