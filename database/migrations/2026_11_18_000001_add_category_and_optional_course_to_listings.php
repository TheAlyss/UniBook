<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * UniBook is now a general second-hand book marketplace:
     * - every listing has a category (Textbook, Reference, Novel, Religious, Others)
     * - the course code is optional (only for course-related books)
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->string('course_code', 10)->nullable()->change();
            $table->string('category', 20)->default('Others')->after('course_code')->index();
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
            $table->string('course_code', 10)->nullable(false)->change();
        });
    }
};
