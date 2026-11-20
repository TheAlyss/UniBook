<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Module C (Orders): an order is a booking of one listing, paid in cash at the meetup.
     * The meetup (place + time, from OpenStreetMap) lives in its own table.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Listing may be deleted later, so keep a snapshot of the title.
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('listing_title');
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 8, 2); // price agreed, paid in cash at the meetup
            $table->string('status', 20)->default('pending'); // pending, completed, cancelled
            $table->string('note', 255)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Buyer rates the seller after the order is completed.
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('review', 500)->nullable();
            $table->timestamp('rated_at')->nullable();

            $table->timestamps();
        });

        Schema::create('meetups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('point', 50);          // key from config/unibook.php
            $table->string('name');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->dateTime('meetup_time');

            // Where the buyer starts from (browser location or a place found with Nominatim).
            $table->string('from_place')->nullable();
            $table->decimal('from_lat', 10, 7)->nullable();
            $table->decimal('from_lng', 10, 7)->nullable();

            // Last route calculated with OSRM.
            $table->unsignedInteger('distance_m')->nullable();
            $table->unsignedInteger('duration_s')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetups');
        Schema::dropIfExists('orders');
    }
};
