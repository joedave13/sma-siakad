<?php

use App\Enums\AttendanceStatus;
use App\Enums\GradeComponent;
use App\Enums\Role;
use App\Models\DailyAttendance;
use App\Models\GradeEntry;
use App\Models\Guardian;
use App\Models\ReportCard;
use App\Models\SchoolProfile;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
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
            ->where('nav.1.items.1.key', 'accounts')
            ->where('nav.1.items.1.href', route('admin.users.students.index'))
            ->where('nav.1.items.1.coming_soon', false)
            ->where('nav.1.items.2.key', 'schedule')
            ->where('nav.1.items.2.href', route('admin.schedule.enrollments.index'))
            ->where('nav.1.items.2.coming_soon', false)
            ->where('nav.3.label', 'Sistem')
            ->has('nav.3.items', 2)
            ->where('nav.3.items.0.key', 'school-settings')
            ->where('nav.3.items.0.href', route('admin.school-settings.edit'))
            ->where('nav.3.items.0.coming_soon', false)
            ->where('nav.3.items.1.key', 'audit-log')
            ->where('nav.3.items.1.href', route('admin.audit.index'))
            ->where('nav.3.items.1.coming_soon', false)
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
            ->where('nav.1.items.0.href', route('student.schedule.index'))
            ->where('nav.1.items.0.coming_soon', false)
            ->where('nav.1.items.3.key', 'report-card')
            ->where('nav.1.items.3.href', route('student.report-cards.index'))
            ->where('nav.1.items.3.coming_soon', false)
        );
});

