<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // لقاءات Zoom — مفصولة تمامًا عن الكورسات/الدروس
        Schema::create('zoom_meetings', function (Blueprint $table) {
            $table->id();
            $table->string('course_name');          // يدخله الأدمن يدويًا
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('zoom_link');
            $table->dateTime('starts_at')->index();
            $table->timestamps();
        });

        Schema::create('zoom_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zoom_meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('attended_at')->useCurrent();
            $table->unique(['zoom_meeting_id', 'user_id']); // الضغط المتكرر ما يكرر السجل
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoom_attendances');
        Schema::dropIfExists('zoom_meetings');
    }
};
