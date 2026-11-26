<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedBigInteger('application_id');
            $table->string('document_type', 100);
            $table->string('original_filename', 255);
            $table->string('storage_disk', 50)->default('private');
            $table->string('storage_key', 500);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->string('verification_status', 30)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->foreign(['institution_id', 'application_id'], 'admission_application_documents_application_foreign')
                ->references(['institution_id', 'id'])->on('applications')->cascadeOnDelete();
            $table->unique(['institution_id', 'id'], 'admission_application_documents_institution_id_unique');

            $table->index(['institution_id', 'application_id', 'document_type'], 'admission_documents_type_index');
            $table->index(['institution_id', 'verification_status'], 'admission_documents_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_application_documents');
    }
};
