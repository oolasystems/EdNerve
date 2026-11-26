<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_interview_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('intake_id');
            $table->string('name', 180);
            $table->string('interview_type', 30)->default('interview');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('venue_or_link', 500)->nullable();
            $table->string('status', 25)->default('scheduled');
            $table->timestamps();
            $table->foreign(['institution_id', 'intake_id'], 'admission_interviews_intake_foreign')
                ->references(['institution_id', 'id'])->on('admission_intakes')->restrictOnDelete();

            // Required parent key for institution-scoped interview assessment foreign keys.
            $table->unique(
                ['institution_id', 'id'],
                'admission_interview_schedules_institution_id_unique'
            );
            $table->index(['institution_id', 'intake_id', 'starts_at'], 'admission_interviews_schedule_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_interview_schedules');
    }
};
