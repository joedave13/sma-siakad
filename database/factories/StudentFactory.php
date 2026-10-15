<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nisn = fake()->unique()->numerify('##########');

        return [
            'user_id' => User::factory()->asRole(Role::Student)->state([
                'username' => $nisn,
                'name' => fake()->name(),
            ]),
            'nisn' => $nisn,
            'nis' => fake()->unique()->numerify('##########'),
            'full_name' => fake()->name(),
            'gender' => fake()->randomElement([Gender::Male, Gender::Female]),
            'birth_date' => fake()->dateTimeBetween('-18 years', '-14 years'),
            'birth_place' => fake()->city(),
            'address' => fake()->address(),
            'status' => StudentStatus::Active,
        ];
    }
}
