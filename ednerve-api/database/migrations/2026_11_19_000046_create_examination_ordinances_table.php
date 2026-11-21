<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-institution examination ordinance: grading bands, pass criteria, attendance threshold.
 * Exactly one is_default per institution (application-enforced transaction).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examination_ordinances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->json('grading_bands'); // [{"min":90,"grade":"O","points":10}, ...] sorted by min desc
            $table->json('pass_criteria'); // {"overall_min_pct":40,"external_min_pct":0,"condonation_pct":0,"max_backlogs":null}
            $table->decimal('attendance_threshold', 5, 2)->default(75.00);
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active'); // active | archived
            $table->timestamps();

            $table->unique(['institution_id', 'code'], 'ordinances_code_unique');
            $table->unique(['institution_id', 'id'], 'ordinances_institution_id_unique');
            $table->index(['institution_id', 'is_default'], 'ordinances_default_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examination_ordinances');
    }
};
