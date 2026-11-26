<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('section_id');
            $table->string('field_key', 120);
            $table->string('label', 180);
            $table->string('field_type', 30);
            $table->text('help_text')->nullable();
            $table->json('validation_rules')->nullable();
            $table->json('visibility_rule')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_applicant_editable')->default(true);
            $table->boolean('is_sensitive')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            // Required by MySQL/MariaDB for composite foreign keys referencing (institution_id, id).
            $table->unique(['institution_id', 'id'], 'admission_form_fields_institution_id_unique');
            $table->foreign(['institution_id', 'section_id'], 'admission_form_fields_section_foreign')
                ->references(['institution_id', 'id'])->on('admission_form_sections')->cascadeOnDelete();
            $table->unique(['section_id', 'field_key'], 'admission_form_fields_key_unique');
            $table->index(['institution_id', 'section_id', 'sort_order'], 'admission_form_fields_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_form_fields');
    }
};
