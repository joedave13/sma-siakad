<?php

namespace App\Services\StudentViews;

use App\Enums\DayOfWeek;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\DailyAttendance;
use App\Models\GradeEntry;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Student;
use App\Services\Grades\FinalScoreCalculator;
use App\Services\ReportCard\ReportCardSnapshotBuilder;
use Illuminate\Support\Collection;

class StudentReadPresenter
{
    public function __construct(
        private readonly FinalScoreCalculator $calculator,
        private readonly ReportCardSnapshotBuilder $snapshotBuilder,
    ) {}

    /**
     * The student's weekly schedule for the active semester.
     *
     * @return array{no_active_year: bool, no_active_semester: bool, not_enrolled: bool, classroom: ?array{id: int, name: string, grade_level: int}, days: list<array{day: string, label: string, periods: list<array{period_number: int, start_time: string, end_time: string, subject: string, teacher: string}>}>}
     */
    public function schedule(Student $student): array
    {
        $year = AcademicYear::query()->where('is_active', true)->first();
        $semester = Semester::query()->where('is_active', true)->first();

        $payload = [
            'no_active_year' => $year === null,
            'no_active_semester' => $semester === null,
            'not_enrolled' => false,
            'classroom' => null,
            'days' => [],
        ];

        if ($year === null || $semester === null) {
            return $payload;
        }

        $classroom = $student->classroomIn($year->id);

        if ($classroom === null) {
            $payload['not_enrolled'] = true;

            return $payload;
        }

        $payload['classroom'] = [
            'id' => $classroom->id,
            'name' => $classroom->name,
            'grade_level' => $classroom->grade_level,
        ];

        $schedules = Schedule::query()
            ->whereHas('teachingAssignment', fn ($query) => $query
                ->where('classroom_id', $classroom->id)
                ->where('semester_id', $semester->id))
            ->with([
                'lessonPeriod:id,number,start_time,end_time',
                'teachingAssignment.subject:id,name',
                'teachingAssignment.teacher:id,full_name',
            ])
            ->get();

        $payload['days'] = $this->groupSchedulesByDay($schedules);

        return $payload;
    }

    /**
     * Daily attendance with per-period history for the active semester.
     *
     * @return array{no_active_semester: bool, not_enrolled: bool, summary: ?array{sick: int, permitted: int, absent: int, late: int, school_days: int, present_days: int, percentage: int}, days: list<array{date: string, status: string, status_label: string, is_late: bool, is_overridden: bool, periods: list<array{period_number: int, subject: string, status_label: string, note: ?string}>}>}
     */
    public function attendance(Student $student): array
    {
        $semester = Semester::query()->where('is_active', true)->first();

        $payload = [
            'no_active_semester' => $semester === null,
            'not_enrolled' => false,
            'summary' => null,
            'days' => [],
        ];

        if ($semester === null) {
            return $payload;
        }

        if ($student->classroomIn((int) $semester->academic_year_id) === null) {
            $payload['not_enrolled'] = true;

            return $payload;
        }

        $range = $semester->start_date !== null && $semester->end_date !== null
            ? [$semester->start_date->toDateString(), $semester->end_date->toDateString()]
            : null;

        $daily = DailyAttendance::query()
            ->where('student_id', $student->getKey())
            ->when($range !== null, fn ($query) => $query->whereBetween('date', $range))
            ->orderByDesc('date')
            ->get();

        $periodsByDate = Attendance::query()
            ->where('student_id', $student->getKey())
            ->whereHas('attendanceSession', fn ($query) => $query
                ->when($range !== null, fn ($query) => $query->whereBetween('date', $range)))
            ->with([
                'attendanceSession.schedule.lessonPeriod:id,number',
                'attendanceSession.schedule.teachingAssignment.subject:id,name',
            ])
            ->get()
            ->groupBy(fn (Attendance $attendance): string => $attendance->attendanceSession->date->toDateString());

        $payload['summary'] = $this->snapshotBuilder->attendanceSummary($student, $semester);
        $payload['days'] = $daily
            ->map(fn (DailyAttendance $day): array => [
                'date' => $day->date->toDateString(),
                'status' => $day->status->value,
                'status_label' => $day->is_late ? 'Hadir (Terlambat)' : $day->status->label(),
                'is_late' => (bool) $day->is_late,
                'is_overridden' => (bool) $day->is_overridden,
                'periods' => ($periodsByDate->get($day->date->toDateString()) ?? collect())
                    ->sortBy(fn (Attendance $attendance) => $attendance->attendanceSession->schedule->lessonPeriod->number)
                    ->values()
                    ->map(fn (Attendance $attendance): array => [
                        'period_number' => $attendance->attendanceSession->schedule->lessonPeriod->number,
                        'subject' => $attendance->attendanceSession->schedule->teachingAssignment->subject->name,
                        'status_label' => $attendance->status->label(),
                        'note' => $attendance->note,
                    ])
                    ->all(),
            ])
            ->all();

        return $payload;
    }

