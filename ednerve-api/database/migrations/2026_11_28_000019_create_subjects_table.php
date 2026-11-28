<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subject master (credits, theory/practical).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('academic_unit_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 200);
            $table->unsignedTinyInteger('credits')->default(3);
            $table->string('subject_type', 20)->default('theory'); // theory | practical
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['institution_id', 'code'], 'subjects_institution_code_unique');
            $table->unique(['institution_id', 'id'], 'subjects_institution_id_unique');
            $table->foreign(['institution_id', 'academic_unit_id'], 'subjects_institution_unit_foreign')
                ->references(['institution_id', 'id'])->on('academic_units')->restrictOnDelete();
            $table->index(['institution_id', 'subject_type'], 'subjects_institution_type_index');
            $table->index(['institution_id', 'status'], 'subjects_institution_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
