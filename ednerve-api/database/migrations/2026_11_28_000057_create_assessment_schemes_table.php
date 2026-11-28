<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-programme (default) or per-subject assessment scheme template
 * (e.g. Sessional 20 + Mid-sem 10 + End-sem 70). Subject scheme wins over programme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->string('scope', 10); // subject | programme
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('programme_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->string('status', 20)->default('active'); // active | archived
            $table->timestamps();

            $table->foreign(['institution_id', 'subject_id'], 'schemes_institution_subject_foreign')
                ->references(['institution_id', 'id'])->on('subjects')->cascadeOnDelete();
            $table->foreign(['institution_id', 'programme_id'], 'schemes_institution_programme_foreign')
                ->references(['institution_id', 'id'])->on('programmes')->cascadeOnDelete();
            $table->unique(['institution_id', 'code'], 'schemes_code_unique');
            $table->unique(['institution_id', 'id'], 'assessment_schemes_institution_id_unique');
            $table->index(['institution_id', 'scope', 'status'], 'schemes_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_schemes');
    }
};
