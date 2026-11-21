<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admission window per programme per session; open/close + capacity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('programme_id');
            $table->unsignedBigInteger('academic_session_id');
            $table->string('name', 150);
            $table->unsignedInteger('seat_capacity')->default(60);
            $table->date('open_date');
            $table->date('close_date');
            $table->string('status', 20)->default('open'); // open | closed
            $table->decimal('application_fee', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'programme_id', 'academic_session_id'], 'admission_intakes_institution_programme_session_unique');
            $table->unique(['institution_id', 'id'], 'admission_intakes_institution_id_unique');
            $table->foreign(['institution_id', 'programme_id'], 'intake_institution_programme_foreign')
                ->references(['institution_id', 'id'])->on('programmes')->restrictOnDelete();
            $table->foreign(['institution_id', 'academic_session_id'], 'intake_institution_session_foreign')
                ->references(['institution_id', 'id'])->on('academic_sessions')->restrictOnDelete();
            $table->index(['institution_id', 'status'], 'admission_intakes_institution_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_intakes');
    }
};
