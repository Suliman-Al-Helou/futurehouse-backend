<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 250);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role', 20)->default('student');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

        });

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('student','admin'))");
            DB::statement('ALTER TABLE users ADD CONSTRAINT users_name_not_blank CHECK (length(btrim(name)) > 0)');
            DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_lowercase CHECK (email = lower(email))');
            // Unique only among non-deleted users, so an archived email can register again.
            DB::statement('CREATE UNIQUE INDEX users_email_active_unique ON users (email) WHERE deleted_at IS NULL');
        } elseif ($driver === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX users_email_active_unique ON users (email) WHERE deleted_at IS NULL');
        } else {
            try {
                DB::statement('CREATE UNIQUE INDEX users_email_active_unique ON users (email, (IF(deleted_at IS NULL, 1, NULL)))');
            } catch (Throwable) {
                Schema::table('users', function (Blueprint $table) {
                    $table->unique('email');
                });
            }
        }

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');                 // creates index on (tokenable_type, tokenable_id)
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');
    }
};
