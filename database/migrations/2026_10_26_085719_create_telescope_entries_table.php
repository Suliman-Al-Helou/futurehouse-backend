<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return config('telescope.storage.database.connection');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // حماية الجدول من التكرار والاصطدام ببيئة الـ Test
        if (! Schema::hasTable('telescope_entries')) {
            Schema::create('telescope_entries', function (Blueprint $table) {
                $table->bigIncrements('sequence');
                $table->uuid('uuid')->unique();
                $table->uuid('batch_id')->index();
                $table->string('family_hash')->nullable()->index();
                $table->boolean('should_display_on_index')->default(true);
                $table->string('type', 20)->index();
                $table->longText('content');
                $table->dateTime('created_at')->nullable()->index();
            });
        }

        if (! Schema::hasTable('telescope_entries_tags')) {
            Schema::create('telescope_entries_tags', function (Blueprint $table) {
                $table->uuid('entry_uuid');
                $table->string('tag')->index();

                $table->primary(['entry_uuid', 'tag']);
                $table->foreign('entry_uuid')
                    ->references('uuid')
                    ->on('telescope_entries')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('telescope_monitoring')) {
            Schema::create('telescope_monitoring', function (Blueprint $table) {
                $table->string('tag')->primary();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection($this->getConnection());

        $schema->dropIfExists('telescope_entries_tags');
        $schema->dropIfExists('telescope_entries');
        $schema->dropIfExists('telescope_monitoring');
    }
};
