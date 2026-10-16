<?php

use App\Enums\DayOfWeek;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\GradeEntry;
use App\Models\LessonPeriod;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The active academic year and semester with one classroom, subject, teacher
 * and lesson period, plus an admin, shared by the schedule builder tests.
 *
 * @return array{admin: User, year: AcademicYear, semester: Semester, classroom: Classroom, subject: Subject, teacher: Teacher, period: LessonPeriod}
 */
function scheduleContext(): array
{
    $year = AcademicYear::factory()->active()->create();
    $semester = Semester::factory()->ganjil()->active()->create([
        'academic_year_id' => $year->id,
    ]);
    $classroom = Classroom::factory()->create(['academic_year_id' => $year->id]);
    $subject = Subject::factory()->create();
    $teacher = Teacher::factory()->create();
    $period = LessonPeriod::factory()->create(['number' => 1]);

    return [
        'admin' => User::factory()->asRole(Role::Admin)->create(),
        'year' => $year,
        'semester' => $semester,
        'classroom' => $classroom,
        'subject' => $subject,
        'teacher' => $teacher,
        'period' => $period,
    ];
}

/**
 * @param  array{admin: User, year: AcademicYear, semester: Semester, classroom: Classroom, subject: Subject, teacher: Teacher, period: LessonPeriod}  $context
 * @param  array<string, mixed>  $overrides
 */
function contextAssignment(array $context, array $overrides = []): TeachingAssignment
{
    return TeachingAssignment::factory()->create(array_merge([
        'semester_id' => $context['semester']->id,
        'classroom_id' => $context['classroom']->id,
        'subject_id' => $context['subject']->id,
        'teacher_id' => $context['teacher']->id,
    ], $overrides));
}

