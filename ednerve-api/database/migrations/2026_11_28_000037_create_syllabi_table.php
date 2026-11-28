<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned syllabi per subject. Lifecycle: draft -> in_review -> approved -> published -> archived.
 * SoD: author != approver. Published rows are immutable (API enforces; clone-as-new-version).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syllabi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->string('version', 30); // e.g. 2026.1
            $table->string('title', 250);
            $table->string('status', 20)->default('draft'); // draft | in_review | approved | published | archived
            $table->unsignedBigInteger('author_id');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('superseded_by')->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'subject_id'], 'sy_institution_subject_foreign')
                ->references(['institution_id', 'id'])->on('subjects')->restrictOnDelete();
            $table->foreign(['institution_id', 'academic_session_id'], 'sy_institution_session_foreign')
                ->references(['institution_id', 'id'])->on('academic_sessions')->restrictOnDelete();
            $table->foreign(['institution_id', 'author_id'], 'sy_institution_author_foreign')
                ->references(['institution_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['institution_id', 'approved_by'], 'sy_institution_approved_by_foreign')
                ->references(['institution_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['institution_id', 'published_by'], 'sy_institution_published_by_foreign')
                ->references(['institution_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['superseded_by'], 'sy_superseded_by_foreign')->references('id')->on('syllabi')->nullOnDelete();
            $table->unique(['institution_id', 'subject_id', 'version'], 'sy_subject_version_unique');
            $table->unique(['institution_id', 'id'], 'sy_institution_id_unique');
            $table->index(['institution_id', 'status'], 'sy_status_index');
            $table->index(['institution_id', 'subject_id', 'academic_session_id', 'status'], 'sy_subject_session_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syllabi');
    }
};
