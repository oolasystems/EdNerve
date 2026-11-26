<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academic calendar: holidays, exam windows, registration/fee deadlines, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->string('title', 200);
            $table->string('event_type', 30)->default('other'); // holiday|exam|registration|fee_deadline|class|other
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_all_day')->default(true);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->foreign(['institution_id', 'academic_session_id'], 'ace_institution_session_foreign')
                ->references(['institution_id', 'id'])->on('academic_sessions')->restrictOnDelete();

            $table->unique(['institution_id', 'id'], 'ace_institution_id_unique');
            $table->index(['institution_id', 'academic_session_id', 'starts_on'], 'ace_session_starts_index');
            $table->index(['institution_id', 'event_type', 'starts_on'], 'ace_type_starts_index');
            $table->index(['institution_id', 'status'], 'ace_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_calendar_events');
    }
};
