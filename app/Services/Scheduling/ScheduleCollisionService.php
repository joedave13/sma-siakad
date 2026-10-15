<?php

namespace App\Services\Scheduling;

use App\Enums\DayOfWeek;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\LessonPeriod;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;

class ScheduleCollisionService
{
    /**
     * A3 AC: one class per student per academic year.
     * Returns an Indonesian error message, or null when the enrollment is allowed.
     */
    public function enrollmentConflictMessage(
        Student $student,
        AcademicYear $academicYear,
        ?ClassEnrollment $ignore = null,
    ): ?string {
        $exists = ClassEnrollment::query()
            ->where('student_id', $student->getKey())
            ->where('academic_year_id', $academicYear->getKey())
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();

        return $exists
            ? 'Siswa sudah terdaftar di kelas lain pada tahun ajaran ini.'
            : null;
    }

    /**
     * A3 AC: one teacher per subject-class per semester.
     * Returns an Indonesian error message, or null when the assignment is allowed.
     */
    public function assignmentConflictMessage(
        Semester $semester,
        Classroom $classroom,
        Subject $subject,
        ?TeachingAssignment $ignore = null,
    ): ?string {
        $exists = TeachingAssignment::query()
            ->where('semester_id', $semester->getKey())
            ->where('classroom_id', $classroom->getKey())
            ->where('subject_id', $subject->getKey())
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();

        return $exists
            ? 'Mata pelajaran ini sudah terdaftar untuk kelas tersebut pada semester ini.'
            : null;
    }

    /**
     * A3 AC: a teacher cannot be scheduled in two classes in the same period.
     * Returns an Indonesian error message, or null when the slot is free.
     */
    public function teacherConflictMessage(
        Teacher $teacher,
        DayOfWeek $day,
        LessonPeriod $period,
        ?Schedule $ignore = null,
    ): ?string {
        $exists = Schedule::query()
            ->where('day_of_week', $day->value)
            ->where('lesson_period_id', $period->getKey())
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->whereHas('teachingAssignment', fn ($query) => $query->where('teacher_id', $teacher->getKey()))
            ->exists();

        return $exists
            ? 'Guru tersebut sudah mengajar di kelas lain pada hari dan jam pelajaran yang sama.'
            : null;
    }
}
