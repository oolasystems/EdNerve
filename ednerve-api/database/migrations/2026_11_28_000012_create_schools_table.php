<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional mid layer (JNU pattern: University -> School -> Centre).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('faculty_id')->nullable(); // JNU may attach schools directly to the institution
            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['institution_id', 'code'], 'schools_institution_code_unique');
            $table->unique(['institution_id', 'id'], 'schools_institution_id_unique');
            $table->foreign(['institution_id', 'faculty_id'], 'schools_institution_faculty_foreign')
                ->references(['institution_id', 'id'])->on('faculties')->cascadeOnDelete();
            $table->index(['institution_id', 'faculty_id'], 'schools_institution_faculty_index');
            $table->index(['institution_id', 'status'], 'schools_institution_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