it('redirects guests from the schedule pages', function (string $routeName): void {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with([
    'penempatan siswa' => 'admin.schedule.enrollments.index',
    'penugasan mengajar' => 'admin.schedule.assignments.index',
    'jadwal mingguan' => 'admin.schedule.weekly.index',
]);

it('forbids a teacher from reading and writing the schedule section', function (string $method, string $routeName): void {
    $this->actingAs(User::factory()->asRole(Role::Teacher)->create())
        ->{$method}(route($routeName))
        ->assertForbidden();
})->with([
    'GET penempatan' => ['get', 'admin.schedule.enrollments.index'],
    'GET penugasan' => ['get', 'admin.schedule.assignments.index'],
    'GET jadwal' => ['get', 'admin.schedule.weekly.index'],
    'POST penempatan' => ['post', 'admin.schedule.enrollments.store'],
    'POST penugasan' => ['post', 'admin.schedule.assignments.store'],
    'POST jadwal' => ['post', 'admin.schedule.weekly.store'],
]);

it('renders a schedule page for an admin', function (string $routeName, string $component): void {
    $context = scheduleContext();

    $this->actingAs($context['admin'])
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'penempatan siswa' => ['admin.schedule.enrollments.index', 'admin/schedule/Enrollments'],
    'penugasan mengajar' => ['admin.schedule.assignments.index', 'admin/schedule/Assignments'],
    'jadwal mingguan' => ['admin.schedule.weekly.index', 'admin/schedule/WeeklySchedule'],
]);

it('places a student into the active classroom and audit-logs it', function (): void {
    $context = scheduleContext();
    $student = Student::factory()->create();

    $this->actingAs($context['admin'])->post(route('admin.schedule.enrollments.store'), [
        'classroom_id' => $context['classroom']->id,
        'student_id' => $student->id,
    ])->assertRedirect(route('admin.schedule.enrollments.index', [
        'classroom' => $context['classroom']->id,
    ]));

    $enrollment = ClassEnrollment::query()->where('student_id', $student->id)->firstOrFail();

    expect($enrollment->classroom_id)->toBe($context['classroom']->id)
        ->and($enrollment->academic_year_id)->toBe($context['year']->id)
        ->and(session('inertia.flash_data')['toast']['message'])
        ->toBe("{$student->full_name} ditempatkan di kelas {$context['classroom']->name}.")
        ->and(AuditLog::query()->where('action', 'enrollment.create')->count())->toBe(1);

    $this->actingAs($context['admin'])
        ->get(route('admin.schedule.enrollments.index', ['classroom' => $context['classroom']->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/schedule/Enrollments')
            ->where('selectedClassroom', $context['classroom']->id)
            ->where('classrooms.0.students.0.full_name', $student->full_name));
});

it('rejects a student already placed in another class this year', function (): void {
    $context = scheduleContext();
    $other = Classroom::factory()->create(['academic_year_id' => $context['year']->id]);
    $student = Student::factory()->create();
    ClassEnrollment::forceCreate([
        'classroom_id' => $context['classroom']->id,
        'student_id' => $student->id,
        'academic_year_id' => $context['year']->id,
    ]);

    $this->actingAs($context['admin'])
        ->from(route('admin.schedule.enrollments.index'))
        ->post(route('admin.schedule.enrollments.store'), [
            'classroom_id' => $other->id,
            'student_id' => $student->id,
        ])
        ->assertRedirect(route('admin.schedule.enrollments.index'))
        ->assertSessionHasErrors([
            'student_id' => 'Siswa sudah terdaftar di kelas lain pada tahun ajaran ini.',
        ]);

    expect(ClassEnrollment::query()->count())->toBe(1);
});

it('re-enrolls a student through a trashed enrollment row', function (): void {
    $context = scheduleContext();
    $student = Student::factory()->create();
    $enrollment = ClassEnrollment::factory()->create([
        'classroom_id' => $context['classroom']->id,
        'student_id' => $student->id,
    ]);
    $enrollment->delete();

    $this->actingAs($context['admin'])->post(route('admin.schedule.enrollments.store'), [
        'classroom_id' => $context['classroom']->id,
        'student_id' => $student->id,
    ])->assertRedirect(route('admin.schedule.enrollments.index', [
        'classroom' => $context['classroom']->id,
    ]));

    expect($enrollment->fresh()->deleted_at)->toBeNull()
        ->and(ClassEnrollment::withTrashed()->count())->toBe(1);
});

it('unenrolls a student and audit-logs it', function (): void {
    $context = scheduleContext();
    $student = Student::factory()->create();
    $enrollment = ClassEnrollment::factory()->create([
        'classroom_id' => $context['classroom']->id,
        'student_id' => $student->id,
    ]);

    $this->actingAs($context['admin'])
        ->delete(route('admin.schedule.enrollments.destroy', $enrollment))
        ->assertRedirect(route('admin.schedule.enrollments.index', [
            'classroom' => $context['classroom']->id,
        ]));

    expect($enrollment->fresh()->deleted_at)->not->toBeNull()
        ->and(session('inertia.flash_data')['toast']['message'])
        ->toBe("{$student->full_name} dikeluarkan dari kelas {$context['classroom']->name}.")
        ->and(AuditLog::query()->where('action', 'enrollment.delete')->count())->toBe(1);
});

it('adds a teaching assignment and audit-logs it', function (): void {
    $context = scheduleContext();

    $this->actingAs($context['admin'])->post(route('admin.schedule.assignments.store'), [
        'semester_id' => $context['semester']->id,
        'classroom_id' => $context['classroom']->id,
        'subject_id' => $context['subject']->id,
        'teacher_id' => $context['teacher']->id,
    ])->assertRedirect(route('admin.schedule.assignments.index', [
        'classroom' => $context['classroom']->id,
    ]));

    $assignment = TeachingAssignment::query()->where('subject_id', $context['subject']->id)->firstOrFail();

    expect($assignment->semester_id)->toBe($context['semester']->id)
        ->and($assignment->teacher_id)->toBe($context['teacher']->id)
        ->and(session('inertia.flash_data')['toast']['message'])
        ->toBe("Penugasan {$context['subject']->name} untuk kelas {$context['classroom']->name} ditambahkan.")
        ->and(AuditLog::query()->where('action', 'assignment.create')->count())->toBe(1);

    $this->actingAs($context['admin'])
        ->get(route('admin.schedule.assignments.index', ['classroom' => $context['classroom']->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/schedule/Assignments')
            ->where('selectedClassroom', $context['classroom']->id)
            ->where('assignments.0.subject_name', $context['subject']->name)
            ->where('assignments.0.teacher_name', $context['teacher']->full_name));
});

it('rejects the same subject for a class twice in one semester', function (): void {
    $context = scheduleContext();
    contextAssignment($context);

    $this->actingAs($context['admin'])
        ->from(route('admin.schedule.assignments.index'))
        ->post(route('admin.schedule.assignments.store'), [
            'semester_id' => $context['semester']->id,
            'classroom_id' => $context['classroom']->id,
            'subject_id' => $context['subject']->id,
            'teacher_id' => $context['teacher']->id,
        ])
        ->assertRedirect(route('admin.schedule.assignments.index'))
        ->assertSessionHasErrors([
            'subject_id' => 'Mata pelajaran ini sudah terdaftar untuk kelas tersebut pada semester ini.',
        ]);

    expect(TeachingAssignment::query()->count())->toBe(1);
});

it('removes a teaching assignment with no schedule or grades', function (): void {
    $context = scheduleContext();
    $assignment = contextAssignment($context);

    $this->actingAs($context['admin'])
        ->delete(route('admin.schedule.assignments.destroy', $assignment))
        ->assertRedirect(route('admin.schedule.assignments.index', [
            'classroom' => $context['classroom']->id,
        ]));

    expect($assignment->fresh()->deleted_at)->not->toBeNull()
        ->and(session('inertia.flash_data')['toast']['message'])
        ->toBe("Penugasan {$context['subject']->name} untuk kelas {$context['classroom']->name} dihapus.")
        ->and(AuditLog::query()->where('action', 'assignment.delete')->count())->toBe(1);
});

it('blocks deleting a teaching assignment that still carries grades', function (): void {
    $context = scheduleContext();
    $assignment = contextAssignment($context);
    $student = Student::factory()->create();
    GradeEntry::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'student_id' => $student->id,
    ]);

    $this->actingAs($context['admin'])
        ->delete(route('admin.schedule.assignments.destroy', $assignment))
        ->assertSessionHasErrors('delete');

    expect(session('errors')->first('delete'))
        ->toContain('Data tidak dapat dihapus karena masih digunakan oleh')
        ->toContain('nilai')
        ->and($assignment->fresh()->deleted_at)->toBeNull();
});

it('schedules a lesson slot, audit-logs it, and lists it on the grid', function (): void {
    $context = scheduleContext();
    $assignment = contextAssignment($context);

    $this->actingAs($context['admin'])->post(route('admin.schedule.weekly.store'), [
        'teaching_assignment_id' => $assignment->id,
        'day_of_week' => DayOfWeek::Senin->value,
        'lesson_period_id' => $context['period']->id,
    ])->assertRedirect(route('admin.schedule.weekly.index', [
        'classroom' => $context['classroom']->id,
    ]));

    $schedule = Schedule::query()->where('teaching_assignment_id', $assignment->id)->firstOrFail();

    expect($schedule->day_of_week)->toBe(DayOfWeek::Senin)
        ->and($schedule->lesson_period_id)->toBe($context['period']->id)
        ->and(session('inertia.flash_data')['toast']['message'])
        ->toBe("{$context['subject']->name} dijadwalkan di kelas {$context['classroom']->name} pada Senin jam ke-{$context['period']->number}.")
        ->and(AuditLog::query()->where('action', 'schedule.create')->count())->toBe(1);

    $this->actingAs($context['admin'])
        ->get(route('admin.schedule.weekly.index', ['classroom' => $context['classroom']->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/schedule/WeeklySchedule')
            ->where('selectedClassroom', $context['classroom']->id)
            ->has('schedules', 1)
            ->where('schedules.0.teaching_assignment_id', $assignment->id)
            ->where('schedules.0.day_of_week', 'senin')
            ->where('days.0.value', 'senin'));
});

it('rejects scheduling the same assignment twice on one slot', function (): void {
    $context = scheduleContext();
    $assignment = contextAssignment($context);
    Schedule::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'day_of_week' => DayOfWeek::Senin,
        'lesson_period_id' => $context['period']->id,
    ]);

    $this->actingAs($context['admin'])
        ->from(route('admin.schedule.weekly.index'))
        ->post(route('admin.schedule.weekly.store'), [
            'teaching_assignment_id' => $assignment->id,
            'day_of_week' => DayOfWeek::Senin->value,
            'lesson_period_id' => $context['period']->id,
        ])
        ->assertRedirect(route('admin.schedule.weekly.index'))
        ->assertSessionHasErrors([
            'teaching_assignment_id' => 'Mata pelajaran ini sudah dijadwalkan pada hari dan jam pelajaran yang sama.',
        ]);

    expect(Schedule::query()->count())->toBe(1);
});

it('rejects a teacher already booked at the same day and period', function (): void {
    $context = scheduleContext();
    $booked = contextAssignment($context);
    Schedule::factory()->create([
        'teaching_assignment_id' => $booked->id,
        'day_of_week' => DayOfWeek::Senin,
        'lesson_period_id' => $context['period']->id,
    ]);
    $other = Classroom::factory()->create(['academic_year_id' => $context['year']->id]);
    $second = contextAssignment($context, [
        'classroom_id' => $other->id,
        'subject_id' => Subject::factory()->create()->id,
    ]);

    $this->actingAs($context['admin'])
        ->from(route('admin.schedule.weekly.index'))
        ->post(route('admin.schedule.weekly.store'), [
            'teaching_assignment_id' => $second->id,
            'day_of_week' => DayOfWeek::Senin->value,
            'lesson_period_id' => $context['period']->id,
        ])
        ->assertRedirect(route('admin.schedule.weekly.index'))
        ->assertSessionHasErrors([
            'teaching_assignment_id' => 'Guru tersebut sudah mengajar di kelas lain pada hari dan jam pelajaran yang sama.',
        ]);

    expect(Schedule::query()->count())->toBe(1);
});

it('rejects a second subject in a slot the classroom already holds', function (): void {
    $context = scheduleContext();
    $booked = contextAssignment($context);
    Schedule::factory()->create([
        'teaching_assignment_id' => $booked->id,
        'day_of_week' => DayOfWeek::Senin,
        'lesson_period_id' => $context['period']->id,
    ]);
    $second = contextAssignment($context, [
        'subject_id' => Subject::factory()->create()->id,
        'teacher_id' => Teacher::factory()->create()->id,
    ]);

    $this->actingAs($context['admin'])
        ->from(route('admin.schedule.weekly.index'))
        ->post(route('admin.schedule.weekly.store'), [
            'teaching_assignment_id' => $second->id,
            'day_of_week' => DayOfWeek::Senin->value,
            'lesson_period_id' => $context['period']->id,
        ])
        ->assertRedirect(route('admin.schedule.weekly.index'))
        ->assertSessionHasErrors([
            'teaching_assignment_id' => 'Kelas tersebut sudah memiliki jadwal lain pada hari dan jam pelajaran yang sama.',
        ]);

    expect(Schedule::query()->count())->toBe(1);
});

it('blocks deleting a slot that already holds attendance sessions', function (): void {
    $context = scheduleContext();
    $assignment = contextAssignment($context);
    $schedule = Schedule::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'day_of_week' => DayOfWeek::Senin,
        'lesson_period_id' => $context['period']->id,
    ]);
    AttendanceSession::forceCreate([
        'schedule_id' => $schedule->id,
        'date' => '2026-10-16',
    ]);

    $this->actingAs($context['admin'])
        ->delete(route('admin.schedule.weekly.destroy', $schedule))
        ->assertSessionHasErrors('delete');

    expect(session('errors')->first('delete'))
        ->toContain('sesi presensi')
        ->and($schedule->fresh()->deleted_at)->toBeNull();
});

it('clears the slot first, then removes the assignment', function (): void {
    $context = scheduleContext();
    $assignment = contextAssignment($context);
    $schedule = Schedule::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'day_of_week' => DayOfWeek::Senin,
        'lesson_period_id' => $context['period']->id,
    ]);

    $this->actingAs($context['admin'])
        ->delete(route('admin.schedule.assignments.destroy', $assignment))
        ->assertSessionHasErrors('delete');

    expect(session('errors')->first('delete'))
        ->toContain('jadwal')
        ->and($assignment->fresh()->deleted_at)->toBeNull();

    $this->delete(route('admin.schedule.weekly.destroy', $schedule))
        ->assertRedirect(route('admin.schedule.weekly.index', [
            'classroom' => $context['classroom']->id,
        ]));

    expect($schedule->fresh()->deleted_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'schedule.delete')->count())->toBe(1);

    $this->delete(route('admin.schedule.assignments.destroy', $assignment))
        ->assertRedirect(route('admin.schedule.assignments.index', [
            'classroom' => $context['classroom']->id,
        ]));

    expect($assignment->fresh()->deleted_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'assignment.delete')->count())->toBe(1);
});

it('requires every field when building the schedule plan', function (): void {
    $context = scheduleContext();

    $this->actingAs($context['admin'])->post(route('admin.schedule.enrollments.store'), [])
        ->assertSessionHasErrors([
            'classroom_id' => 'Bidang ruang kelas wajib diisi.',
            'student_id' => 'Bidang siswa wajib diisi.',
        ]);

    $this->post(route('admin.schedule.assignments.store'), [])
        ->assertSessionHasErrors([
            'semester_id' => 'Bidang semester wajib diisi.',
            'classroom_id' => 'Bidang ruang kelas wajib diisi.',
            'subject_id' => 'Bidang mata pelajaran wajib diisi.',
            'teacher_id' => 'Bidang guru wajib diisi.',
        ]);

    $this->post(route('admin.schedule.weekly.store'), [])
        ->assertSessionHasErrors([
            'teaching_assignment_id' => 'Bidang penugasan mengajar wajib diisi.',
            'day_of_week' => 'Bidang hari wajib diisi.',
            'lesson_period_id' => 'Bidang jam pelajaran wajib diisi.',
        ]);
});
