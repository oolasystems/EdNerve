<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rooms within a centre (theory/lab/viva/server_room).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('centre_id');
            $table->string('code', 20);
            $table->string('name', 80);
            $table->string('room_type', 20)->default('theory'); // theory | lab | viva | server_room
            $table->unsignedInteger('capacity')->default(30);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign(['institution_id', 'centre_id'], 'exam_rooms_centre_foreign')
                ->references(['institution_id', 'id'])->on('exam_centres')->cascadeOnDelete();
            $table->unique(['institution_id', 'centre_id', 'code'], 'exam_rooms_centre_code_unique');
            $table->unique(['institution_id', 'id'], 'exam_rooms_institution_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_rooms');
    }
};
