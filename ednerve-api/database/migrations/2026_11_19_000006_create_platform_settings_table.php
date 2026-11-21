<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global platform configuration (typed key-value; JSON values).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('group', 40)->index();
            $table->string('type', 20)->default('string'); // string|boolean|integer|json
            $table->string('label', 160);
            $table->text('description')->nullable();
            $table->json('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
