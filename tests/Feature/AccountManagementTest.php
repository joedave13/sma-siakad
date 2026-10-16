<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from the account pages', function (string $routeName): void {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with([
    'students' => 'admin.users.students.index',
    'teachers' => 'admin.users.teachers.index',
    'guardians' => 'admin.users.guardians.index',
    'import' => 'admin.users.import.create',
]);

test('non-admin users cannot access the account pages', function (string $routeName): void {
    $this->actingAs(User::factory()->asRole(Role::Teacher)->create())
        ->get(route($routeName))
        ->assertForbidden();
})->with([
    'students' => 'admin.users.students.index',
    'teachers' => 'admin.users.teachers.index',
    'guardians' => 'admin.users.guardians.index',
    'import' => 'admin.users.import.create',
]);

test('admins can open every account page', function (string $routeName, string $component): void {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create())
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'students index' => ['admin.users.students.index', 'admin/users/Students'],
    'teachers index' => ['admin.users.teachers.index', 'admin/users/Teachers'],
    'guardians index' => ['admin.users.guardians.index', 'admin/users/Guardians'],
    'import form' => ['admin.users.import.create', 'admin/users/Import'],
    'student create' => ['admin.users.students.create', 'admin/users/StudentForm'],
    'teacher create' => ['admin.users.teachers.create', 'admin/users/TeacherForm'],
    'guardian create' => ['admin.users.guardians.create', 'admin/users/GuardianForm'],
]);

test('admin creates a student account whose username equals the nisn', function (): void {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.users.students.store'), [
        'name' => 'Budi Santoso',
        'nisn' => '1234567890',
        'nis' => '9001',
        'gender' => 'male',
        'birth_date' => '2010-08-15',
        'birth_place' => 'Jakarta',
        'address' => 'Jl. Merdeka 1',
        'status' => 'active',
    ])->assertRedirect(route('admin.users.students.index'));

    $student = Student::query()->where('nisn', '1234567890')->firstOrFail();

    expect($student->full_name)->toBe('Budi Santoso')
        ->and($student->user->username)->toBe('1234567890')
        ->and($student->user->role)->toBe(Role::Student)
        ->and($student->user->is_active)->toBeTrue()
        ->and($student->user->must_change_password)->toBeTrue();

    $toast = session('inertia.flash_data')['toast']['message'];
    expect($toast)->toStartWith('Akun siswa dibuat. Kata sandi awal untuk 1234567890: ');
    preg_match('/untuk 1234567890: (\S+) \(/', $toast, $matches);
    expect(Hash::check($matches[1], $student->user->password))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'student.create')->count())->toBe(1);
});

test('admin edits a student and the username follows the nisn', function (): void {
    $student = Student::factory()->create();
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->put(route('admin.users.students.update', $student), [
        'name' => 'Nama Baru',
        'nisn' => $student->nisn,
        'gender' => 'female',
        'status' => 'active',
    ])->assertRedirect(route('admin.users.students.index'));

    expect($student->fresh()->full_name)->toBe('Nama Baru');

    $this->put(route('admin.users.students.update', $student), [
        'name' => 'Nama Baru',
        'nisn' => '0987654321',
        'gender' => 'female',
        'status' => 'active',
    ])->assertRedirect(route('admin.users.students.index'));

    $student->refresh();

    expect($student->nisn)->toBe('0987654321')
        ->and($student->user->username)->toBe('0987654321')
        ->and(AuditLog::query()->where('action', 'student.update')->count())->toBe(2);
});

test('student identity rules report Indonesian messages', function (array $payload, string $field, string $message): void {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.users.students.store'), $payload)
        ->assertSessionHasErrors([$field => $message]);

    expect(Student::query()->count())->toBe(0);
})->with([
    'short nisn' => [
        ['name' => 'A', 'nisn' => '12345', 'gender' => 'male', 'status' => 'active'],
        'nisn',
        'NISN harus terdiri dari 10 digit angka.',
    ],
    'missing name' => [
        ['name' => '', 'nisn' => '5555555555', 'gender' => 'male', 'status' => 'active'],
        'name',
        'Nama wajib diisi.',
    ],
    'missing gender' => [
        ['name' => 'A', 'nisn' => '5555555555', 'gender' => '', 'status' => 'active'],
        'gender',
        'Jenis kelamin wajib dipilih.',
    ],
]);

