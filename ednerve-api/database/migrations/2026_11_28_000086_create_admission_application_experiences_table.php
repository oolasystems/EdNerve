<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_application_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('application_id');
            $table->string('employer_name', 180);
            $table->string('designation', 150)->nullable();
            $table->string('employment_type', 40)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->foreign(['institution_id', 'application_id'], 'admission_application_experiences_application_foreign')
                ->references(['institution_id', 'id'])->on('applications')->cascadeOnDelete();

            $table->index(['institution_id', 'application_id', 'sort_order'], 'admission_experiences_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_application_experiences');
    }
};
