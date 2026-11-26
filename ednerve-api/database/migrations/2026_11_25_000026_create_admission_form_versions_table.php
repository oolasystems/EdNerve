<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('template_id');
            $table->unsignedInteger('version_number');
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign(['institution_id', 'template_id'], 'admission_form_versions_template_foreign')
                ->references(['institution_id', 'id'])->on('admission_form_templates')->cascadeOnDelete();
            $table->unique(['template_id', 'version_number'], 'admission_form_versions_template_version_unique');
            $table->unique(['institution_id', 'id'], 'admission_form_versions_institution_id_unique');
            $table->index(['institution_id', 'status'], 'admission_form_versions_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_form_versions');
    }
};
