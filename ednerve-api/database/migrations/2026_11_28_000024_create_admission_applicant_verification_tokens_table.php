<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_applicant_verification_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('applicant_id');
            $table->string('purpose', 32);
            $table->string('token_hash', 128)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamps();
            $table->foreign(['institution_id', 'applicant_id'], 'admission_applicant_tokens_owner_foreign')
                ->references(['institution_id', 'id'])->on('admission_applicants')->cascadeOnDelete();
            $table->index(['institution_id', 'applicant_id', 'purpose'], 'admission_applicant_tokens_lookup_index');
            $table->index('expires_at', 'admission_applicant_tokens_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_applicant_verification_tokens');
    }
};
