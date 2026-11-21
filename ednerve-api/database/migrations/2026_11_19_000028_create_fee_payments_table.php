<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receipt ledger; manual collection only (gateway deferred).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->string('receipt_number', 40)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 20)->default('cash'); // cash | cheque | dd | upi | card | bank_transfer | other
            $table->string('reference', 100)->nullable(); // cheque/DD/UTR ref
            $table->dateTime('paid_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'receipt_number'], 'fee_payments_institution_receipt_unique');
            $table->index(['institution_id', 'student_profile_id', 'paid_at'], 'fee_payments_student_paid_index');
            $table->index(['institution_id', 'paid_at'], 'fee_payments_paid_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
    }
};
