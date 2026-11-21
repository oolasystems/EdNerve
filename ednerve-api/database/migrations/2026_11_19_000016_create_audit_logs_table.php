<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security/business event trail. Hash-chain columns (S1) are part of the definition.
 * Existing rows carry null hashes and are excluded from verification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained('institutions')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 80);
            $table->string('module', 80);
            $table->string('actor_type', 20)->default('user');
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('description', 500)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('prev_hash', 64)->nullable();
            $table->string('entry_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'created_at']);
            $table->index(['actor_user_id', 'created_at']);
            $table->index(['module', 'event']);
            $table->index(['created_at', 'id'], 'audit_logs_chain_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
