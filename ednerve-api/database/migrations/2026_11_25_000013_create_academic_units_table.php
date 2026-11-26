<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leaf layer: departments / centres / offices / divisions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            // Exactly one parent level may be non-null (Institution via institution_id).
            $table->unsignedBigInteger('faculty_id')->nullable();
            $table->unsignedBigInteger('school_id')->nullable();
            $table->string('unit_type', 30)->default('department'); // department | centre | office | division | other
            $table->string('code', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['institution_id', 'code'], 'academic_units_institution_code_unique');
            $table->unique(['institution_id', 'id'], 'academic_units_institution_id_unique');
            $table->foreign(['institution_id', 'faculty_id'], 'academic_units_institution_faculty_foreign')
                ->references(['institution_id', 'id'])->on('faculties')->cascadeOnDelete();
            $table->foreign(['institution_id', 'school_id'], 'academic_units_institution_school_foreign')
                ->references(['institution_id', 'id'])->on('schools')->cascadeOnDelete();
            $table->index(['institution_id', 'faculty_id'], 'academic_units_institution_faculty_index');
            $table->index(['institution_id', 'school_id'], 'academic_units_institution_school_index');
            $table->index(['institution_id', 'unit_type'], 'academic_units_institution_type_index');
            $table->index(['institution_id', 'status'], 'academic_units_institution_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_units');
    }
};
