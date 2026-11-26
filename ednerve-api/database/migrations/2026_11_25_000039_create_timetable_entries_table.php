<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly class grid rows (day_of_week 1=Mon..6=Sat x period_no).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('programme_id');
            $table->unsignedTinyInteger('semester');
            $table->string('section', 20)->default('A');
            $table->unsignedTinyInteger('day_of_week'); // 1 = Monday ... 6 = Saturday
            $table->unsignedTinyInteger('period_no');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('faculty_user_id')->nullable();
            $table->string('room', 50)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->foreign(['institution_id', 'programme_id'], 'te_institution_programme_foreign')
                ->references(['institution_id', 'id'])->on('programmes')->restrictOnDelete();
            $table->foreign(['institution_id', 'subject_id'], 'te_institution_subject_foreign')
                ->references(['institution_id', 'id'])->on('subjects')->restrictOnDelete();
            $table->foreign(['institution_id', 'faculty_user_id'], 'te_institution_faculty_foreign')
                ->references(['institution_id', 'id'])->on('users')->restrictOnDelete();
            $table->unique(['institution_id', 'programme_id', 'semester', 'section', 'day_of_week', 'period_no'], 'te_slot_unique');
            $table->unique(['institution_id', 'id'], 'te_institution_id_unique');
            $table->index(['institution_id', 'faculty_user_id', 'day_of_week'], 'te_faculty_day_index');
            $table->index(['institution_id', 'programme_id', 'semester'], 'te_programme_semester_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
    }
};
