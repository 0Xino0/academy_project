<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Roles
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'api']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'student', 'guard_name' => 'api']);

        // Create Admin
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'), // or generic password
            'first_name' => 'Admin',
            'last_name' => 'User',
            'national_id' => '0000000000',
            'phone' => '09123456789',
        ]);
        $admin->assignRole('admin');

        // Create Teachers (users who will become teachers via TeacherSeeder or manually)
        $teachers = User::factory()->count(3)->create();
        foreach ($teachers as $teacher) {
             $teacher->assignRole('teacher');
        }

        // Create Students
        $students = User::factory()->count(10)->create();
        foreach ($students as $student) {
             $student->assignRole('student');
        }
    }
}