test('parent navigation lists only the child destinations', function () {
    $this->actingAs(User::factory()->asRole(Role::Parent)->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('nav', 2)
            ->where('nav.1.label', 'Anak')
            ->has('nav.1.items', 4)
            ->where('nav.1.items.0.key', 'schedule')
            ->where('nav.1.items.0.href', route('guardian.schedule.index'))
            ->where('nav.1.items.0.coming_soon', false)
            ->where('nav.1.items.3.key', 'report-card')
            ->where('nav.1.items.3.href', route('guardian.report-cards.index'))
            ->where('nav.1.items.3.coming_soon', false)
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

test('the admin dashboard lists students whose complete scores fall below the KKM', function () {
    $context = teacherAttendanceUiContext();
    $assignment = $context['assignment'];
    $studentA = $context['students'][0];
    $studentB = $context['students'][1];

    $failAllComponents = function (int $assignmentId, int $studentId, int $score): void {
        foreach (GradeComponent::cases() as $component) {
            GradeEntry::factory()->create([
                'teaching_assignment_id' => $assignmentId,
                'student_id' => $studentId,
                'component' => $component,
                'title' => $component->label(),
                'score' => $score,
            ]);
        }
    };

    $failAllComponents($assignment->id, $studentA->id, $assignment->subject->kkm - 5);
    $failAllComponents($assignment->id, $studentB->id, $assignment->subject->kkm - 5);

    $secondAssignment = TeachingAssignment::factory()->create([
        'semester_id' => $assignment->semester_id,
        'classroom_id' => $assignment->classroom_id,
    ]);
    $failAllComponents($secondAssignment->id, $studentA->id, $secondAssignment->subject->kkm - 5);

    $incomplete = Student::factory()->create();
    GradeEntry::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'student_id' => $incomplete->id,
        'component' => GradeComponent::Assignment,
        'score' => 0,
    ]);

    $this->actingAs(User::factory()->asRole(Role::Admin)->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('belowKkm.total', 2)
            ->where('belowKkm.evaluated', 3)
            ->has('belowKkm.rows', 2)
            ->where('belowKkm.rows.0.name', $studentA->full_name)
            ->where('belowKkm.rows.0.classroom', $assignment->classroom->name)
            ->where('belowKkm.rows.0.failing_subjects', 2)
            ->where('belowKkm.rows.1.name', $studentB->full_name)
            ->where('belowKkm.rows.1.failing_subjects', 1)
            ->where('todayPeriods', null)
        );
});

test('the admin dashboard counts generated report cards awaiting publication', function () {
    $context = teacherAttendanceUiContext();
    $semester = $context['assignment']->semester;

    ReportCard::query()->create([
        'student_id' => $context['students'][0]->id,
        'semester_id' => $semester->id,
        'snapshot' => ['grades' => []],
        'generated_at' => now(),
        'published_at' => null,
    ]);
    ReportCard::query()->create([
        'student_id' => $context['students'][1]->id,
        'semester_id' => $semester->id,
        'snapshot' => ['grades' => []],
        'generated_at' => now(),
        'published_at' => now(),
    ]);

    $this->actingAs(User::factory()->asRole(Role::Admin)->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('unpublishedReportCards.total', 1)
            ->where('unpublishedReportCards.generated_total', 2)
            ->has('unpublishedReportCards.semesters', 1)
            ->where('unpublishedReportCards.semesters.0.semester_id', $semester->id)
            ->where('unpublishedReportCards.semesters.0.term', $semester->name->label())
            ->where('unpublishedReportCards.semesters.0.academic_year', $semester->academicYear->name)
            ->where('unpublishedReportCards.semesters.0.count', 1)
            ->where('belowKkm.total', 0)
        );
});

test('the teacher dashboard lists today periods with their submission state', function () {
    $context = teacherAttendanceUiContext();
    $this->travelTo(Carbon::parse('2026-10-12 08:00'));

    $this->actingAs($context['user'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('todayPeriods', 1)
            ->where('todayPeriods.0.schedule_id', $context['schedule']->id)
            ->where('todayPeriods.0.period_number', 1)
            ->where('todayPeriods.0.subject', $context['assignment']->subject->name)
            ->where('todayPeriods.0.classroom', $context['assignment']->classroom->name)
            ->where('todayPeriods.0.submitted', false)
            ->has('pendingSubmissions', 1)
            ->where('pendingSubmissions.0.schedule_id', $context['schedule']->id)
            ->where('pendingSubmissions.0.date', '2026-10-12')
            ->where('pendingSubmissions.0.date_label', 'Hari ini')
            ->where('missingSessions', null)
            ->where('belowKkm', null)
            ->where('latestReportCard', null)
        );
});

test('the teacher dashboard marks a submitted session and drops it from pending', function () {
    $context = teacherAttendanceUiContext();
    $this->travelTo(Carbon::parse('2026-10-12 08:00'));

    $this->actingAs($context['user'])
        ->post(route('teacher.attendance.store', $context['schedule']), [
            'date' => '2026-10-12',
            'entries' => [
                ['student_id' => $context['students'][0]->id, 'status' => 'present'],
                ['student_id' => $context['students'][1]->id, 'status' => 'present'],
            ],
        ])
        ->assertRedirect();

    $this->actingAs($context['user'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('todayPeriods', 1)
            ->where('todayPeriods.0.submitted', true)
            ->has('pendingSubmissions', 0)
        );
});

test('the teacher dashboard surfaces yesterday unsent sessions within the edit window', function () {
    $context = teacherAttendanceUiContext();
    $this->travelTo(Carbon::parse('2026-10-13 08:00'));

    $this->actingAs($context['user'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('todayPeriods', 0)
            ->has('pendingSubmissions', 1)
            ->where('pendingSubmissions.0.schedule_id', $context['schedule']->id)
            ->where('pendingSubmissions.0.date', '2026-10-12')
            ->where('pendingSubmissions.0.date_label', 'Kemarin, 12 Okt 2026')
        );
});

test('the student dashboard shows the latest published report card', function () {
    $context = teacherAttendanceUiContext();
    $semester = $context['assignment']->semester;
    $student = $context['students'][0];

    ReportCard::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'snapshot' => ['grades' => []],
        'generated_at' => now(),
        'published_at' => '2026-10-15 08:00:00',
    ]);

    $this->actingAs($student->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('latestReportCard.term', $semester->name->label())
            ->where('latestReportCard.academic_year', $semester->academicYear->name)
            ->where('latestReportCard.published_at', '15 Okt 2026')
            ->where('latestReportCard.view_url', route('student.report-cards.index'))
            ->where('recentAbsences', null)
            ->where('belowKkm', null)
        );
});

test('the student dashboard has no report card before anything is published', function () {
    $this->actingAs(User::factory()->asRole(Role::Student)->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('latestReportCard', null)
            ->where('childCount', null)
        );
});

test('the parent dashboard shows the linked childs report card and absences', function () {
    $context = teacherAttendanceUiContext();
    $semester = $context['assignment']->semester;
    $child = $context['students'][0];

    $guardian = Guardian::factory()->create();
    $guardian->students()->attach($child->id, ['relationship' => 'Ibu']);

    ReportCard::query()->create([
        'student_id' => $child->id,
        'semester_id' => $semester->id,
        'snapshot' => ['grades' => []],
        'generated_at' => now(),
        'published_at' => '2026-10-15 08:00:00',
    ]);

    DailyAttendance::query()->create([
        'student_id' => $child->id,
        'date' => '2026-10-12',
        'status' => AttendanceStatus::Absent,
        'is_late' => false,
        'is_overridden' => false,
        'computed_at' => now(),
    ]);
    DailyAttendance::query()->create([
        'student_id' => $child->id,
        'date' => '2026-10-13',
        'status' => AttendanceStatus::Present,
        'is_late' => false,
        'is_overridden' => false,
        'computed_at' => now(),
    ]);

    $this->actingAs($guardian->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('childCount', 1)
            ->where('currentChildName', null)
            ->where('latestReportCard.term', $semester->name->label())
            ->where('latestReportCard.published_at', '15 Okt 2026')
            ->where('latestReportCard.view_url', route('guardian.report-cards.index'))
            ->has('recentAbsences', 1)
            ->where('recentAbsences.0.date', '12 Okt 2026')
            ->where('recentAbsences.0.status_label', 'Alpa')
        );
});

test('the parent dashboard without linked children renders empty child data', function () {
    $guardian = Guardian::factory()->create();

    $this->actingAs($guardian->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('childCount', 0)
            ->where('currentChildName', null)
            ->where('latestReportCard', null)
            ->where('recentAbsences', [])
        );
});

test('the parent dashboard names the current child when several are linked', function () {
    $context = teacherAttendanceUiContext();
    $first = $context['students'][0];
    $second = $context['students'][1];

    $guardian = Guardian::factory()->create();
    $guardian->students()->attach($first->id, ['relationship' => 'Ibu']);
    $guardian->students()->attach($second->id, ['relationship' => 'Ayah']);

    $this->actingAs($guardian->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('childCount', 2)
            ->where('currentChildName', $guardian->students->first()->full_name)
        );
});
