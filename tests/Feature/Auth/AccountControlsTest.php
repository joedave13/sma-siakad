<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('self registration is disabled', function () {
    $this->get('/register')->assertNotFound();

    $this->post('/register', [
        'name' => 'Test User',
        'username' => 'testuser',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();
});

test('self service password reset is disabled', function () {
    $this->get('/forgot-password')->assertNotFound();
});

test('users must change their password before entering the app', function () {
    $user = User::factory()->mustChangePassword()->create();

    $this->post(route('login.store'), [
        'username' => $user->username,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))->assertRedirect(route('force-password.change'));

    $this->put(route('force-password.update'), [
        'password' => 'kata-baru-1234',
        'password_confirmation' => 'kata-baru-1234',
    ])->assertRedirect(route('dashboard'));

    expect($user->refresh()->must_change_password)->toBeFalse()
        ->and(Hash::check('kata-baru-1234', $user->password))->toBeTrue();

    $this->get(route('dashboard'))->assertOk();
});

test('forced password change requires a valid confirmation', function () {
    $user = User::factory()->mustChangePassword()->create();

    $this->actingAs($user)->put(route('force-password.update'), [
        'password' => 'kata-baru-1234',
        'password_confirmation' => 'berbeda-tentu-saja',
    ])->assertSessionHasErrors('password');

    expect($user->refresh()->must_change_password)->toBeTrue();
});

test('the admin password reset endpoint requires the admin role', function () {
    $teacher = User::factory()->asRole(Role::Teacher)->create();
    $student = User::factory()->asRole(Role::Student)->create();

    $this->actingAs($teacher)
        ->post(route('admin.users.reset-password', $student))
        ->assertForbidden();

    $this->actingAs($student)
        ->post(route('admin.users.reset-password', $teacher))
        ->assertForbidden();
});

test('an admin can reset a user password and it must be changed on next login', function () {
    $admin = User::factory()->asRole(Role::Admin)->create();
    $teacher = User::factory()->asRole(Role::Teacher)->create();

    $response = $this->actingAs($admin)->post(route('admin.users.reset-password', $teacher));

    $response->assertRedirect()->assertSessionHas('inertia.flash_data');

    $teacher->refresh();

    expect($teacher->must_change_password)->toBeTrue()
        ->and(Hash::check('password', $teacher->password))->toBeFalse();

    $newPassword = session('inertia.flash_data')['toast']['message'];

    expect($newPassword)->toContain($teacher->username);
});
