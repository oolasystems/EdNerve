<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Examination centres (buildings).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_centres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('address', 255)->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['institution_id', 'code'], 'exam_centres_code_unique');
            $table->unique(['institution_id', 'id'], 'exam_centres_institution_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_centres');
    }
};
