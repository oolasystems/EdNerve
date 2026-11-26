<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_application_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('form_version_id');
            $table->string('field_key', 120);
            $table->json('answer_value')->nullable();
            $table->timestamps();
            $table->foreign(['institution_id', 'application_id'], 'admission_application_answers_application_foreign')
                ->references(['institution_id', 'id'])->on('applications')->cascadeOnDelete();
            $table->foreign(['institution_id', 'form_version_id'], 'admission_answers_form_version_foreign')
                ->references(['institution_id', 'id'])->on('admission_form_versions')->restrictOnDelete();
            $table->unique(['application_id', 'form_version_id', 'field_key'], 'admission_answers_field_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_application_answers');
    }
};
