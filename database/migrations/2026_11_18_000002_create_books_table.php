<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * UniBook's own book catalogue: step 1 of the book lookup
     * (books table -> Google Books -> Open Library -> manual entry).
     * Every book identified or typed in by a seller is kept here so it is found
     * immediately next time, including local books such as UiTM modules (source = manual).
     */
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('isbn', 20)->nullable()->unique();
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('edition', 50)->nullable();
            $table->string('publisher')->nullable();
            $table->string('published_year', 4)->nullable();
            // URL (https://...) or file path (uploads/covers/...) only, never the image itself.
            $table->string('cover_url')->nullable();
            $table->string('cover_source', 20)->nullable(); // google_books, open_library, seller_photo (null = placeholder)
            $table->string('source', 20);                    // google_books, open_library, manual
            $table->timestamps();

            $table->index('title');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->foreignId('book_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('cover_source', 20)->nullable()->after('cover_url');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('book_id');
            $table->dropColumn('cover_source');
        });
        Schema::dropIfExists('books');
    }
};
