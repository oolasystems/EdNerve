<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * First-class academic section/batch entity (replaces ad-hoc free-text section labels).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->unsignedBigInteger('programme_id')->nullable();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->foreign(['institution_id', 'academic_session_id'], 'asec_institution_session_foreign')
                ->references(['institution_id', 'id'])->on('academic_sessions')->restrictOnDelete();
            $table->foreign(['institution_id', 'programme_id'], 'asec_institution_programme_foreign')
                ->references(['institution_id', 'id'])->on('programmes')->restrictOnDelete();

            $table->unique(['institution_id', 'id'], 'asec_institution_id_unique');
            $table->unique(
                ['institution_id', 'academic_session_id', 'programme_id', 'semester', 'code'],
                'asec_scope_code_unique'
            );
            $table->index(['institution_id', 'programme_id', 'semester'], 'asec_prog_sem_index');
            $table->index(['institution_id', 'status'], 'asec_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_sections');
    }
};
