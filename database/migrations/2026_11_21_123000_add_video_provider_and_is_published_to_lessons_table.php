<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('video_provider')->default('youtube')->after('video_id');
            $table->boolean('is_published')->default(true)->after('is_preview');
            $table->index(['section_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['section_id', 'is_published']);
            $table->dropColumn(['video_provider', 'is_published']);
        });
    }
};
