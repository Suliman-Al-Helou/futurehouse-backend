<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::forceCreate([
            'name' => env('ADMIN_NAME', 'Suliman'),
            'email' => env('ADMIN_EMAIL', 'sulimanalhellou@gmail.com'),
            'password' => Hash::make(env('ADMIN_PASSWORD', 'Suliman123')),
            'is_active' => true,
        ]);
        $role = Role::findOrCreate('admin', 'web');
        $admin->assignRole($role);
        Role::findOrCreate('instructor', 'web');
        Role::findOrCreate('student', 'web');

        $instructor = Instructor::firstOrCreate(
            ['name' => 'المهندس سليمان الحلو'],
            [
                'title' => 'كبير مهندسي البرمجيات',
                'bio' => 'مدرب معتمد في تطوير تطبيقات الويب والأنظمة السحابية',
                'years_experience' => 8,
                'rating' => 4.9,
                'students_count' => 1240,
            ]
        );

        $this->call([
            CourseSeeder::class,
        ]);

        Course::whereNull('instructor_id')->update(['instructor_id' => $instructor->id]);
    }
}
// App\Models\User::where('email', 's@gmail.com')->update(['role' => 'admin']);

