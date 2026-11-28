<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * University-configurable paper blueprints (assessment kind, pattern JSON, instructions).
 * Feeds the draft->prepared->checked->approved->ratified workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_paper_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('assessment_kind', 40)->default('exam'); // exam | unit_test | mid | sessional
            $table->decimal('total_marks', 7, 2);
            $table->json('pattern')->nullable(); // [{title, position, instruction, items, marks}]
            $table->text('instructions_md')->nullable();
            $table->string('status', 20)->default('active'); // active | inactive
            $table->timestamps();

            $table->unique(['institution_id', 'name'], 'qpt_institution_name_unique');
            $table->unique(['institution_id', 'id'], 'qpt_institution_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_paper_templates');
    }
};
