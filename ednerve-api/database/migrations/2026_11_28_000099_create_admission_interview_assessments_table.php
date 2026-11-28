<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_interview_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('interview_schedule_id');
            $table->unsignedBigInteger('application_id');
            $table->foreignId('assessor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('score', 8, 2)->nullable();
            $table->string('recommendation', 30)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('assessed_at')->nullable();
            $table->timestamps();
            $table->foreign(['institution_id', 'interview_schedule_id'], 'admission_interview_assessments_schedule_foreign')
                ->references(['institution_id', 'id'])->on('admission_interview_schedules')->cascadeOnDelete();
            $table->foreign(['institution_id', 'application_id'], 'admission_interview_assessments_application_foreign')
                ->references(['institution_id', 'id'])->on('applications')->restrictOnDelete();
            $table->unique(['interview_schedule_id', 'application_id', 'assessor_user_id'], 'admission_interview_assessments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_interview_assessments');
    }
};
