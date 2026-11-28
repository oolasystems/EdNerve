<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrolled student profile; provenance snapshot from application; composite unique (institution_id, id)
 * backs institution-safe foreign keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('user_id'); // one active profile per enrolled user
            $table->string('application_number', 40)->nullable(); // snapshot; no FK (admission independence)
            $table->unsignedBigInteger('programme_id')->nullable();
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->string('enrollment_status', 20)->default('current'); // current | leave | withheld | graduated | terminated
            $table->date('admitted_on')->nullable();
            $table->date('left_on')->nullable();
            $table->string('father_name', 120)->nullable();
            $table->string('mother_name', 120)->nullable();
            $table->date('dob')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('nationality', 40)->default('Indian');
            $table->string('category', 20)->default('General');
            $table->string('guardian_name', 120)->nullable();
            $table->string('guardian_relation', 40)->nullable();
            $table->string('guardian_phone', 30)->nullable();
            $table->text('address_line')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pin_code', 12)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'programme_id'], 'sp_institution_programme_foreign')
                ->references(['institution_id', 'id'])->on('programmes')->restrictOnDelete();
            $table->foreign(['institution_id', 'academic_session_id'], 'sp_institution_session_foreign')
                ->references(['institution_id', 'id'])->on('academic_sessions')->restrictOnDelete();
            $table->foreign(['institution_id', 'user_id'], 'sp_institution_user_foreign')
                ->references(['institution_id', 'id'])->on('users')->cascadeOnDelete();
            $table->unique(['institution_id', 'user_id'], 'sp_institution_user_unique');
            $table->unique(['institution_id', 'id'], 'sp_institution_id_unique');
            $table->index(['institution_id', 'enrollment_status'], 'sp_institution_status_index');
            $table->index(['institution_id', 'programme_id'], 'sp_institution_programme_index');
            $table->index(['institution_id', 'academic_session_id'], 'sp_institution_session_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
