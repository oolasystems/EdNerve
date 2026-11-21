<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-subject component split (theory / practical / tutorial).
 * A subject may carry both theory and practical components (e.g. 3-1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('subject_id');
            $table->string('kind', 20); // theory | practical | tutorial
            $table->unsignedTinyInteger('credits')->default(0);
            $table->unsignedTinyInteger('hours_per_week')->default(0);
            $table->decimal('max_marks', 7, 2)->nullable();
            $table->unsignedTinyInteger('position')->default(1);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->foreign(['institution_id', 'subject_id'], 'sc_institution_subject_foreign')
                ->references(['institution_id', 'id'])->on('subjects')->cascadeOnDelete();
            $table->unique(['institution_id', 'subject_id', 'kind'], 'sc_subject_kind_unique');
            $table->unique(['institution_id', 'id'], 'sc_institution_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_components');
    }
};
