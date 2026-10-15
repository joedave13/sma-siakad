<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('??##')),
            'name' => fake()->randomElement([
                'Matematika',
                'Bahasa Indonesia',
                'Bahasa Inggris',
                'Fisika',
                'Kimia',
                'Biologi',
                'Ekonomi',
                'Sosiologi',
                'Geografi',
                'Sejarah',
                'PPKn',
                'Informatika',
            ]),
            'kkm' => 75,
        ];
    }
}
