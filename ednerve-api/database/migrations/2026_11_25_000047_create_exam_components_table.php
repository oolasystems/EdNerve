<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marking components per exam subject (e.g. Internal 30, Theory 70).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('exam_subject_id');
            $table->string('name', 60); // e.g. Internal, Theory, Practical, Viva
            $table->decimal('max_marks', 7, 2);
            $table->unsignedTinyInteger('position')->default(1);
            $table->timestamps();

            $table->foreign(['institution_id', 'exam_subject_id'], 'ec_institution_exam_subject_foreign')
                ->references(['institution_id', 'id'])->on('exam_subjects')->cascadeOnDelete();
            $table->unique(['institution_id', 'exam_subject_id', 'name'], 'ec_subject_name_unique');
            $table->unique(['institution_id', 'id'], 'ec_institution_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_components');
    }
};
