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
    Schema::table('courses', function (Blueprint $table) {
        $table->json('requirements')->nullable()->change();
        $table->json('target_audience')->nullable()->change();
    });
}

public function down(): void
{
    Schema::table('courses', function (Blueprint $table) {
        $table->text('requirements')->nullable()->change();
        $table->text('target_audience')->nullable()->change();
    });
}
};
