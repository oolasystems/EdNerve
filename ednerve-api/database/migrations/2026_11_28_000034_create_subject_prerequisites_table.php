<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same-institution prerequisite graph; cycle detection in app validation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('prerequisite_subject_id');
            $table->timestamps();

            $table->foreign(['institution_id', 'subject_id'], 'spr_institution_subject_foreign')
                ->references(['institution_id', 'id'])->on('subjects')->cascadeOnDelete();
            $table->foreign(['institution_id', 'prerequisite_subject_id'], 'spr_institution_prereq_foreign')
                ->references(['institution_id', 'id'])->on('subjects')->restrictOnDelete();
            $table->unique(['institution_id', 'subject_id', 'prerequisite_subject_id'], 'spr_institution_unique');
            $table->unique(['institution_id', 'id'], 'spr_institution_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_prerequisites');
    }
};
