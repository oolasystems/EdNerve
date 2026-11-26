<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_form_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('form_version_id');
            $table->string('section_key', 100);
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_required')->default(false);
            $table->timestamps();
            // Required by MySQL/MariaDB for composite foreign keys referencing (institution_id, id).
            $table->unique(['institution_id', 'id'], 'admission_form_sections_institution_id_unique');
            $table->foreign(['institution_id', 'form_version_id'], 'admission_form_sections_version_foreign')
                ->references(['institution_id', 'id'])->on('admission_form_versions')->cascadeOnDelete();
            $table->unique(['form_version_id', 'section_key'], 'admission_form_sections_key_unique');
            $table->index(['institution_id', 'form_version_id', 'sort_order'], 'admission_form_sections_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_form_sections');
    }
};
