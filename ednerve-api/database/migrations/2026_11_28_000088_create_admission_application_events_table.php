<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_application_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('application_id');
            $table->string('event_type', 80);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->text('remarks')->nullable();
            $table->string('actor_type', 30)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->foreign(['institution_id', 'application_id'], 'admission_application_events_application_foreign')
                ->references(['institution_id', 'id'])->on('applications')->cascadeOnDelete();

            $table->index(['institution_id', 'application_id', 'occurred_at'], 'admission_events_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_application_events');
    }
};
