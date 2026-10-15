<?php

use App\Enums\DayOfWeek;
use App\Enums\Role;
use App\Models\Classroom;
use App\Models\LessonPeriod;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\Scheduling\ScheduleCollisionService;
use Illuminate\Support\Facades\Gate;

it('blocks enrolling a student into two classes in the same year', function (): void {
    $service = app(ScheduleCollisionService::class);
    $student = Student::factory()->create();
    $classroom = Classroom::factory()->create();

    $enrollment = $classroom->enrollments()->create([
        'student_id' => $student->id,
        'academic_year_id' => $classroom->academic_year_id,
    ]);

    $message = $service->enrollmentConflictMessage($student, $classroom->academicYear);

    expect($message)->toBe('Siswa sudah terdaftar di kelas lain pada tahun ajaran ini.')
        ->and($service->enrollmentConflictMessage($student, $classroom->academicYear, $enrollment))->toBeNull();
});

it('blocks a second assignment for the same subject-class in one semester', function (): void {
    $service = app(ScheduleCollisionService::class);
    $assignment = TeachingAssignment::factory()->create();

    $message = $service->assignmentConflictMessage(
        $assignment->semester,
        $assignment->classroom,
        $assignment->subject,
    );

    expect($message)->toBe('Mata pelajaran ini sudah terdaftar untuk kelas tersebut pada semester ini.');

    $otherSubject = Subject::factory()->create();
    expect($service->assignmentConflictMessage(
        $assignment->semester,
        $assignment->classroom,
        $otherSubject,
    ))->toBeNull();

    expect($service->assignmentConflictMessage(
        $assignment->semester,
        $assignment->classroom,
        $assignment->subject,
        $assignment,
    ))->toBeNull();
});

it('blocks scheduling a teacher in two classes at the same day and period', function (): void {
    $service = app(ScheduleCollisionService::class);
    $teacher = Teacher::factory()->create();
    $period = LessonPeriod::factory()->create(['number' => 1]);
    $first = Schedule::factory()->create([
        'day_of_week' => DayOfWeek::Senin,
        'lesson_period_id' => $period->id,
        'teaching_assignment_id' => TeachingAssignment::factory()->create(['teacher_id' => $teacher->id]),
    ]);

    $message = $service->teacherConflictMessage($teacher, DayOfWeek::Senin, $period);

    expect($message)->toBe('Guru tersebut sudah mengajar di kelas lain pada hari dan jam pelajaran yang sama.')
        ->and($service->teacherConflictMessage($teacher, DayOfWeek::Senin, $period, $first))->toBeNull();
});

it('allows the same teacher on other days or periods', function (): void {
    $service = app(ScheduleCollisionService::class);
    $teacher = Teacher::factory()->create();
    $period = LessonPeriod::factory()->create(['number' => 1]);
    Schedule::factory()->create([
        'day_of_week' => DayOfWeek::Senin,
        'lesson_period_id' => $period->id,
        'teaching_assignment_id' => TeachingAssignment::factory()->create(['teacher_id' => $teacher->id]),
    ]);

    $later = LessonPeriod::factory()->create(['number' => 2]);

    expect($service->teacherConflictMessage($teacher, DayOfWeek::Selasa, $period))->toBeNull()
        ->and($service->teacherConflictMessage($teacher, DayOfWeek::Senin, $later))->toBeNull();
});

it('lets admins pass every ability and blocks other roles on academic master', function (): void {
    $admin = User::factory()->asRole(Role::Admin)->create();
    $teacherUser = User::factory()->asRole(Role::Teacher)->create();

    expect(Gate::forUser($admin)->allows('manageAcademicMaster'))->toBeTrue()
        ->and(Gate::forUser($teacherUser)->allows('manageAcademicMaster'))->toBeFalse();
});
