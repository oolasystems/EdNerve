<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->string('full_name', 180);
            $table->string('email', 190);
            $table->string('normalized_email', 190);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('mobile_country_code', 8)->nullable();
            $table->string('mobile', 30);
            $table->timestamp('mobile_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('status', 30)->default('pending_verification');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'normalized_email'], 'admission_applicants_institution_email_unique');
            $table->unique(['institution_id', 'id'], 'admission_applicants_institution_id_unique');
            $table->index(['institution_id', 'status'], 'admission_applicants_institution_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_applicants');
    }
};
