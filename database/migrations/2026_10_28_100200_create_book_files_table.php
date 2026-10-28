<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            // Name of the StorageRegistry backend holding the primary copy.
            $table->string('backend', 32);
            // Backends holding a verified copy, filled by ReplicateBookFile.
            $table->json('replicas');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size');
            $table->char('checksum', 64);
            $table->timestamps();

            $table->unique(['book_id', 'checksum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_files');
    }
};
