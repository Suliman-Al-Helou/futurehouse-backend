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
    Schema::table('instructors', function (Blueprint $table) {
        $table->json('achievements')->nullable();
        $table->string('cover_url')->nullable();
        $table->decimal('rating', 3, 1)->default(4.5);
        $table->integer('total_reviews')->default(0);
        $table->integer('students_count')->default(0);
                $table->string('twitter')->nullable();
        $table->string('linkedin')->nullable();
        $table->string('youtube')->nullable();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            //
        });
    }
};
