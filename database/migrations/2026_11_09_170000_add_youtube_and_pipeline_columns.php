<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. إضافة youtube_playlist_id للكورسات
        Schema::table('courses', function (Blueprint $table) {
            $table->string('youtube_playlist_id')->nullable()->after('is_public');
        });

        // 2. إضافة status و temp_file_path لأصول الفيديوهات
        Schema::table('video_assets', function (Blueprint $table) {
            $table->string('status')->default('ready')->after('type'); // 'uploading', 'processing', 'ready', 'failed'
            $table->string('temp_file_path')->nullable()->after('status');
        });

        // 3. جدول إعدادات المنصة لتخزين توكنات يوتيوب والقناة
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');

        Schema::table('video_assets', function (Blueprint $table) {
            $table->dropColumn(['status', 'temp_file_path']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('youtube_playlist_id');
        });
    }
};
