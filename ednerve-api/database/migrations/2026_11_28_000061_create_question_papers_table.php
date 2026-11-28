<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A paper instance: sections+items; template-driven or manual; workflow statuses
 * draft|final|retired plus ratification trail stamps; SoD enforced server-side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_papers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('subject_id');
            $table->string('code', 40); // e.g. BTCSE-CS301-2026-001
            $table->text('instructions')->nullable();
            $table->string('status', 10)->default('draft'); // draft | prepared | checked | approved | ratified | final | retired
            $table->string('source', 10)->default('manual'); // manual | blueprint
            $table->decimal('total_marks', 7, 2)->default(0);
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('numbering_scheme', 30)->default('1'); // 1 | Q1 | A1 | etc.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('prepared_by')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->unsignedBigInteger('checked_by')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('ratified_by')->nullable();
            $table->timestamp('ratified_at')->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'subject_id'], 'qp_institution_subject_foreign')
                ->references(['institution_id', 'id'])->on('subjects')->restrictOnDelete();
            $table->foreign(['created_by'], 'qp_created_by_foreign')->references('id')->on('users')->restrictOnDelete();
            $table->foreign(['institution_id', 'template_id'], 'qp_institution_template_foreign')
                ->references(['institution_id', 'id'])->on('question_paper_templates')->restrictOnDelete();
            $table->unique(['institution_id', 'code'], 'qp_institution_code_unique');
            $table->unique(['institution_id', 'id'], 'qp_institution_id_unique');
            $table->index(['institution_id', 'subject_id', 'status'], 'qp_subject_status_index');
            $table->index(['institution_id', 'status'], 'qp_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_papers');
    }
};
