<?php

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

test('guests are redirected from the master index pages', function (string $routeName): void {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with([
    'academic years' => 'admin.master.academic-years.index',
    'semesters' => 'admin.master.semesters.index',
    'lesson periods' => 'admin.master.lesson-periods.index',
    'subjects' => 'admin.master.subjects.index',
    'classrooms' => 'admin.master.classrooms.index',
]);

test('non-admin users cannot access the master index pages', function (string $routeName): void {
    $this->actingAs(User::factory()->asRole(Role::Teacher)->create())
        ->get(route($routeName))
        ->assertForbidden();
})->with([
    'academic years' => 'admin.master.academic-years.index',
    'semesters' => 'admin.master.semesters.index',
    'lesson periods' => 'admin.master.lesson-periods.index',
    'subjects' => 'admin.master.subjects.index',
    'classrooms' => 'admin.master.classrooms.index',
]);

test('a teacher cannot delete a master record', function (): void {
    $subject = Subject::factory()->create();

    $this->actingAs(User::factory()->asRole(Role::Teacher)->create())
        ->delete(route('admin.master.subjects.destroy', $subject))
        ->assertForbidden();

    expect($subject->fresh()->deleted_at)->toBeNull();
});

test('admins can open each master index page', function (string $routeName, string $component): void {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create())
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'academic years' => ['admin.master.academic-years.index', 'admin/master/AcademicYears'],
    'semesters' => ['admin.master.semesters.index', 'admin/master/Semesters'],
    'lesson periods' => ['admin.master.lesson-periods.index', 'admin/master/LessonPeriods'],
    'subjects' => ['admin.master.subjects.index', 'admin/master/Subjects'],
    'classrooms' => ['admin.master.classrooms.index', 'admin/master/Classrooms'],
]);

test('admins can create, edit, and soft-delete an academic year', function (): void {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.master.academic-years.store'), [
        'name' => '2030/2031',
        'start_date' => '2030-07-01',
        'end_date' => '2031-06-30',
        'is_active' => '1',
    ])->assertRedirect(route('admin.master.academic-years.index'));

    $year = AcademicYear::query()->where('name', '2030/2031')->firstOrFail();

    expect($year->is_active)->toBeTrue()
        ->and(session('inertia.flash_data')['toast']['message'])->toBe('Tahun ajaran berhasil ditambahkan.');

    $this->put(route('admin.master.academic-years.update', $year), [
        'name' => '2030/2031',
        'start_date' => '2030-07-15',
        'end_date' => '2031-06-30',
    ])->assertRedirect(route('admin.master.academic-years.index'));

    expect($year->fresh()->start_date->toDateString())->toBe('2030-07-15');

    $this->delete(route('admin.master.academic-years.destroy', $year))
        ->assertRedirect(route('admin.master.academic-years.index'));

    expect($year->fresh()->deleted_at)->not->toBeNull()
        ->and(AuditLog::query()->where('auditable_id', $year->id)->pluck('action')->all())
        ->toBe(['academic_year.create', 'academic_year.update', 'academic_year.delete']);
});

test('admins can create, edit, and soft-delete a semester', function (): void {
    $year = AcademicYear::factory()->create();
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.master.semesters.store'), [
        'academic_year_id' => $year->id,
        'name' => 'ganjil',
        'start_date' => '2026-07-15',
        'end_date' => '2026-12-31',
        'is_active' => '1',
    ])->assertRedirect(route('admin.master.semesters.index'));

    $semester = Semester::query()->where('name', 'ganjil')->firstOrFail();

    expect($semester->academic_year_id)->toBe($year->id)
        ->and($semester->is_active)->toBeTrue()
        ->and(session('inertia.flash_data')['toast']['message'])->toBe('Semester berhasil ditambahkan.');

    $this->get(route('admin.master.semesters.edit', $semester))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/master/SemesterForm')
            ->where('semester.id', $semester->id)
            ->has('academicYears', 1));

    $this->put(route('admin.master.semesters.update', $semester), [
        'academic_year_id' => $year->id,
        'name' => 'genap',
        'start_date' => '2027-01-04',
        'end_date' => '2027-06-30',
    ])->assertRedirect(route('admin.master.semesters.index'));

    $semester->refresh();

    expect($semester->name->value)->toBe('genap')
        ->and($semester->start_date->toDateString())->toBe('2027-01-04');

    $this->delete(route('admin.master.semesters.destroy', $semester))
        ->assertRedirect(route('admin.master.semesters.index'));

    expect($semester->fresh()->deleted_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'semester.delete')->count())->toBe(1);
});

