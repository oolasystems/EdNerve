<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_application_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('application_id');
            $table->string('qualification_level', 40);
            $table->string('qualification_name', 180);
            $table->string('board_or_university', 180)->nullable();
            $table->string('institution_name', 180)->nullable();
            $table->string('country', 80)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('roll_number', 100)->nullable();
            $table->unsignedSmallInteger('passing_year')->nullable();
            $table->string('result_status', 30)->default('passed');
            $table->decimal('maximum_marks', 10, 2)->nullable();
            $table->decimal('marks_obtained', 10, 2)->nullable();
            $table->decimal('cgpa', 6, 3)->nullable();
            $table->decimal('percentage', 6, 2)->nullable();
            $table->string('stream_or_subjects', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->foreign(['institution_id', 'application_id'], 'admission_application_qualifications_application_foreign')
                ->references(['institution_id', 'id'])->on('applications')->cascadeOnDelete();

            $table->index(['institution_id', 'application_id', 'sort_order'], 'admission_qualifications_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_application_qualifications');
    }
};
