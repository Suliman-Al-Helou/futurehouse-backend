<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('cover_image')->nullable();
            $table->enum('level', ['beginner', 'intermediate', 'advanced'])->default('beginner');
            $table->enum('status', ['draft', 'published', 'coming_soon'])->default('draft');
            $table->integer('total_duration')->default(0);
            $table->json('what_you_learn')->nullable();
            $table->json('requirements')->nullable();
            $table->json('target_audience')->nullable();
            $table->decimal('rating', 2, 1)->default(4.5);
            $table->boolean('is_popular')->default(false);
            $table->decimal('price', 8, 2)->default(0);
            $table->boolean('is_public')->default(false);
            $table->foreignId('instructor_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('students_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
