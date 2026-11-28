<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('application_id');
            $table->string('offer_number', 80);
            $table->string('offer_type', 25)->default('provisional');
            $table->string('status', 25)->default('issued');
            $table->text('conditions')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign(['institution_id', 'application_id'], 'admission_offers_application_foreign')
                ->references(['institution_id', 'id'])->on('applications')->restrictOnDelete();

            // Required parent key for institution-scoped offer response foreign keys.
            $table->unique(
                ['institution_id', 'id'],
                'admission_offers_institution_id_unique'
            );
            $table->unique(['institution_id', 'offer_number'], 'admission_offers_number_unique');
            $table->index(['institution_id', 'application_id', 'status'], 'admission_offers_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_offers');
    }
};
