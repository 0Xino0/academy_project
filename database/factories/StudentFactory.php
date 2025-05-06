<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{

    protected $model = Student::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent1_name' => fake()->name(),
            'parent1_phone' => fake()->regexify('(0|\+98)(9)[0-9]{9}'),
            'parent2_phone' => fake()->regexify('(0|\+98)(9)[0-9]{9}'),
            'parent2_name' => fake()->name()
        ];
    }
}
