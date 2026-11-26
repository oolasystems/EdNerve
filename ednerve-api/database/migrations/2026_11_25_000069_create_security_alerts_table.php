<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S3 anomaly log: repeated failures, audit-chain breaks, invalid checkpoints.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained('institutions')->nullOnDelete();
            $table->string('kind', 60);     // auth.repeated_failures | audit.chain_broken | audit.checkpoint_invalid
            $table->string('severity', 10); // info | warning | high | critical
            $table->string('message', 255);
            $table->json('context')->nullable();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'acknowledged_at', 'created_at'], 'security_alerts_inst_ack_index');
            $table->index(['kind', 'created_at'], 'security_alerts_kind_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_alerts');
    }
};
