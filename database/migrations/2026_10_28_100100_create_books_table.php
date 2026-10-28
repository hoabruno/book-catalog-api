<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            // An author with books cannot be deleted by accident.
            $table->foreignId('author_id')->constrained()->restrictOnDelete();
            $table->char('isbn', 13)->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->string('format', 16);
            $table->char('language', 2)->default('fr');
            $table->smallInteger('published_year')->nullable();
            $table->unsignedSmallInteger('page_count')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Covers the catalog listing: filter by author or format, newest first.
            $table->index(['author_id', 'published_year']);
            $table->index(['format', 'published_year']);

            // Full-text search on MySQL; other drivers fall back to LIKE (see BookQuery).
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText(['title', 'summary']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
