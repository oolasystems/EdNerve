<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-student dues from the allocation engine; paid/partial/waived tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_fee_dues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('programme_id')->nullable()->restrictOnDelete();
            $table->foreignId('academic_session_id')->nullable()->restrictOnDelete();
            $table->foreignId('fee_head_id')->constrained('fee_heads')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('pending'); // pending | partial | paid | waived
            $table->text('waived_reason')->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'student_profile_id', 'status'], 'sp_dues_status_index');
            $table->index(['institution_id', 'academic_session_id', 'status'], 'sp_dues_session_status_index');
            $table->index(['institution_id', 'fee_head_id', 'status'], 'sp_dues_fee_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_fee_dues');
    }
};
