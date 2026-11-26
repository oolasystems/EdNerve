<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_clarification_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('application_id');
            $table->text('request_text');
            $table->json('requested_fields')->nullable();
            $table->string('status', 25)->default('open');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('applicant_response')->nullable();
            $table->timestamps();
            $table->foreign(['institution_id', 'application_id'], 'admission_clarification_requests_application_foreign')
                ->references(['institution_id', 'id'])->on('applications')->cascadeOnDelete();

            $table->index(['institution_id', 'application_id', 'status'], 'admission_clarifications_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_clarification_requests');
    }
};