    /**
     * Per-subject final scores for the active semester with missing components.
     *
     * @return array{no_active_semester: bool, not_enrolled: bool, semester: ?array{name: string, academic_year: string}, classroom: ?array{id: int, name: string}, rows: list<array{subject: string, score: ?int, kkm: int, predicate: string, passed: bool, missing: list<string>}>}
     */
    public function grades(Student $student): array
    {
        $semester = Semester::query()->where('is_active', true)->with('academicYear')->first();

        $payload = [
            'no_active_semester' => $semester === null,
            'not_enrolled' => false,
            'semester' => null,
            'classroom' => null,
            'rows' => [],
        ];

        if ($semester === null) {
            return $payload;
        }

        $classroom = $student->classroomIn((int) $semester->academic_year_id);

        if ($classroom === null) {
            $payload['not_enrolled'] = true;

            return $payload;
        }

        $payload['semester'] = [
            'name' => $semester->name->label(),
            'academic_year' => $semester->academicYear->name,
        ];
        $payload['classroom'] = ['id' => $classroom->id, 'name' => $classroom->name];

        $assignments = $semester->teachingAssignments()
            ->where('classroom_id', $classroom->id)
            ->with('subject')
            ->get();

        $payload['rows'] = $assignments
            ->map(function ($assignment) use ($student): array {
                $entries = GradeEntry::query()
                    ->where('teaching_assignment_id', $assignment->getKey())
                    ->where('student_id', $student->getKey())
                    ->get()
                    ->map(fn (GradeEntry $entry): array => [
                        'component' => $entry->component,
                        'score' => $entry->score,
                    ])
                    ->all();

                $score = $this->calculator->finalScore($entries);
                $kkm = $assignment->subject->kkm;

                return [
                    'subject' => $assignment->subject->name,
                    'score' => $score,
                    'kkm' => $kkm,
                    'predicate' => $score === null ? '-' : $this->snapshotBuilder->predicateFor($score, $kkm),
                    'passed' => $score !== null && $score >= $kkm,
                    'missing' => array_map(
                        fn ($component) => $component->label(),
                        $this->calculator->missingComponents($entries),
                    ),
                ];
            })
            ->all();

        return $payload;
    }

    /**
     * Published report cards with their download links.
     *
     * @return array{rows: list<array{semester_id: int, term: string, academic_year: string, published_at: string, download_url: string}>}
     */
    public function reportCards(Student $student, bool $forGuardian): array
    {
        $rows = $student->reportCards()
            ->whereNotNull('published_at')
            ->with('semester.academicYear')
            ->get()
            ->sortByDesc(fn ($reportCard) => $reportCard->semester?->start_date)
            ->values()
            ->map(fn ($reportCard) => [
                'semester_id' => $reportCard->semester_id,
                'term' => $reportCard->semester->name->label(),
                'academic_year' => $reportCard->semester->academicYear->name,
                'published_at' => $reportCard->published_at->translatedFormat('d M Y'),
                'download_url' => $forGuardian
                    ? route('guardian.report-cards.download', [$student, $reportCard->semester])
                    : route('student.report-cards.download', $reportCard->semester),
            ])
            ->all();

        return ['rows' => $rows];
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     * @return list<array{day: string, label: string, periods: list<array{period_number: int, start_time: string, end_time: string, subject: string, teacher: string}>}>
     */
    private function groupSchedulesByDay(Collection $schedules): array
    {
        return collect(DayOfWeek::cases())
            ->map(function (DayOfWeek $day) use ($schedules): ?array {
                $periods = $schedules
                    ->filter(fn (Schedule $schedule): bool => $schedule->day_of_week === $day)
                    ->sortBy(fn (Schedule $schedule): int => $schedule->lessonPeriod->number)
                    ->values()
                    ->map(fn (Schedule $schedule): array => [
                        'period_number' => $schedule->lessonPeriod->number,
                        'start_time' => substr((string) $schedule->lessonPeriod->start_time, 0, 5),
                        'end_time' => substr((string) $schedule->lessonPeriod->end_time, 0, 5),
                        'subject' => $schedule->teachingAssignment->subject->name,
                        'teacher' => $schedule->teachingAssignment->teacher->full_name,
                    ])
                    ->all();

                if ($periods === []) {
                    return null;
                }

                return ['day' => $day->value, 'label' => $day->label(), 'periods' => $periods];
            })
            ->filter()
            ->values()
            ->all();
    }
}
