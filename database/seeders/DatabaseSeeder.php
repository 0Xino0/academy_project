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
        // Call the RolePermissionSeeder to set up roles and permissions first
        $this->call(RolePermissionSeeder::class);

        // Create Admin
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'), // or generic password
            'first_name' => 'Admin',
            'last_name' => 'User',
            'national_id' => '0000000000',
            'phone' => '09123456789',
        ]);
        $admin->assignRole('manager');

        // Create 13 users who will automatically get randomly assigned teacher or student roles
        User::factory()->count(13)->create();

        // Call seeders to create corresponding Teacher and Student profile records
        $this->call([
            TeacherSeeder::class,
            StudentSeeder::class,
        ]);
    }
}
