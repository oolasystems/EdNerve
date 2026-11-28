<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public verify attempt log (valid|invalid|revoked|expired|not_found).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('credential_id')->nullable();
            $table->string('credential_number', 40)->nullable();
            $table->string('result', 20);
            $table->string('visitor_ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->foreign(['credential_id'], 'credential_verifications_credential_foreign')
                ->references('id')->on('credentials')->nullOnDelete();
            $table->index(['credential_id', 'created_at'], 'credential_verifications_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_verifications');
    }
};
