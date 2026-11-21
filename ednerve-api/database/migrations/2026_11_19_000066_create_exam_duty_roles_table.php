<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_duty_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('min_qualification', 255)->nullable();
            $table->unsignedSmallInteger('duty_weight')->default(1);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['institution_id', 'code'], 'edr_institution_code_unique');
            $table->unique(['institution_id', 'id'], 'edr_institution_id_unique');
            $table->index(['institution_id', 'active'], 'edr_institution_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_duty_roles');
    }
};