test('admins can create, edit, and soft-delete a lesson period', function (): void {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.master.lesson-periods.store'), [
        'number' => 11,
        'start_time' => '13:00',
        'end_time' => '13:45',
    ])->assertRedirect(route('admin.master.lesson-periods.index'));

    $period = LessonPeriod::query()->where('number', 11)->firstOrFail();

    expect(session('inertia.flash_data')['toast']['message'])->toBe('Jam pelajaran berhasil ditambahkan.');

    $this->put(route('admin.master.lesson-periods.update', $period), [
        'number' => 11,
        'start_time' => '13:10',
        'end_time' => '13:55',
    ])->assertRedirect(route('admin.master.lesson-periods.index'));

    expect($period->fresh()->start_time)->toContain('13:10');

    $this->delete(route('admin.master.lesson-periods.destroy', $period))
        ->assertRedirect(route('admin.master.lesson-periods.index'));

    expect($period->fresh()->deleted_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'lesson_period.delete')->count())->toBe(1);
});

test('admins can create, edit, and soft-delete a subject', function (): void {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.master.subjects.store'), [
        'code' => 'MTK',
        'name' => 'Matematika',
        'kkm' => 75,
    ])->assertRedirect(route('admin.master.subjects.index'));

    $subject = Subject::query()->where('code', 'MTK')->firstOrFail();

    expect(session('inertia.flash_data')['toast']['message'])->toBe('Mata pelajaran berhasil ditambahkan.');

    $this->put(route('admin.master.subjects.update', $subject), [
        'code' => 'MTK',
        'name' => 'Matematika Lanjutan',
        'kkm' => 80,
    ])->assertRedirect(route('admin.master.subjects.index'));

    expect($subject->fresh()->kkm)->toBe(80);

    $this->delete(route('admin.master.subjects.destroy', $subject))
        ->assertRedirect(route('admin.master.subjects.index'));

    expect($subject->fresh()->deleted_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'subject.delete')->count())->toBe(1);
});

test('admins can create, edit, and soft-delete a classroom', function (): void {
    $year = AcademicYear::factory()->create();
    $teacher = Teacher::factory()->create();
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.master.classrooms.store'), [
        'academic_year_id' => $year->id,
        'name' => 'XI-IPA-1',
        'grade_level' => 11,
    ])->assertRedirect(route('admin.master.classrooms.index'));

    $classroom = Classroom::query()->where('name', 'XI-IPA-1')->firstOrFail();

    expect($classroom->major)->toBeNull()
        ->and(session('inertia.flash_data')['toast']['message'])->toBe('Ruang kelas berhasil ditambahkan.');

    $this->get(route('admin.master.classrooms.edit', $classroom))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/master/ClassroomForm')
            ->where('classroom.id', $classroom->id)
            ->has('academicYears', 1)
            ->has('teachers', 1));

    $this->put(route('admin.master.classrooms.update', $classroom), [
        'academic_year_id' => $year->id,
        'name' => 'XI-IPA-1',
        'grade_level' => 11,
        'major' => 'ipa',
        'homeroom_teacher_id' => $teacher->id,
    ])->assertRedirect(route('admin.master.classrooms.index'));

    $classroom->refresh();

    expect($classroom->major?->value)->toBe('ipa')
        ->and($classroom->homeroom_teacher_id)->toBe($teacher->id);

    $this->delete(route('admin.master.classrooms.destroy', $classroom))
        ->assertRedirect(route('admin.master.classrooms.index'));

    expect($classroom->fresh()->deleted_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'classroom.delete')->count())->toBe(1);
});

test('activating a new academic year over http deactivates the previous one', function (): void {
    $first = AcademicYear::factory()->active()->create();
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.master.academic-years.store'), [
        'name' => '2031/2032',
        'start_date' => '2031-07-01',
        'end_date' => '2032-06-30',
        'is_active' => '1',
    ]);

    expect($first->fresh()->is_active)->toBeFalse()
        ->and(AcademicYear::query()->where('is_active', true)->count())->toBe(1);
});

