<?php

use App\Enums\Role;
use App\Models\SchoolProfile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('guests see an empty navigation and no user on the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->where('auth.user', null)
            ->where('nav', [])
        );
});

test('the dashboard shares the authenticated user role and navigation', function (Role $role) {
    $user = User::factory()->asRole($role)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.user.role', $role->value)
            ->has('nav')
        );
})->with([Role::Admin, Role::Teacher, Role::Student, Role::Parent]);

test('admin navigation groups management destinations by area', function () {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('nav', 4)
            ->where('nav.0.label', null)
            ->where('nav.0.items.0.key', 'dashboard')
            ->where('nav.0.items.0.href', route('dashboard'))
            ->where('nav.0.items.0.coming_soon', false)
            ->where('nav.1.label', 'Akademik')
            ->has('nav.1.items', 3)
            ->where('nav.1.items.0.key', 'master')
            ->where('nav.1.items.0.href', route('admin.master.academic-years.index'))
            ->where('nav.1.items.0.coming_soon', false)
            ->where('nav.3.label', 'Sistem')
            ->has('nav.3.items', 2)
        );
});

test('student navigation lists only the learning destinations', function () {
    $this->actingAs(User::factory()->asRole(Role::Student)->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('nav', 2)
            ->where('nav.1.label', 'Belajar')
            ->has('nav.1.items', 4)
            ->where('nav.1.items.0.key', 'schedule')
            ->where('nav.1.items.0.href', null)
            ->where('nav.1.items.0.coming_soon', true)
            ->where('nav.1.items.3.key', 'report-card')
        );
});

test('the dashboard shares the school name and logo', function () {
    SchoolProfile::query()->create([
        'name' => 'SMA Negeri 1 Contoh',
        'address' => 'Jalan Pendidikan No. 1',
        'city' => 'Bandung',
        'headmaster_name' => 'Budi Santoso',
        'headmaster_nip' => '196001011985031001',
        'logo_path' => 'school/logo.png',
    ]);

    $this->actingAs(User::factory()->asRole(Role::Admin)->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('school.name', 'SMA Negeri 1 Contoh')
            ->where('school.logo_url', Storage::disk('public')->url('school/logo.png'))
        );
});

test('the dashboard falls back to the application name without a school profile', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('school.name', config('app.name'))
            ->where('school.logo_url', null)
        );
});
