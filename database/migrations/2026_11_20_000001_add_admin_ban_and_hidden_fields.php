<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin area: admins (users.is_admin), banned users (users.banned_at)
 * and listings hidden by an admin (listings.hidden_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
            $table->timestamp('banned_at')->nullable()->after('is_admin');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('hidden_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'banned_at']);
        });
    }
};
