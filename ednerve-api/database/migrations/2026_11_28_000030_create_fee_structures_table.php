<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-programme head x session fee rows (frequency: annual|semester|one_time).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained('programmes')->restrictOnDelete();
            $table->foreignId('academic_session_id')->nullable()->constrained('academic_sessions')->restrictOnDelete();
            $table->foreignId('fee_head_id')->constrained('fee_heads')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('frequency', 20)->default('annual');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['institution_id', 'programme_id', 'academic_session_id', 'fee_head_id', 'frequency'], 'fee_structures_unique');
            $table->unique(['institution_id', 'id'], 'fee_structures_institution_id_unique');
            $table->index(['institution_id', 'academic_session_id', 'status'], 'fee_structures_session_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structures');
    }
};
