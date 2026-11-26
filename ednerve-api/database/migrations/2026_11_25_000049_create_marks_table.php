<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Component-wise marks; status present|absent. entered_by supports SoD on publish
 * (the publisher may not have entered any marks — enforced in app + test).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('exam_component_id');
            $table->unsignedBigInteger('student_profile_id');
            $table->string('status', 10)->default('present'); // present | absent
            $table->decimal('marks_obtained', 7, 2)->nullable();
            $table->string('remarks', 150)->nullable();
            $table->unsignedBigInteger('entered_by')->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'exam_component_id'], 'mk_institution_component_foreign')
                ->references(['institution_id', 'id'])->on('exam_components')->cascadeOnDelete();
            $table->foreign(['institution_id', 'student_profile_id'], 'mk_institution_student_foreign')
                ->references(['institution_id', 'id'])->on('student_profiles')->cascadeOnDelete();
            $table->foreign(['entered_by'], 'marks_entered_by_foreign')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['exam_component_id', 'student_profile_id'], 'mk_component_student_unique');
            $table->index(['institution_id', 'student_profile_id'], 'mk_student_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marks');
    }
};
