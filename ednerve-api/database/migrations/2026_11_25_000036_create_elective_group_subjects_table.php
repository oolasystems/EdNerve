<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subject offering within an elective basket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elective_group_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('elective_group_id');
            $table->unsignedBigInteger('subject_id');
            $table->timestamps();

            $table->foreign(['institution_id', 'elective_group_id'], 'egs_institution_group_foreign')
                ->references(['institution_id', 'id'])->on('elective_groups')->cascadeOnDelete();
            $table->foreign(['institution_id', 'subject_id'], 'egs_institution_subject_foreign')
                ->references(['institution_id', 'id'])->on('subjects')->restrictOnDelete();
            $table->unique(['elective_group_id', 'subject_id'], 'egs_group_subject_unique');
            $table->unique(['institution_id', 'id'], 'egs_institution_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elective_group_subjects');
    }
};
