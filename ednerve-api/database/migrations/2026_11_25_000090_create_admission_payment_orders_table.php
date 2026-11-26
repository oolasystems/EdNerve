<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_payment_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('application_id');
            $table->string('purpose', 40);
            $table->string('order_number', 80);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('INR');
            $table->string('status', 30)->default('initiated');
            $table->string('provider', 80)->nullable();
            $table->string('provider_order_reference', 180)->nullable();
            $table->string('idempotency_key', 100)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->foreign(['institution_id', 'application_id'], 'admission_payment_orders_application_foreign')
                ->references(['institution_id', 'id'])->on('applications')->restrictOnDelete();
            // Required as the referenced key for institution-scoped composite foreign keys.
            $table->unique(['institution_id', 'id'], 'admission_payment_orders_institution_id_unique');
            $table->unique(['institution_id', 'order_number'], 'admission_payment_orders_number_unique');
            $table->index(['institution_id', 'application_id', 'status'], 'admission_payment_orders_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_payment_orders');
    }
};
