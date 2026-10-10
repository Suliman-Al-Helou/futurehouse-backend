<?php

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
        Schema::table('zoom_meetings', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')->default(60)->after('starts_at');
            $table->foreignId('course_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zoom_meetings', function (Blueprint $table) {
            //
        });
    }
};
