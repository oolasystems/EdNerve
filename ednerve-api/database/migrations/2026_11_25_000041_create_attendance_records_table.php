<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-student P/A/L marks within a register sheet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('attendance_sheet_id');
            $table->unsignedBigInteger('student_profile_id');
            $table->string('status', 10); // present | absent | late
            $table->string('remarks', 150)->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'attendance_sheet_id'], 'ar_institution_sheet_foreign')
                ->references(['institution_id', 'id'])->on('attendance_sheets')->cascadeOnDelete();
            $table->foreign(['institution_id', 'student_profile_id'], 'ar_institution_student_foreign')
                ->references(['institution_id', 'id'])->on('student_profiles')->cascadeOnDelete();
            $table->unique(['attendance_sheet_id', 'student_profile_id'], 'ar_sheet_student_unique');
            $table->index(['institution_id', 'student_profile_id'], 'ar_student_index');
            $table->index(['institution_id', 'status'], 'ar_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
