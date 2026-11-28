<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily integrity checkpoints (HMAC anchors for the audit hash chain).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->date('for_date');
            $table->unsignedBigInteger('record_count');
            $table->string('last_entry_hash', 64);
            $table->string('signature', 128); // HMAC-SHA256 over checkpoint payload
            $table->timestamps();

            $table->unique('for_date', 'checkpoints_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log_checkpoints');
    }
};
