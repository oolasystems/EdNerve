<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('payment_order_id');
            $table->string('provider_transaction_reference', 180)->nullable();
            $table->string('event_reference', 180)->nullable();
            $table->string('status', 30);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('INR');
            $table->json('verified_payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->foreign(['institution_id', 'payment_order_id'], 'admission_payment_transactions_order_foreign')
                ->references(['institution_id', 'id'])->on('admission_payment_orders')->cascadeOnDelete();
            $table->unique(['institution_id', 'event_reference'], 'admission_payment_transactions_event_unique');
            $table->index(['institution_id', 'payment_order_id', 'status'], 'admission_payment_transactions_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_payment_transactions');
    }
};
