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
        Schema::table('tasks', function (Blueprint $table) {
            $table->index('lesson_id', 'tasks_lesson_id_index');
        });

        Schema::table('zoom_meetings', function (Blueprint $table) {
            $table->index('course_id', 'zoom_meetings_course_id_index');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'enrollments_user_id_status_index');
            $table->index(['course_id', 'status'], 'enrollments_course_id_status_index');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->index(['section_id', 'order'], 'lessons_section_id_order_index');
            $table->index('video_id', 'lessons_video_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_lesson_id_index');
        });

        Schema::table('zoom_meetings', function (Blueprint $table) {
            $table->dropIndex('zoom_meetings_course_id_index');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('enrollments_user_id_status_index');
            $table->dropIndex('enrollments_course_id_status_index');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex('lessons_section_id_order_index');
            $table->dropIndex('lessons_video_id_index');
        });
    }
};
