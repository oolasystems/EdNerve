<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_application_entrance_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('application_id');
            $table->string('exam_name', 150);
            $table->string('exam_year', 12)->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->string('roll_number', 100)->nullable();
            $table->string('paper_or_subject', 150)->nullable();
            $table->decimal('score', 10, 3)->nullable();
            $table->decimal('percentile', 7, 3)->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->string('result_status', 30)->nullable();
            $table->unsignedBigInteger('scorecard_document_id')->nullable();
            $table->timestamps();
            $table->foreign(['institution_id', 'application_id'], 'admission_application_entrance_exams_application_foreign')
                ->references(['institution_id', 'id'])->on('applications')->cascadeOnDelete();

            $table->foreign(['institution_id', 'scorecard_document_id'], 'admission_entrance_scorecard_document_foreign')
                ->references(['institution_id', 'id'])->on('admission_application_documents')->restrictOnDelete();

            $table->index(['institution_id', 'application_id'], 'admission_entrance_application_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_application_entrance_exams');
    }
};
