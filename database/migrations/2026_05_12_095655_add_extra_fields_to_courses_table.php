<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('instructor_name')->default('فريق تِقنيار')->after('target_audience');
            $table->decimal('rating', 2, 1)->default(4.5)->after('instructor_name');
            $table->boolean('is_popular')->default(false)->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['instructor_name', 'rating', 'is_popular']);
        });
    }
};