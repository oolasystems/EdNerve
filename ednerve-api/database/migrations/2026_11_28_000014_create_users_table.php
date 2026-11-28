<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform/institution accounts. NULL institution_id = platform account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('account_type', 20)->default('institution'); // platform | institution
            $table->foreignId('institution_id')->nullable()->constrained('institutions')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('email', 150)->unique('users_email_unique'); // globally unique
            $table->string('phone', 30)->nullable();
            $table->string('password');
            $table->string('profile_photo', 255)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            // Supports composite FKs from user_role_assignments.
            $table->unique(['institution_id', 'id'], 'users_institution_id_unique');
            $table->index(['institution_id', 'status'], 'users_institution_status_index');
            $table->index(['account_type', 'status'], 'users_account_type_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
