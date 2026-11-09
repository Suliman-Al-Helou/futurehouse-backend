<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('zoom_meeting_id')->nullable()->constrained('zoom_meetings')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('provider')->default('youtube');
            $table->string('provider_video_id');
            $table->integer('duration_seconds')->default(0)->nullable();
            $table->string('type')->default('recording'); // 'recording' | 'lesson'
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['course_id', 'created_at']);
            $table->index('zoom_meeting_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_assets');
    }
};

