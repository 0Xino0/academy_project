<?php

namespace Database\Factories;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Teacher>
 */
class TeacherFactory extends Factory
{

    protected $model = Teacher::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'salary' => fake()->numerify('###000'),
            'bio' => fake()->sentence(),
            'resume' => fake()->sentence(),
            'degree' => fake()->randomElement(['bachelor','master','phd']),
        ];
    }
}
