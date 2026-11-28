<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_form_field_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('field_id');
            $table->string('option_value', 150);
            $table->string('option_label', 180);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign(['institution_id', 'field_id'], 'admission_form_options_field_foreign')
                ->references(['institution_id', 'id'])->on('admission_form_fields')->cascadeOnDelete();
            $table->unique(['field_id', 'option_value'], 'admission_form_options_value_unique');
            $table->index(['institution_id', 'field_id', 'sort_order'], 'admission_form_options_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_form_field_options');
    }
};