test('duplicate nisn is rejected with the Indonesian message', function (): void {
    $existing = Student::factory()->create();
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.users.students.store'), [
        'name' => 'Peniru',
        'nisn' => $existing->nisn,
        'gender' => 'male',
        'status' => 'active',
    ])->assertSessionHasErrors(['nisn' => 'NISN tersebut sudah terdaftar.']);
});

test('a student with enrollment records cannot be deleted, a clean student can', function (): void {
    $blocked = Student::factory()->create();
    ClassEnrollment::factory()->create(['student_id' => $blocked->id]);
    $clean = Student::factory()->create();
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->delete(route('admin.users.students.destroy', $blocked))
        ->assertSessionHasErrors(['delete' => 'Data tidak dapat dihapus karena masih digunakan oleh: 1 peserta didik terdaftar.']);

    expect($blocked->fresh()->deleted_at)->toBeNull();

    $this->delete(route('admin.users.students.destroy', $clean))
        ->assertRedirect(route('admin.users.students.index'));

    $clean->refresh();

    expect($clean->deleted_at)->not->toBeNull()
        ->and($clean->user->is_active)->toBeFalse()
        ->and(User::query()->where('username', $clean->nisn)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'student.delete')->count())->toBe(1);
});

test('admin creates and deletes a teacher account keyed by nip', function (): void {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.users.teachers.store'), [
        'name' => 'Ahmad Dani',
        'nip' => '198712345678901234',
        'phone' => '6281111111111',
    ])->assertRedirect(route('admin.users.teachers.index'));

    $teacher = Teacher::query()->where('nip', '198712345678901234')->firstOrFail();

    expect($teacher->user->username)->toBe('198712345678901234')
        ->and($teacher->user->role)->toBe(Role::Teacher);

    $assignment = TeachingAssignment::factory()->create(['teacher_id' => $teacher->id]);

    $this->delete(route('admin.users.teachers.destroy', $teacher))
        ->assertSessionHasErrors(['delete' => 'Data tidak dapat dihapus karena masih digunakan oleh: 1 penugasan mengajar.']);

    expect($teacher->fresh()->deleted_at)->toBeNull();

    $assignment->delete();

    $this->delete(route('admin.users.teachers.destroy', $teacher))
        ->assertRedirect(route('admin.users.teachers.index'));

    expect($teacher->fresh()->deleted_at)->not->toBeNull()
        ->and($teacher->fresh()->user->is_active)->toBeFalse();
});

test('admin creates a guardian account keyed by phone and is blocked while a child is linked', function (): void {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.users.guardians.store'), [
        'name' => 'Siti Rahayu',
        'telepon' => '6281234567890',
    ])->assertRedirect(route('admin.users.guardians.index'));

    $guardian = Guardian::query()->where('phone', '6281234567890')->firstOrFail();

    expect($guardian->user->username)->toBe('6281234567890')
        ->and($guardian->user->role)->toBe(Role::Parent);

    $toast = session('inertia.flash_data')['toast']['message'];
    expect($toast)->toEndWith('Buka Ubah untuk menautkan anak.');

    $student = Student::factory()->create();
    $guardian->students()->attach($student->id, ['relationship' => 'Ibu']);

    $this->delete(route('admin.users.guardians.destroy', $guardian))
        ->assertSessionHasErrors(['delete' => 'Data tidak dapat dihapus karena masih digunakan oleh: 1 anak yang tertaut.']);

    expect($guardian->fresh()->deleted_at)->toBeNull();
});

test('resetting a password replaces the hash and forces a change on next login', function (): void {
    $student = Student::factory()->create();
    $user = $student->user;
    $before = $user->password;
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.users.reset-password', $user))
        ->assertRedirect();

    $user->refresh();

    $toast = session('inertia.flash_data')['toast']['message'];
    expect($toast)->toStartWith("Kata sandi baru untuk {$user->username}: ")
        ->and($user->password)->not->toBe($before)
        ->and($user->must_change_password)->toBeTrue();

    $password = Str::after($toast, "{$user->username}: ");
    expect(Hash::check($password, $user->password))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'account.password-reset')->count())->toBe(1);
});
