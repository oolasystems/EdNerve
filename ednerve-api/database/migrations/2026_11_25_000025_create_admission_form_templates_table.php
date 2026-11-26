<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_form_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('programme_id')->nullable();
            $table->unsignedBigInteger('intake_id')->nullable();
            $table->string('code', 80);
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->unique(['institution_id', 'code'], 'admission_form_templates_institution_code_unique');
            // Required by MySQL/MariaDB for the composite FK from admission_form_versions.
            $table->unique(['institution_id', 'id'], 'admission_form_templates_institution_id_unique');
            $table->foreign(['institution_id', 'programme_id'], 'admission_form_templates_programme_foreign')
                ->references(['institution_id', 'id'])->on('programmes')->restrictOnDelete();
            $table->foreign(['institution_id', 'intake_id'], 'admission_form_templates_intake_foreign')
                ->references(['institution_id', 'id'])->on('admission_intakes')->restrictOnDelete();
            $table->index(['institution_id', 'status'], 'admission_form_templates_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_form_templates');
    }
};
