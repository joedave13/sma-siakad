<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Guardian;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phone = fake()->unique()->numerify('628##########');

        return [
            'user_id' => User::factory()->asRole(Role::Parent)->state([
                'username' => $phone,
                'name' => fake()->name(),
            ]),
            'full_name' => fake()->name(),
            'phone' => $phone,
        ];
    }
}
