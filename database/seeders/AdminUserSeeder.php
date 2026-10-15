<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Create the initial administrator account (idempotent).
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['username' => config('app.seed_admin_username')],
            [
                'name' => 'Administrator',
                'role' => Role::Admin,
                'is_active' => true,
                'must_change_password' => true,
                'password' => config('app.seed_admin_password'),
            ],
        );
    }
}
