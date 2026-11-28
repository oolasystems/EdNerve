<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faculty workload store (lite): teaching/load allocations per staff member.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculty_workload_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('programme_id')->nullable();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->string('section', 20)->nullable();
            $table->unsignedSmallInteger('periods_per_week')->default(0);
            $table->decimal('teaching_hours_per_week', 5, 2)->nullable();
            $table->string('role', 40)->nullable(); // e.g. instructor, coordinator
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->foreign(['institution_id', 'user_id'], 'fwe_institution_user_foreign')
                ->references(['institution_id', 'id'])->on('users')->cascadeOnDelete();
            $table->foreign(['institution_id', 'academic_session_id'], 'fwe_institution_session_foreign')
                ->references(['institution_id', 'id'])->on('academic_sessions')->restrictOnDelete();
            $table->foreign(['institution_id', 'subject_id'], 'fwe_institution_subject_foreign')
                ->references(['institution_id', 'id'])->on('subjects')->restrictOnDelete();
            $table->foreign(['institution_id', 'programme_id'], 'fwe_institution_programme_foreign')
                ->references(['institution_id', 'id'])->on('programmes')->restrictOnDelete();

            $table->unique(['institution_id', 'id'], 'fwe_institution_id_unique');
            $table->index(['institution_id', 'user_id', 'academic_session_id'], 'fwe_user_session_index');
            $table->index(['institution_id', 'subject_id'], 'fwe_subject_index');
            $table->index(['institution_id', 'status'], 'fwe_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_workload_entries');
    }
};
