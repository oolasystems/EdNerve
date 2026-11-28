<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class register sheet (one per timetable slot per date).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('timetable_entry_id');
            $table->date('date');
            $table->unsignedBigInteger('marked_by')->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'timetable_entry_id'], 'as_institution_entry_foreign')
                ->references(['institution_id', 'id'])->on('timetable_entries')->restrictOnDelete();
            $table->foreign(['marked_by'], 'as_marked_by_foreign')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['institution_id', 'timetable_entry_id', 'date'], 'as_entry_date_unique');
            $table->unique(['institution_id', 'id'], 'as_institution_id_unique');
            $table->index(['institution_id', 'date'], 'as_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sheets');
    }
};
