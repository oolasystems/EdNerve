<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Applicant record; review pipeline; enrolled -> creates student profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('institution_id')
                ->constrained('institutions')
                ->restrictOnDelete();

            // Nullable to preserve anonymous/legacy applications.
            $table->unsignedBigInteger('applicant_id')->nullable();
            $table->unsignedBigInteger('form_version_id')->nullable();
            $table->unsignedBigInteger('intake_id');

            $table->string('first_name', 80)->nullable();
            $table->string('middle_name', 80)->nullable();
            $table->string('last_name', 80)->nullable();
            $table->string('father_name', 120)->nullable();
            $table->string('mother_name', 120)->nullable();

            $table->date('dob')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('category', 20)->default('General');
            $table->string('nationality', 40)->default('Indian');

            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();

            $table->text('address_line')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pin_code', 12)->nullable();

            $table->string('last_exam_name', 120)->nullable();
            $table->string('last_exam_board_or_univ', 150)->nullable();
            $table->unsignedSmallInteger('last_exam_year')->nullable();
            $table->decimal('last_exam_percentage', 5, 2)->nullable();

            $table->string('application_number', 32);
            $table->string('status', 40)->default('draft');

            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('enrolled_user_id')->nullable();

            $table->timestamps();

            $table->foreign(
                ['institution_id', 'intake_id'],
                'app_institution_intake_foreign'
            )
                ->references(['institution_id', 'id'])
                ->on('admission_intakes')
                ->cascadeOnDelete();

            $table->foreign(['institution_id', 'applicant_id'], 'applications_applicant_foreign')
                ->references(['institution_id', 'id'])
                ->on('admission_applicants')
                ->restrictOnDelete();

            $table->foreign(['institution_id', 'form_version_id'], 'applications_form_version_foreign')
                ->references(['institution_id', 'id'])
                ->on('admission_form_versions')
                ->restrictOnDelete();

            // Reviewer/enrolled user institution membership enforced in application code.
            $table->foreign('reviewed_by', 'app_reviewer_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('enrolled_user_id', 'app_enrolled_user_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->unique(['institution_id', 'id'], 'applications_institution_id_unique');

            $table->unique(
                ['institution_id', 'application_number'],
                'applications_institution_number_unique'
            );

            $table->index(
                ['institution_id', 'applicant_id'],
                'applications_applicant_index'
            );

            $table->index(
                ['institution_id', 'intake_id', 'status'],
                'applications_institution_intake_status_index'
            );

            $table->index(
                ['institution_id', 'status'],
                'applications_institution_status_index'
            );

            $table->index(
                ['institution_id', 'email'],
                'applications_institution_email_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
