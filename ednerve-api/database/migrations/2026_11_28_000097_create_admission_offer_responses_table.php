<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_offer_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('offer_id');
            $table->string('response', 25);
            $table->text('remarks')->nullable();
            $table->timestamp('responded_at');
            $table->timestamps();
            $table->foreign(['institution_id', 'offer_id'], 'admission_offer_responses_offer_foreign')
                ->references(['institution_id', 'id'])->on('admission_offers')->cascadeOnDelete();
            $table->index(['institution_id', 'offer_id', 'responded_at'], 'admission_offer_responses_history_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_offer_responses');
    }
};
