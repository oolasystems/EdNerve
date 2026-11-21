<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unit-wise syllabus content (CommonMark topics + learning outcomes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syllabus_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('syllabus_id');
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('unit_number', 20);
            $table->string('title', 250);
            $table->text('topics')->nullable();            // CommonMark
            $table->text('learning_outcomes')->nullable(); // CommonMark
            $table->unsignedSmallInteger('prescribed_hours')->default(0);
            $table->timestamps();

            $table->foreign(['institution_id', 'syllabus_id'], 'su_institution_syllabus_foreign')
                ->references(['institution_id', 'id'])->on('syllabi')->cascadeOnDelete();
            $table->unique(['syllabus_id', 'unit_number'], 'su_syllabus_unit_unique');
            $table->unique(['institution_id', 'id'], 'su_institution_id_unique');
            $table->index(['syllabus_id', 'position'], 'su_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syllabus_units');
    }
};
