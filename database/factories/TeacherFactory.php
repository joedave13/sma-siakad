<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nip = fake()->unique()->numerify('##################');

        return [
            'user_id' => User::factory()->asRole(Role::Teacher)->state([
                'username' => $nip,
                'name' => fake()->name(),
            ]),
            'nip' => $nip,
            'full_name' => fake()->name(),
            'phone' => fake()->unique()->numerify('628##########'),
        ];
    }
}
