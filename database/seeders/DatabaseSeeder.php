<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // $this->call([
        //     CourseSeeder::class,
        // ]);
        $admin = User::forceCreate([
            'name' => env('ADMIN_NAME', 'Suliman'),
            'email' => env('ADMIN_EMAIL', 'sulimanalhellou@gmail.com'),
            'password' => Hash::make(env('ADMIN_PASSWORD', 'Suliman123')),
            'is_active' => true,
        ]);
        $role = Role::findOrCreate('admin', 'web');
        $admin->assignRole($role);
    }
}
// App\Models\User::where('email', 's@gmail.com')->update(['role' => 'admin']);
