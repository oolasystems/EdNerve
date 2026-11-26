<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Degree programmes (UG/PG) with semester counts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name', 200);
            $table->string('level', 10); // UG | PG
            $table->unsignedTinyInteger('duration_semesters');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['institution_id', 'code'], 'programmes_institution_code_unique');
            $table->unique(['institution_id', 'id'], 'programmes_institution_id_unique');
            $table->index(['institution_id', 'level'], 'programmes_institution_level_index');
            $table->index(['institution_id', 'status'], 'programmes_institution_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmes');
    }
};
