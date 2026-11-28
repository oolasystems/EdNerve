<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programme elective baskets with capacity + required choices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elective_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('programme_id');
            $table->unsignedTinyInteger('semester');
            $table->string('code', 40);
            $table->string('name', 200);
            $table->unsignedTinyInteger('required_choices')->default(1);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->foreign(['institution_id', 'programme_id'], 'eg_institution_programme_foreign')
                ->references(['institution_id', 'id'])->on('programmes')->cascadeOnDelete();
            $table->unique(['institution_id', 'programme_id', 'semester', 'code'], 'elective_groups_unique');
            $table->unique(['institution_id', 'id'], 'elective_groups_institution_id_unique');
            $table->unique(['institution_id', 'programme_id', 'id'], 'eg_institution_programme_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elective_groups');
    }
};
