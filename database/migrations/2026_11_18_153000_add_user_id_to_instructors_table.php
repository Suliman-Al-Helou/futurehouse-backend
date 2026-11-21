<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();
        });

        // Seed initial instructor role matching the established pattern in 2026_11_04_140000_drop_role_column_from_users_table
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('instructor', 'web');
    }

    public function down(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn('user_id');
        });

        $role = Role::where('name', 'instructor')->where('guard_name', 'web')->first();
        if ($role && $role->users()->count() === 0) {
            $role->delete();
        }
    }
};
