<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user TOTP secret (AES-encrypted at rest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_mfa_secrets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->text('secret'); // base32; encrypted at rest
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_mfa_secrets');
    }
};
