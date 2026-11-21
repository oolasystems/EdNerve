<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable exam kinds per institution (seeded: class_test|mid_sem|end_sem|supplementary).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_kinds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('label', 60);
            $table->unsignedTinyInteger('max_attempts')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['institution_id', 'code'], 'exam_kinds_code_unique');
            $table->unique(['institution_id', 'id'], 'exam_kinds_institution_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_kinds');
    }
};
