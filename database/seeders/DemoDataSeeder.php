<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed a complete demo school on top of the base seeders: identity,
     * structure, schedule, attendance, grades, report cards, promotion, and
     * a printable table of login accounts. Skips once the demo year exists.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            LessonPeriodSeeder::class,
            AcademicSettingSeeder::class,
        ]);

        if (AcademicYear::query()->where('name', DemoData::CURRENT_YEAR)->exists()) {
            $this->command->info('Demo data for '.DemoData::CURRENT_YEAR.' already exists; generation skipped.');

            $this->printAccounts();

            return;
        }

        $admin = User::query()->where('role', Role::Admin->value)->first();

        if ($admin !== null) {
            auth()->login($admin);
        }

        $this->call([
            DemoSchoolProfileSeeder::class,
            DemoAcademicStructureSeeder::class,
            DemoScheduleSeeder::class,
            DemoAttendanceSeeder::class,
            DemoGradeSeeder::class,
            DemoReportCardSeeder::class,
            DemoPromotionSeeder::class,
        ]);

        $this->printAccounts();
    }

    /**
     * Print every seeded login account; all passwords are PASSWORD except
     * the admin, which comes from config.
     */
    private function printAccounts(): void
    {
        $rows = [];

        $admin = User::query()->where('role', Role::Admin->value)->first();
        if ($admin !== null) {
            $rows[] = ['Admin', $admin->name, $admin->username, (string) config('app.seed_admin_password')];
        }

        $roles = [
            Role::Teacher->value => 'Guru',
            Role::Student->value => 'Siswa',
            Role::Parent->value => 'Orang Tua',
        ];

        foreach ($roles as $role => $label) {
            User::query()
                ->where('role', $role)
                ->orderBy('name')
                ->get()
                ->each(function (User $user) use (&$rows, $label): void {
                    $rows[] = [$label, $user->name, $user->username, DemoData::PASSWORD];
                });
        }

        $this->command->table(['Role', 'Nama', 'Username', 'Password'], $rows);
        $this->command->info('Buka '.config('app.url').' untuk melihat aplikasi.');
    }
}
