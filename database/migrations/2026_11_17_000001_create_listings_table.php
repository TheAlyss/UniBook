<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // seller
            $table->string('title');
            $table->string('author');
            $table->string('edition', 50)->nullable();
            $table->string('publisher')->nullable();
            $table->string('isbn', 20)->nullable();       // filled by barcode / Google Books later
            $table->string('cover_url')->nullable();      // filled by Google Books later
            $table->string('course_code', 10)->index();
            $table->decimal('price', 8, 2);
            $table->string('condition', 20);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('available'); // available, reserved, sold
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
