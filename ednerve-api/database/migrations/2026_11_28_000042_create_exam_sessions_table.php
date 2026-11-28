<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Examination day slots (FN/AN/EV or institution-configured).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('label', 60);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedTinyInteger('sort_order')->default(1);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['institution_id', 'code'], 'exam_sessions_code_unique');
            $table->unique(['institution_id', 'id'], 'exam_sessions_institution_id_unique');
            $table->index(['institution_id', 'active'], 'exam_sessions_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_sessions');
    }
};
