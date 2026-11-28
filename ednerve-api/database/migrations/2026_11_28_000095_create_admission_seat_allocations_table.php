<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_seat_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('counselling_round_id');
            $table->unsignedBigInteger('application_id');
            $table->string('seat_category', 80)->nullable();
            $table->string('allocation_status', 30)->default('offered');
            $table->timestamp('allocated_at')->nullable();
            $table->timestamp('response_deadline')->nullable();
            $table->timestamps();
            $table->foreign(['institution_id', 'counselling_round_id'], 'admission_seat_allocations_round_foreign')
                ->references(['institution_id', 'id'])->on('admission_counselling_rounds')->cascadeOnDelete();
            $table->foreign(['institution_id', 'application_id'], 'admission_seat_allocations_application_foreign')
                ->references(['institution_id', 'id'])->on('applications')->restrictOnDelete();
            $table->unique(['counselling_round_id', 'application_id'], 'admission_seat_allocations_application_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_seat_allocations');
    }
};
