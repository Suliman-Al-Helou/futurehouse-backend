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
            'name' => 'Suliman',
            'email' => 'sulimanalhellou@gmail.com',
            'password' => Hash::make('Suliman123'),
            'is_active' => true,
        ]);
        $role = Role::findOrCreate('admin', 'web');
        $admin->assignRole($role);
    }
}
// php artisan tinker --execute="User::create(['name'=>'Admin','email'=>'s@gmail.com','password'=>bcrypt('123'),'role'=>'admin']);"

// بدي صفحة بعد ما يقوم ال admin بقبول المستخدم اي يسمح له برؤية الكورس الذي قام ب التسجيل فيه يعرض صفحة فيها
// الفيديوهات والتاسكات بدي اعرضهم
// App\Models\User::where('email', 's@gmail.com')->update(['role' => 'admin']);
