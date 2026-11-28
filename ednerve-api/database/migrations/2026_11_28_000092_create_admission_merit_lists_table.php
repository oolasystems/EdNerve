<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_merit_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('intake_id');
            $table->string('name', 180);
            $table->unsignedSmallInteger('round_number')->default(1);
            $table->string('list_type', 30)->default('provisional');
            $table->string('status', 25)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['institution_id', 'id'], 'admission_merit_lists_institution_id_unique');
            $table->foreign(['institution_id', 'intake_id'], 'admission_merit_lists_intake_foreign')
                ->references(['institution_id', 'id'])->on('admission_intakes')->restrictOnDelete();
            $table->index(['institution_id', 'intake_id', 'status'], 'admission_merit_lists_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_merit_lists');
    }
};
