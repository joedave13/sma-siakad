<?php

use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Enums\Role;
use App\Jobs\RecomputeDailyAttendance;
use App\Models\DailyAttendance;
use App\Models\Guardian;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ChildAbsent;
use App\Services\Attendance\AttendanceSubmitService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;

test('a student sees their weekly schedule for the active semester', function () {
    $context = teacherAttendanceUiContext();
    $student = $context['students'][0];
    $assignment = $context['assignment'];

    $this->actingAs($student->user)
        ->get(route('student.schedule.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('student/Schedule')
            ->where('schedule.no_active_year', false)
            ->where('schedule.no_active_semester', false)
            ->where('schedule.not_enrolled', false)
            ->where('schedule.classroom.name', $assignment->classroom->name)
            ->where('schedule.days.0.day', 'senin')
            ->where('schedule.days.0.label', DayOfWeek::Senin->label())
            ->where('schedule.days.0.periods.0.subject', $assignment->subject->name)
            ->where('schedule.days.0.periods.0.teacher', $assignment->teacher->full_name)
        );
});

test('a student without an enrollment sees the not-enrolled schedule state', function () {
    teacherAttendanceUiContext();
    $unenrolled = Student::factory()->create();

    $this->actingAs($unenrolled->user)
        ->get(route('student.schedule.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('student/Schedule')
            ->where('schedule.not_enrolled', true)
            ->where('schedule.days', [])
            ->where('schedule.classroom', null)
        );
});

test('a student sees their attendance summary and daily rows', function () {
    $context = teacherAttendanceUiContext();
    $student = $context['students'][0];

    DailyAttendance::query()->create([
        'student_id' => $student->id,
        'date' => '2026-10-12',
        'status' => AttendanceStatus::Absent,
    ]);

    $this->actingAs($student->user)
        ->get(route('student.attendance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('student/Attendance')
            ->where('attendance.not_enrolled', false)
            ->where('attendance.days.0.date', '2026-10-12')
            ->where('attendance.days.0.status', 'absent')
            ->where('attendance.days.0.status_label', 'Alpa')
            ->where('attendance.days.0.periods', [])
            ->where('attendance.summary.absent', 1)
            ->where('attendance.summary.school_days', 1)
            ->where('attendance.summary.percentage', 0)
        );
});

test('a student sees final grades with missing components marked', function () {
    $context = teacherAttendanceUiContext();
    $student = $context['students'][0];
    $assignment = $context['assignment'];

    $this->actingAs($student->user)
        ->get(route('student.grades.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('student/Grades')
            ->where('grades.not_enrolled', false)
            ->where('grades.semester.academic_year', $assignment->semester->academicYear->name)
            ->where('grades.classroom.name', $assignment->classroom->name)
            ->where('grades.rows.0.subject', $assignment->subject->name)
            ->where('grades.rows.0.score', null)
            ->where('grades.rows.0.kkm', $assignment->subject->kkm)
            ->where('grades.rows.0.predicate', '-')
            ->where('grades.rows.0.passed', false)
            ->has('grades.rows.0.missing')
        );
});

test('a student sees only their own published report cards', function () {
    $context = teacherAttendanceUiContext();
    $semester = $context['assignment']->semester;
    $student = $context['students'][0];
    $other = $context['students'][1];

    ReportCard::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'snapshot' => ['grades' => []],
        'generated_at' => now(),
        'published_at' => '2026-10-15 08:00:00',
    ]);
    ReportCard::query()->create([
        'student_id' => $other->id,
        'semester_id' => $semester->id,
        'snapshot' => ['grades' => []],
        'generated_at' => now(),
        'published_at' => null,
    ]);

    $this->actingAs($student->user)
        ->get(route('student.report-cards.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('student/ReportCards')
            ->has('reportCards.rows', 1)
            ->where('reportCards.rows.0.term', $semester->name->label())
            ->where('reportCards.rows.0.academic_year', $semester->academicYear->name)
            ->where('reportCards.rows.0.published_at', '15 Okt 2026')
            ->where('reportCards.rows.0.download_url', route('student.report-cards.download', $semester))
        );

    $this->actingAs($other->user)
        ->get(route('student.report-cards.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportCards.rows', [])
        );
});

test('read routes refuse users without the matching role', function (Role $role, string $routeName) {
    $this->actingAs(User::factory()->asRole($role)->create())
        ->get(route($routeName))
        ->assertForbidden();
})->with([
    [Role::Admin, 'student.schedule.index'],
    [Role::Teacher, 'student.grades.index'],
    [Role::Parent, 'student.report-cards.index'],
    [Role::Admin, 'guardian.attendance.index'],
    [Role::Student, 'guardian.grades.index'],
    [Role::Student, 'notifications.index'],
    [Role::Admin, 'notifications.index'],
]);

test('a parent sees their linked child selected by default', function () {
    $context = teacherAttendanceUiContext();
    $student = $context['students'][0];
    $guardian = Guardian::factory()->create();
    $guardian->students()->attach($student->id, ['relationship' => 'Ibu']);

    $this->actingAs($guardian->user)
        ->get(route('guardian.schedule.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parent/Schedule')
            ->has('children', 1)
            ->where('children.0.id', $student->id)
            ->where('children.0.name', $student->full_name)
            ->where('currentChildId', $student->id)
            ->where('schedule.not_enrolled', false)
        );
});

test('a parent switches the selected child with the anak query parameter', function () {
    $context = teacherAttendanceUiContext();
    $guardian = Guardian::factory()->create();
    $first = $context['students'][0];
    $second = $context['students'][1];
    $guardian->students()->attach($first->id, ['relationship' => 'Ibu']);
    $guardian->students()->attach($second->id, ['relationship' => 'Ayah']);

    $this->actingAs($guardian->user)
        ->get(route('guardian.schedule.index', ['anak' => $second->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parent/Schedule')
            ->has('children', 2)
            ->where('currentChildId', $second->id)
        );
});

test('a parent cannot select a child outside their links', function () {
    $guardian = Guardian::factory()->create();
    $linked = Student::factory()->create();
    $guardian->students()->attach($linked->id, ['relationship' => 'Ibu']);
    $stranger = Student::factory()->create();

    $this->actingAs($guardian->user)
        ->get(route('guardian.schedule.index', ['anak' => $stranger->id]))
        ->assertForbidden();

    $this->actingAs($guardian->user)
        ->get(route('guardian.schedule.index', ['anak' => 404404]))
        ->assertForbidden();
});

test('a guardian without linked children gets an empty parent payload', function () {
    $guardian = Guardian::factory()->create();

    $this->actingAs($guardian->user)
        ->get(route('guardian.grades.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parent/Grades')
            ->where('grades', null)
            ->has('children', 0)
            ->where('currentChildId', null)
        );
});

test('a parent sees the child report card with a guardian-scoped download url', function () {
    $context = teacherAttendanceUiContext();
    $student = $context['students'][0];
    $semester = $context['assignment']->semester;
    $guardian = Guardian::factory()->create();
    $guardian->students()->attach($student->id, ['relationship' => 'Ibu']);

    ReportCard::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'snapshot' => ['grades' => []],
        'generated_at' => now(),
        'published_at' => now(),
    ]);

    $this->actingAs($guardian->user)
        ->get(route('guardian.report-cards.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parent/ReportCards')
            ->has('reportCards.rows', 1)
            ->where('reportCards.rows.0.download_url', route('guardian.report-cards.download', [$student, $semester]))
            ->where('currentChildId', $student->id)
        );
});

test('a real absence submission creates the guardian notification the bell and list show', function () {
    Bus::fake([RecomputeDailyAttendance::class]);
    $context = teacherAttendanceUiContext();
    $guardian = Guardian::factory()->create();
    $student = $context['students'][0];
    $student->guardians()->attach($guardian->id, ['relationship' => 'Ibu']);

    app(AttendanceSubmitService::class)->submit(
        $context['schedule'],
        Carbon::parse('2026-10-12'),
        [['student_id' => $student->id, 'status' => 'absent']],
        $context['user'],
    );

    expect($guardian->notifications()->count())->toBe(1);

    $this->actingAs($guardian->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unread_count', 1)
        );

    $this->actingAs($guardian->user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/Index')
            ->has('items', 1)
            ->where('items.0.title', 'Ketidakhadiran anak')
            ->where('items.0.read', false)
            ->where('meta.total', 1)
        );
});

test('a parent can mark a notification read', function () {
    $context = teacherAttendanceUiContext();
    $guardian = Guardian::factory()->create();
    $guardian->notify(new ChildAbsent($context['students'][0], Carbon::parse('2026-10-12')));
    $notification = $guardian->notifications()->firstOrFail();

    $this->actingAs($guardian->user)
        ->from(route('notifications.index'))
        ->patch(route('notifications.mark-read', $notification))
        ->assertRedirect(route('notifications.index'));

    expect($notification->refresh()->read_at)->not->toBeNull();

    $this->actingAs($guardian->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unread_count', 0)
        );
});

test('a parent can mark every notification read at once', function () {
    $context = teacherAttendanceUiContext();
    $guardian = Guardian::factory()->create();
    $guardian->notify(new ChildAbsent($context['students'][0], Carbon::parse('2026-10-12')));
    $guardian->notify(new ChildAbsent($context['students'][1], Carbon::parse('2026-10-13')));

    $this->actingAs($guardian->user)
        ->from(route('notifications.index'))
        ->patch(route('notifications.mark-all-read'))
        ->assertRedirect(route('notifications.index'));

    expect($guardian->unreadNotifications()->count())->toBe(0);
});

test('a parent cannot mark another guardian notification read', function () {
    $context = teacherAttendanceUiContext();
    $guardian = Guardian::factory()->create();
    $otherGuardian = Guardian::factory()->create();
    $otherGuardian->notify(new ChildAbsent($context['students'][0], Carbon::parse('2026-10-12')));
    $foreign = $otherGuardian->notifications()->firstOrFail();

    $this->actingAs($guardian->user)
        ->patch(route('notifications.mark-read', $foreign))
        ->assertNotFound();

    expect($foreign->refresh()->read_at)->toBeNull();
});

test('only parents receive the notification bell payload', function (Role $role) {
    $this->actingAs(User::factory()->asRole($role)->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications', null)
        );
})->with([Role::Student, Role::Teacher, Role::Admin]);
