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
        Schema::create('instructors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('title')->nullable();
            $table->text('bio')->nullable();
            $table->text('avatar_url')->nullable();
            $table->string('specializations')->nullable();
            $table->integer('years_experience')->default(0);
            $table->json('achievements')->nullable();
            $table->string('cover_url')->nullable();
            $table->decimal('rating', 3, 1)->default(4.5);
            $table->integer('total_reviews')->default(0);
            $table->integer('students_count')->default(0);
            $table->string('twitter')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('youtube')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instructors');
    }
};
