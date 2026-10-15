<?php

use App\Enums\ClassMajor;
use App\Enums\DayOfWeek;
use App\Enums\Gender;
use App\Enums\Role;
use App\Enums\SemesterTerm;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingAssignment;

it('creates a full linked people-and-org graph', function (): void {
    $student = Student::factory()->create();
    $guardian = Guardian::factory()->create();
    $teacher = Teacher::factory()->create();
    $classroom = Classroom::factory()->create([
        'grade_level' => 11,
        'major' => ClassMajor::Ipa,
    ]);
    $enrollment = ClassEnrollment::factory()->create([
        'classroom_id' => $classroom->id,
        'student_id' => $student->id,
    ]);
    $student->guardians()->attach($guardian->id, ['relationship' => 'ayah']);
    $assignment = TeachingAssignment::factory()->create([
        'classroom_id' => $classroom->id,
        'teacher_id' => $teacher->id,
    ]);
    $schedule = Schedule::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'day_of_week' => DayOfWeek::Senin,
    ]);

    expect($student->user->role)->toBe(Role::Student)
        ->and($student->gender)->toBeInstanceOf(Gender::class)
        ->and($student->status)->toBe(StudentStatus::Active)
        ->and($teacher->user->role)->toBe(Role::Teacher)
        ->and($guardian->user->role)->toBe(Role::Parent)
        ->and($classroom->major)->toBeInstanceOf(ClassMajor::class)
        ->and($classroom->academicYear->exists)->toBeTrue()
        ->and($enrollment->academic_year_id)->toBe($classroom->academic_year_id)
        ->and($student->guardians()->first()->id)->toBe($guardian->id)
        ->and($guardian->students()->first()->id)->toBe($student->id)
        ->and($student->classroomIn($classroom->academic_year_id)?->id)->toBe($classroom->id)
        ->and($assignment->teacher->id)->toBe($teacher->id)
        ->and($assignment->semester->name)->toBeInstanceOf(SemesterTerm::class)
        ->and($schedule->day_of_week)->toBe(DayOfWeek::Senin)
        ->and($schedule->lessonPeriod->exists)->toBeTrue();
});

it('blocks assigning a student to two classes in the same year', function (): void {
    $student = Student::factory()->create();
    $year = AcademicYear::factory()->create();

    ClassEnrollment::factory()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
    ]);

    ClassEnrollment::factory()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
    ]);
})->throws(RuntimeException::class);