test('activating a new semester over http deactivates the previous one', function (): void {
    $year = AcademicYear::factory()->create();
    $first = Semester::factory()->ganjil()->active()->create(['academic_year_id' => $year->id]);
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.master.semesters.store'), [
        'academic_year_id' => $year->id,
        'name' => 'genap',
        'is_active' => '1',
    ]);

    expect($first->fresh()->is_active)->toBeFalse()
        ->and(Semester::query()->where('is_active', true)->count())->toBe(1);
});

test('master forms validate required fields in indonesian', function (): void {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.master.subjects.store'), [])
        ->assertSessionHasErrors([
            'code' => 'Bidang kode wajib diisi.',
            'name' => 'Bidang nama wajib diisi.',
            'kkm' => 'Bidang kkm wajib diisi.',
        ]);

    $this->post(route('admin.master.academic-years.store'), [
        'name' => '2026/2027',
        'start_date' => '2026-07-01',
        'end_date' => '2026-06-01',
    ])->assertSessionHasErrors('end_date');

    $this->post(route('admin.master.lesson-periods.store'), [
        'number' => 1,
        'start_time' => '07:30',
        'end_time' => '07:00',
    ])->assertSessionHasErrors('end_time');
});

test('duplicate codes are rejected even after the subject is soft-deleted', function (): void {
    Subject::factory()->create(['code' => 'MTK'])->delete();
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.master.subjects.store'), [
        'code' => 'MTK',
        'name' => 'Matematika',
        'kkm' => 75,
    ])->assertSessionHasErrors(['code' => 'Bidang kode sudah digunakan.']);
});

test('an academic year with dependents cannot be deleted', function (): void {
    $year = AcademicYear::factory()->create();
    Semester::factory()->create(['academic_year_id' => $year->id]);
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->delete(route('admin.master.academic-years.destroy', $year))
        ->assertSessionHasErrors('delete');

    expect(session('errors')->first('delete'))
        ->toContain('Data tidak dapat dihapus karena masih digunakan oleh')
        ->toContain('semester')
        ->and($year->fresh()->deleted_at)->toBeNull();
});

test('a semester with grades cannot be deleted even after its assignment is trashed', function (): void {
    $semester = Semester::factory()->ganjil()->create();
    $assignment = TeachingAssignment::factory()->create(['semester_id' => $semester->id]);
    GradeEntry::factory()->create(['teaching_assignment_id' => $assignment->id]);
    $assignment->delete();
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->delete(route('admin.master.semesters.destroy', $semester))
        ->assertSessionHasErrors('delete');

    expect(session('errors')->first('delete'))->toContain('nilai')
        ->and($semester->fresh()->deleted_at)->toBeNull();
});

test('a lesson period used by attendance sessions cannot be deleted', function (): void {
    $period = LessonPeriod::factory()->create();
    $schedule = Schedule::factory()->create(['lesson_period_id' => $period->id]);
    AttendanceSession::forceCreate([
        'schedule_id' => $schedule->id,
        'date' => '2026-10-16',
    ]);
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->delete(route('admin.master.lesson-periods.destroy', $period))
        ->assertSessionHasErrors('delete');

    expect(session('errors')->first('delete'))->toContain('jadwal')
        ->toContain('sesi presensi')
        ->and($period->fresh()->deleted_at)->toBeNull();
});

test('a subject with teaching assignments cannot be deleted', function (): void {
    $assignment = TeachingAssignment::factory()->create();
    $subject = $assignment->subject;
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->delete(route('admin.master.subjects.destroy', $subject))
        ->assertSessionHasErrors('delete');

    expect(session('errors')->first('delete'))->toContain('penugasan mengajar')
        ->and($subject->fresh()->deleted_at)->toBeNull();
});

test('a classroom with enrollments cannot be deleted', function (): void {
    $year = AcademicYear::factory()->create();
    $classroom = Classroom::factory()->create(['academic_year_id' => $year->id]);
    $student = Student::factory()->create();
    ClassEnrollment::forceCreate([
        'classroom_id' => $classroom->id,
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
    ]);
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->delete(route('admin.master.classrooms.destroy', $classroom))
        ->assertSessionHasErrors('delete');

    expect(session('errors')->first('delete'))->toContain('peserta didik terdaftar')
        ->and($classroom->fresh()->deleted_at)->toBeNull();
});
