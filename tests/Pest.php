<?php

use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\LessonPeriod;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Active-semester teacher, assignment, Monday schedule, and enrolled students
 * for the teacher/admin attendance UI tests.
 *
 * @return array{teacher: Teacher, user: User, assignment: TeachingAssignment, schedule: Schedule, students: Collection<int, Student>}
 */
function teacherAttendanceUiContext(): array
{
    $teacher = Teacher::factory()->create();
    $year = AcademicYear::factory()->active()->create();
    $semester = Semester::factory()->active()->ganjil()->create(['academic_year_id' => $year->id]);
    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'semester_id' => $semester->id,
    ]);
    $period = LessonPeriod::factory()->create([
        'number' => 1,
        'start_time' => '07:00',
        'end_time' => '07:45',
    ]);
    $schedule = Schedule::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'lesson_period_id' => $period->id,
        'day_of_week' => 'senin',
    ]);
    $students = Student::factory()->count(2)->create();
    foreach ($students as $student) {
        ClassEnrollment::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $assignment->classroom_id,
            'academic_year_id' => $year->id,
        ]);
    }

    return [
        'teacher' => $teacher,
        'user' => $teacher->user,
        'assignment' => $assignment,
        'schedule' => $schedule,
        'students' => $students,
    ];
}

/**
 * Active-semester homeroom teacher, classroom, assignment, semester, and
 * enrolled students for the teacher homeroom UI tests.
 *
 * @return array{teacher: Teacher, user: User, classroom: Classroom, assignment: TeachingAssignment, semester: Semester, students: Collection<int, Student>}
 */
function teacherHomeroomUiContext(): array
{
    $context = teacherAttendanceUiContext();
    $semester = $context['assignment']->semester;
    $classroom = $context['assignment']->classroom;
    $classroom->update([
        'academic_year_id' => $semester->academic_year_id,
        'homeroom_teacher_id' => $context['teacher']->id,
    ]);

    return [
        'teacher' => $context['teacher'],
        'user' => $context['user'],
        'classroom' => $classroom,
        'assignment' => $context['assignment'],
        'semester' => $semester,
        'students' => $context['students'],
    ];
}
