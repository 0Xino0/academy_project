<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $studentsInComplete = User::role('student')->whereDoesntHave('student')->get();

        foreach($studentsInComplete as $user)
        {
            Student::factory()->create([
                'user_id' => $user->id
            ]);
        }
    }
}
