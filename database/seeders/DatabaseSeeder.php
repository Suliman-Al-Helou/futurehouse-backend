<?php

namespace Database\Seeders;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CourseSeeder::class,
        ]);
User::create([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => Hash::make('admin123'),
    'role' => 'admin',  // أو 'is_admin' => 1
]);
    }
}
// php artisan tinker --execute="User::create(['name'=>'Admin','email'=>'admin@gmail.com','password'=>bcrypt('admin1234'),'role'=>'admin']);"

// بدي صفحة بعد ما يقوم ال admin بقبول المستخدم اي يسمح له برؤية الكورس الذي قام ب التسجيل فيه يعرض صفحة فيها 
// الفيديوهات والتاسكات بدي اعرضهم