<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_merit_list_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('merit_list_id');
            $table->unsignedBigInteger('application_id');
            $table->unsignedInteger('rank');
            $table->decimal('aggregate_score', 12, 4)->nullable();
            $table->string('selection_status', 30)->default('considered');
            $table->json('score_breakdown')->nullable();
            $table->timestamps();
            $table->foreign(['institution_id', 'merit_list_id'], 'admission_merit_entries_list_foreign')
                ->references(['institution_id', 'id'])->on('admission_merit_lists')->cascadeOnDelete();
            $table->foreign(['institution_id', 'application_id'], 'admission_merit_entries_application_foreign')
                ->references(['institution_id', 'id'])->on('applications')->restrictOnDelete();
            $table->unique(['merit_list_id', 'application_id'], 'admission_merit_entries_application_unique');
            $table->unique(['merit_list_id', 'rank'], 'admission_merit_entries_rank_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_merit_list_entries');
    }
};
