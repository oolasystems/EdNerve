<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Components of an assessment scheme (weightage sums to 100 when set).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_scheme_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('scheme_id');
            $table->unsignedTinyInteger('position')->default(1);
            $table->string('name', 60);
            $table->string('kind', 12); // continuous | external | practical | viva | project
            $table->decimal('max_marks', 7, 2);
            $table->decimal('weightage', 5, 2)->nullable();
            $table->boolean('is_external')->default(false);
            $table->decimal('min_percent', 5, 2)->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'scheme_id'], 'asc_institution_scheme_foreign')
                ->references(['institution_id', 'id'])->on('assessment_schemes')->cascadeOnDelete();
            $table->unique(['scheme_id', 'name'], 'asc_scheme_name_unique');
            $table->index(['institution_id', 'scheme_id'], 'asc_scheme_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_scheme_components');
    }
};
