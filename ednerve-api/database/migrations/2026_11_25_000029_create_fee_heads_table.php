<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fee head catalogue (Tuition, Lab, Library, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_heads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['institution_id', 'code'], 'fee_heads_institution_code_unique');
            $table->unique(['institution_id', 'id'], 'fee_heads_institution_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_heads');
    }
};
