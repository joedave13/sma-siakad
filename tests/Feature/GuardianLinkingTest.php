<?php

use App\Enums\Role;
use App\Jobs\RecomputeDailyAttendance;
use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\Guardian;
use App\Models\LessonPeriod;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Notifications\ChildAbsent;
use App\Services\Attendance\AttendanceSubmitService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function unlinkAccessContext(): array
{
    $teacher = Teacher::factory()->create();
    $assignment = TeachingAssignment::factory()->create(['teacher_id' => $teacher->id]);
    $period = LessonPeriod::factory()->create(['number' => 1]);
    $schedule = Schedule::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'lesson_period_id' => $period->id,
        'day_of_week' => 'senin',
    ]);
    $student = Student::factory()->create();
    ClassEnrollment::factory()->create([
        'student_id' => $student->id,
        'classroom_id' => $assignment->classroom_id,
        'academic_year_id' => $assignment->semester->academic_year_id,
    ]);

    return [$teacher, $schedule, $student];
}

test('guests are redirected from guardian link routes', function (bool $isDestroy): void {
    $guardian = Guardian::factory()->create();
    $student = Student::factory()->create();

    $response = $isDestroy
        ? $this->delete(route('admin.users.guardians.link.destroy', [$guardian, $student]))
        : $this->post(route('admin.users.guardians.link.store', $guardian));

    $response->assertRedirect(route('login'));
})->with(['link' => false, 'unlink' => true]);

test('non-admin users cannot manage guardian links', function (bool $isDestroy): void {
    $guardian = Guardian::factory()->create();
    $student = Student::factory()->create();
    $this->actingAs(User::factory()->asRole(Role::Teacher)->create());

    $response = $isDestroy
        ? $this->delete(route('admin.users.guardians.link.destroy', [$guardian, $student]))
        : $this->post(route('admin.users.guardians.link.store', $guardian));

    $response->assertForbidden();
})->with(['link' => false, 'unlink' => true]);

test('admin links a student to a guardian with a recorded relationship', function (): void {
    $guardian = Guardian::factory()->create();
    $student = Student::factory()->create();
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.users.guardians.link.store', $guardian), [
        'student_id' => $student->id,
        'relationship' => 'Ayah',
    ])->assertRedirect();

    expect($guardian->students()->get()->first()->pivot->relationship)->toBe('Ayah')
        ->and(session('inertia.flash_data')['toast']['message'])
        ->toBe("{$student->full_name} ({$student->nisn}) tertaut sebagai anak dari {$guardian->full_name}.")
        ->and(AuditLog::query()->where('action', 'guardian.link')->count())->toBe(1);
});

test('invalid guardian links are rejected with Indonesian messages', function (): void {
    $guardian = Guardian::factory()->create();
    $student = Student::factory()->create();
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.users.guardians.link.store', $guardian), [
        'student_id' => $student->id,
        'relationship' => 'Saudara',
    ])->assertSessionHasErrors(['relationship' => 'Hubungan tidak dikenal.']);

    $guardian->students()->attach($student->id, ['relationship' => 'Ibu']);

    $this->post(route('admin.users.guardians.link.store', $guardian), [
        'student_id' => $student->id,
        'relationship' => 'Ibu',
    ])->assertSessionHasErrors(['student_id' => 'Siswa tersebut sudah tertaut ke orang tua ini.']);
});

test('admin unlinks a child and the toast names both parties', function (): void {
    $guardian = Guardian::factory()->create();
    $student = Student::factory()->create();
    $guardian->students()->attach($student->id, ['relationship' => 'Wali']);
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->delete(route('admin.users.guardians.link.destroy', [$guardian, $student]))
        ->assertRedirect();

    expect($guardian->students()->count())->toBe(0)
        ->and(session('inertia.flash_data')['toast']['message'])
        ->toBe("Tautan {$student->full_name} ({$student->nisn}) dengan {$guardian->full_name} dilepas.")
        ->and(AuditLog::query()->where('action', 'guardian.unlink')->count())->toBe(1);
});

test('unlinking revokes absence notifications for that guardian', function (): void {
    Bus::fake([RecomputeDailyAttendance::class]);
    Notification::fake();
    [$teacher, $schedule, $student] = unlinkAccessContext();
    $guardian = Guardian::factory()->create();
    $admin = User::factory()->asRole(Role::Admin)->create();

    $this->actingAs($admin)->post(route('admin.users.guardians.link.store', $guardian), [
        'student_id' => $student->id,
        'relationship' => 'Ibu',
    ])->assertRedirect();

    app(AttendanceSubmitService::class)->submit($schedule, Carbon::parse('2026-10-12'), [
        ['student_id' => $student->id, 'status' => 'absent'],
    ], $teacher->user);

    Notification::assertSentTo($guardian, ChildAbsent::class);

    $this->actingAs($admin)->delete(route('admin.users.guardians.link.destroy', [$guardian, $student]))
        ->assertRedirect();
    Notification::fake();

    app(AttendanceSubmitService::class)->submit($schedule, Carbon::parse('2026-10-13'), [
        ['student_id' => $student->id, 'status' => 'absent'],
    ], $teacher->user);

    Notification::assertNotSentTo($guardian, ChildAbsent::class);
});

test('the guardian edit page moves a linked child out of the available list', function (): void {
    $guardian = Guardian::factory()->create();
    $linked = Student::factory()->create();
    $free = Student::factory()->create();
    $guardian->students()->attach($linked->id, ['relationship' => 'Ibu']);
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->get(route('admin.users.guardians.edit', $guardian))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/GuardianForm')
            ->has('guardian.children', 1)
            ->where('guardian.children.0.id', $linked->id)
            ->where('guardian.children.0.relationship', 'Ibu')
            ->has('availableStudents', 1)
            ->where('availableStudents.0.id', $free->id)
            ->where('totalStudents', 2));
});
