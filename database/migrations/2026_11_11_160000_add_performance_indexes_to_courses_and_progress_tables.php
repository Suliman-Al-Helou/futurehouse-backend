<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->index(['status', 'level', 'created_at']);
            $table->index('instructor_id');
        });

        Schema::table('user_lesson_progress', function (Blueprint $table) {
            $table->index(['user_id', 'completed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['status', 'level', 'created_at']);
            $table->dropIndex(['instructor_id']);
        });

        Schema::table('user_lesson_progress', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'completed']);
        });
    }
};
