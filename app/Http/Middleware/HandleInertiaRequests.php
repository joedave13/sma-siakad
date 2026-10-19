<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\SchoolProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $this->userPayload($user),
            ],
            'school' => $this->schoolPayload(),
            'nav' => $user === null ? [] : $this->navPayload($user->role),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The subset of the user record the client is allowed to see.
     *
     * @return array{id: int, name: string, username: string, email: string|null, role: string}|null
     */
    private function userPayload(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role->value,
        ];
    }

    /**
     * @return array{name: string, logo_url: string|null}
     */
    private function schoolPayload(): array
    {
        $profile = SchoolProfile::current();

        return [
            'name' => $profile->name ?? config('app.name'),
            'logo_url' => $profile?->logo_path !== null
                ? Storage::disk('public')->url($profile->logo_path)
                : null,
        ];
    }

    /**
     * The role-aware sidebar navigation. Items flagged coming_soon form the
     * final per-role information architecture; each flips to a live href as
     * its page ships (STEPS.md steps 14-23).
     *
     * @return list<array{label: string|null, items: list<array{key: string, title: string, href: string|null, coming_soon: bool}>}>
     */
    private function navPayload(Role $role): array
    {
        $dashboard = [
            'key' => 'dashboard',
            'title' => 'Dashboard',
            'href' => route('dashboard'),
            'coming_soon' => false,
        ];

        $master = [
            'key' => 'master',
            'title' => 'Data Master',
            'href' => route('admin.master.academic-years.index'),
            'coming_soon' => false,
        ];

        $accounts = [
            'key' => 'accounts',
            'title' => 'Akun Pengguna',
            'href' => route('admin.users.students.index'),
            'coming_soon' => false,
        ];

        $schedule = [
            'key' => 'schedule',
            'title' => 'Penempatan & Jadwal',
            'href' => route('admin.schedule.enrollments.index'),
            'coming_soon' => false,
        ];

        $schoolSettings = [
            'key' => 'school-settings',
            'title' => 'Pengaturan Sekolah',
            'href' => route('admin.school-settings.edit'),
            'coming_soon' => false,
        ];

        $auditLog = [
            'key' => 'audit-log',
            'title' => 'Log Audit',
            'href' => route('admin.audit.index'),
            'coming_soon' => false,
        ];

        $soon = fn (string $key, string $title): array => [
            'key' => $key,
            'title' => $title,
            'href' => null,
            'coming_soon' => true,
        ];

        return match ($role) {
            Role::Admin => [
                ['label' => null, 'items' => [$dashboard]],
                ['label' => 'Akademik', 'items' => [
                    $master,
                    $accounts,
                    $schedule,
                ]],
                ['label' => 'Rapor', 'items' => [
                    $soon('report-publish', 'Terbitkan Rapor'),
                    $soon('promotion', 'Kenaikan Kelas'),
                ]],
                ['label' => 'Sistem', 'items' => [
                    $schoolSettings,
                    $auditLog,
                ]],
            ],
            Role::Teacher => [
                ['label' => null, 'items' => [$dashboard]],
                ['label' => 'Mengajar', 'items' => [
                    [
                        'key' => 'attendance',
                        'title' => 'Presensi',
                        'href' => route('teacher.attendance.index'),
                        'coming_soon' => false,
                    ],
                    [
                        'key' => 'grades',
                        'title' => 'Nilai',
                        'href' => route('teacher.grades.index'),
                        'coming_soon' => false,
                    ],
                    [
                        'key' => 'homeroom',
                        'title' => 'Rapor Wali Kelas',
                        'href' => route('teacher.homeroom.index'),
                        'coming_soon' => false,
                    ],
                ]],
            ],
            Role::Student => [
                ['label' => null, 'items' => [$dashboard]],
                ['label' => 'Belajar', 'items' => [
                    $soon('schedule', 'Jadwal'),
                    $soon('attendance', 'Presensi'),
                    $soon('grades', 'Nilai'),
                    $soon('report-card', 'Rapor'),
                ]],
            ],
            Role::Parent => [
                ['label' => null, 'items' => [$dashboard]],
                ['label' => 'Anak', 'items' => [
                    $soon('schedule', 'Jadwal'),
                    $soon('attendance', 'Presensi'),
                    $soon('grades', 'Nilai'),
                    $soon('report-card', 'Rapor'),
                ]],
            ],
        };
    }
}
