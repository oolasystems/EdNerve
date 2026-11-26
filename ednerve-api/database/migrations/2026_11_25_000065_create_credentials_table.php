<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W3C VC-style signed academic credentials (ES256).
 * One row per (exam x student); payload + signature; verification log on the verifications table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->string('credential_number', 40);
            $table->unsignedBigInteger('student_profile_id');
            $table->string('type', 40); // marksheet | transcript | degree | diploma | certificate
            $table->unsignedBigInteger('exam_id')->nullable();
            $table->unsignedBigInteger('programme_id')->nullable();
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->json('payload'); // W3C VC JSON, signed as stored
            $table->longText('signature');
            $table->string('signature_algorithm', 40)->default('EcdsaSecp256r1Signature2019');
            $table->string('public_key_id', 80)->nullable();
            $table->string('status', 20)->default('issued'); // issued | revoked | replaced
            $table->dateTime('issued_at');
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->string('revoke_reason', 200)->nullable();
            $table->unsignedBigInteger('replaces_id')->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'student_profile_id'], 'credentials_institution_student_foreign')
                ->references(['institution_id', 'id'])->on('student_profiles')->restrictOnDelete();
            $table->foreign(['exam_id'], 'credentials_exam_foreign')->references('id')->on('exams')->nullOnDelete();
            $table->foreign(['programme_id'], 'credentials_programme_foreign')->references('id')->on('programmes')->nullOnDelete();
            $table->foreign(['academic_session_id'], 'credentials_session_foreign')->references('id')->on('academic_sessions')->nullOnDelete();
            $table->foreign(['replaces_id'], 'credentials_replaces_foreign')->references('id')->on('credentials')->nullOnDelete();
            $table->unique(['institution_id', 'credential_number'], 'credentials_institution_number_unique');
            $table->unique(['institution_id', 'id'], 'credentials_institution_id_unique');
            $table->index(['institution_id', 'student_profile_id', 'type'], 'credentials_student_type_index');
            $table->index(['institution_id', 'status'], 'credentials_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credentials');
    }
};
