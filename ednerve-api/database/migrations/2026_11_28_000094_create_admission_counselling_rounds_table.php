<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_counselling_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('intake_id');
            $table->string('name', 180);
            $table->unsignedSmallInteger('round_number');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 25)->default('draft');
            $table->timestamps();
            $table->foreign(['institution_id', 'intake_id'], 'admission_counselling_rounds_intake_foreign')
                ->references(['institution_id', 'id'])->on('admission_intakes')->restrictOnDelete();

            // Required parent key for institution-scoped composite foreign keys
            // (e.g. admission_seat_allocations -> counselling_rounds).
            $table->unique(
                ['institution_id', 'id'],
                'admission_counselling_rounds_institution_id_unique'
            );

            $table->unique(['intake_id', 'round_number'], 'admission_counselling_rounds_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_counselling_rounds');
    }
};
