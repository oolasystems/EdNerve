<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User <-> role bindings, institution-scoped, optionally unit-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('academic_unit_id')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 20)->default('active');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();

            $table->foreign(['institution_id', 'user_id'], 'ura_institution_user_foreign')
                ->references(['institution_id', 'id'])->on('users')->cascadeOnDelete();
            $table->foreign('role_id', 'ura_role_foreign')->references('id')->on('roles')->restrictOnDelete();
            $table->foreign(['institution_id', 'academic_unit_id'], 'ura_institution_org_unit_foreign')
                ->references(['institution_id', 'id'])->on('academic_units')->restrictOnDelete();
            $table->index(['institution_id', 'user_id'], 'ura_institution_user_index');
            $table->index(['institution_id', 'role_id'], 'ura_institution_role_index');
            $table->index(['institution_id', 'academic_unit_id'], 'ura_institution_academic_unit_index');
            $table->index(['institution_id', 'status'], 'ura_institution_status_index');
            $table->index(['user_id', 'is_primary', 'status'], 'ura_user_primary_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_assignments');
    }
};
