<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Enums\Role;
use App\Models\DailyAttendance;
use App\Models\GradeEntry;
use App\Models\Guardian;
use App\Models\ReportCard;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Grades\FinalScoreCalculator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly FinalScoreCalculator $calculator) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $role = $user->role;

        $student = $role === Role::Student ? $this->resolveStudent($user) : null;
        $guardian = $role === Role::Parent
            ? Guardian::query()->where('user_id', $user->getKey())->first()
            : null;
        $children = $guardian?->students;
        $child = $children?->first();

        return Inertia::render('Dashboard', [
            'missingSessions' => $role === Role::Admin ? $this->missingSessions() : null,
            'belowKkm' => $role === Role::Admin ? $this->belowKkm() : null,
            'unpublishedReportCards' => $role === Role::Admin ? $this->unpublishedReportCards() : null,
            'todayPeriods' => $role === Role::Teacher ? $this->todayPeriods($user) : null,
            'pendingSubmissions' => $role === Role::Teacher ? $this->pendingSubmissions($user) : null,
            'latestReportCard' => match (true) {
                $role === Role::Student => $this->latestReportCardFor($student, false),
                $role === Role::Parent => $this->latestReportCardFor($child, true),
                default => null,
            },
            'recentAbsences' => $role === Role::Parent ? $this->recentAbsencesFor($child) : null,
            'childCount' => $role === Role::Parent ? ($children?->count() ?? 0) : null,
            'currentChildName' => $role === Role::Parent
                ? ($children !== null && $children->count() > 1 ? $child?->full_name : null)
                : null,
        ]);
    }

    /**
     * Today's active-semester schedules that have no attendance session yet.
     *
     * @return list<array{schedule_id: int, period_number: int, start_time: string, end_time: string, subject: string, classroom: string, teacher: string, date: string}>
     */
    private function missingSessions(): array
    {
        $today = Date::today();
        $day = DayOfWeek::fromDate($today);

        if ($day === null) {
            return [];
        }

        $schedules = Schedule::query()
            ->where('day_of_week', $day->value)
            ->whereHas('teachingAssignment.semester', fn ($query) => $query->where('is_active', true))
            ->whereDoesntHave('attendanceSessions', fn ($query) => $query->where('date', $today->toDateString()))
            ->with([
                'lessonPeriod:id,number,start_time,end_time',
                'teachingAssignment.subject:id,name',
                'teachingAssignment.classroom:id,name',
                'teachingAssignment.teacher:id,full_name',
            ])
            ->join('lesson_periods', 'lesson_periods.id', '=', 'schedules.lesson_period_id')
            ->orderBy('lesson_periods.number')
            ->select('schedules.*')
            ->get();

        return array_values($schedules
            ->map(fn (Schedule $schedule): array => [
                'schedule_id' => $schedule->id,
                'period_number' => $schedule->lessonPeriod->number,
                'start_time' => substr((string) $schedule->lessonPeriod->start_time, 0, 5),
                'end_time' => substr((string) $schedule->lessonPeriod->end_time, 0, 5),
                'subject' => $schedule->teachingAssignment->subject->name,
                'classroom' => $schedule->teachingAssignment->classroom->name,
                'teacher' => $schedule->teachingAssignment->teacher->full_name,
                'date' => $today->toDateString(),
            ])
            ->all());
    }

    /**
     * Students with a complete final score below their subject's KKM in the
     * active semester. Incomplete scores are not failures, so they are only
     * counted in $evaluated.
     *
     * @return array{total: int, evaluated: int, rows: list<array{student_id: int, name: string, classroom: string, failing_subjects: int}>}
     */
    private function belowKkm(): array
    {
        $semester = Semester::query()->where('is_active', true)->first();

        if ($semester === null) {
            return ['total' => 0, 'evaluated' => 0, 'rows' => []];
        }

        $assignments = $semester->teachingAssignments()
            ->with(['subject:id,name,kkm', 'classroom:id,name'])
            ->get();

        if ($assignments->isEmpty()) {
            return ['total' => 0, 'evaluated' => 0, 'rows' => []];
        }

        $entriesByAssignment = GradeEntry::query()
            ->whereIn('teaching_assignment_id', $assignments->pluck('id'))
            ->get()
            ->groupBy('teaching_assignment_id');

        $failing = [];
        $evaluated = 0;

        foreach ($assignments as $assignment) {
            $kkm = $assignment->subject->kkm;

            $perStudent = $entriesByAssignment
                ->get($assignment->getKey(), collect())
                ->groupBy(fn (GradeEntry $entry): int => (int) $entry->student_id);

            foreach ($perStudent as $studentId => $entries) {
                $score = $this->calculator->finalScore(array_values($entries
                    ->map(fn (GradeEntry $entry): array => [
                        'component' => $entry->component,
                        'score' => $entry->score,
                    ])
                    ->all()));

                if ($score === null) {
                    continue;
                }

                $evaluated++;

                if ($score >= $kkm) {
                    continue;
                }

                if (! isset($failing[$studentId])) {
                    $failing[$studentId] = [
                        'classroom' => $assignment->classroom->name,
                        'subjects' => [],
                    ];
                }

                $failing[$studentId]['subjects'][] = $assignment->subject->name;
            }
        }

        if ($failing === []) {
            return ['total' => 0, 'evaluated' => $evaluated, 'rows' => []];
        }

        $students = Student::query()
            ->whereIn('id', array_keys($failing))
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($failing as $studentId => $info) {
            $student = $students->get($studentId);

            if ($student === null) {
                continue;
            }

            $rows[] = [
                'student_id' => (int) $studentId,
                'name' => $student->full_name,
                'classroom' => $info['classroom'],
                'failing_subjects' => count($info['subjects']),
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['failing_subjects'] <=> $a['failing_subjects'] ?: strcmp($a['name'], $b['name']));

        return [
            'total' => count($rows),
            'evaluated' => $evaluated,
            'rows' => array_slice($rows, 0, 8),
        ];
    }

    /**
     * Report cards that were generated but not yet published, grouped by semester.
     *
     * @return array{total: int, generated_total: int, semesters: list<array{semester_id: int, term: string, academic_year: string, count: int}>}
     */
    private function unpublishedReportCards(): array
    {
        $counts = [];
        foreach (ReportCard::query()->whereNull('published_at')->pluck('semester_id') as $semesterId) {
            $id = (int) $semesterId;
            $counts[$id] = ($counts[$id] ?? 0) + 1;
        }

        $generatedTotal = ReportCard::query()->count();

        if ($counts === []) {
            return ['total' => 0, 'generated_total' => $generatedTotal, 'semesters' => []];
        }

        $rows = [];
        $semesters = Semester::query()
            ->with('academicYear')
            ->findMany(array_keys($counts))
            ->sortByDesc(fn (Semester $semester): string => (string) $semester->start_date?->toDateString())
            ->values();

        foreach ($semesters as $semester) {
            $rows[] = [
                'semester_id' => $semester->getKey(),
                'term' => $semester->name->label(),
                'academic_year' => $semester->academicYear->name,
                'count' => $counts[$semester->getKey()],
            ];
        }

        return [
            'total' => array_sum($counts),
            'generated_total' => $generatedTotal,
            'semesters' => array_slice($rows, 0, 5),
        ];
    }

    /**
     * The teacher's schedules for today with their submission state.
     *
     * @return list<array{schedule_id: int, period_number: int, start_time: string, end_time: string, subject: string, classroom: string, submitted: bool}>
     */
    private function todayPeriods(User $user): array
    {
        $today = Date::today();
        $day = DayOfWeek::fromDate($today);
        $teacher = $user->teacher;

        if ($teacher === null || $day === null) {
            return [];
        }

        return array_values($this->teacherSchedulesOn($teacher, $day, $today, missingOnly: false)
            ->map(fn (Schedule $schedule): array => [
                'schedule_id' => $schedule->getKey(),
                'period_number' => $schedule->lessonPeriod->number,
                'start_time' => substr((string) $schedule->lessonPeriod->start_time, 0, 5),
                'end_time' => substr((string) $schedule->lessonPeriod->end_time, 0, 5),
                'subject' => $schedule->teachingAssignment->subject->name,
                'classroom' => $schedule->teachingAssignment->classroom->name,
                'submitted' => $schedule->attendanceSessions->isNotEmpty(),
            ])
            ->all());
    }

    /**
     * Sessions the teacher can still submit (the edit window runs until 23:59
     * the next day): yesterday's unsent schedules first, then today's.
     *
     * @return list<array{schedule_id: int, date: string, date_label: string, period_number: int, start_time: string, end_time: string, subject: string, classroom: string}>
     */
    private function pendingSubmissions(User $user): array
    {
        $teacher = $user->teacher;

        if ($teacher === null) {
            return [];
        }

        $rows = [];
        foreach ([Date::yesterday(), Date::today()] as $date) {
            $day = DayOfWeek::fromDate($date);

            if ($day === null) {
                continue;
            }

            foreach ($this->teacherSchedulesOn($teacher, $day, $date, missingOnly: true) as $schedule) {
                $rows[] = [
                    'schedule_id' => $schedule->getKey(),
                    'date' => $date->toDateString(),
                    'date_label' => $date->isToday()
                        ? 'Hari ini'
                        : 'Kemarin, '.$date->translatedFormat('d M Y'),
                    'period_number' => $schedule->lessonPeriod->number,
                    'start_time' => substr((string) $schedule->lessonPeriod->start_time, 0, 5),
                    'end_time' => substr((string) $schedule->lessonPeriod->end_time, 0, 5),
                    'subject' => $schedule->teachingAssignment->subject->name,
                    'classroom' => $schedule->teachingAssignment->classroom->name,
                ];
            }
        }

        return $rows;
    }

    /**
     * The latest published report card, from the student's own or the
     * guardian's perspective for the link target.
     *
     * @return ?array{term: string, academic_year: string, published_at: string, view_url: string}
     */
    private function latestReportCardFor(?Student $student, bool $forGuardian): ?array
    {
        if ($student === null) {
            return null;
        }

        $card = $student->reportCards()
            ->whereNotNull('published_at')
            ->with('semester.academicYear')
            ->get()
            ->sortByDesc(fn (ReportCard $reportCard): string => (string) $reportCard->semester?->start_date)
            ->first();

        $publishedAt = $card?->published_at;

        if ($card === null || $card->semester === null || $publishedAt === null) {
            return null;
        }

        return [
            'term' => $card->semester->name->label(),
            'academic_year' => $card->semester->academicYear->name,
            'published_at' => $publishedAt->translatedFormat('d M Y'),
            'view_url' => $forGuardian
                ? route('guardian.report-cards.index')
                : route('student.report-cards.index'),
        ];
    }

    /**
     * The child's most recent non-present days.
     *
     * @return list<array{date: string, status_label: string}>
     */
    private function recentAbsencesFor(?Student $child): array
    {
        if ($child === null) {
            return [];
        }

        return array_values(DailyAttendance::query()
            ->where('student_id', $child->getKey())
            ->where('status', '!=', AttendanceStatus::Present->value)
            ->orderByDesc('date')
            ->limit(5)
            ->get()
            ->map(fn (DailyAttendance $day): array => [
                'date' => $day->date->translatedFormat('d M Y'),
                'status_label' => $day->status->label(),
            ])
            ->all());
    }

    /**
     * The signed-in student's own record; an account without a student
     * profile gets an empty dashboard instead of a 404.
     */
    private function resolveStudent(User $user): ?Student
    {
        return Student::query()
            ->where('user_id', $user->getKey())
            ->first();
    }

    /**
     * A teacher's schedules on one weekday of the active semester, ordered by
     * period, optionally only those without an attendance session for the date.
     *
     * @return Collection<int, Schedule>
     */
    private function teacherSchedulesOn(Teacher $teacher, DayOfWeek $day, CarbonInterface $date, bool $missingOnly): Collection
    {
        return Schedule::query()
            ->where('day_of_week', $day->value)
            ->whereHas('teachingAssignment', fn ($query) => $query
                ->where('teacher_id', $teacher->getKey())
                ->whereHas('semester', fn ($query) => $query->where('is_active', true)))
            ->when($missingOnly, fn ($query) => $query->whereDoesntHave(
                'attendanceSessions',
                fn ($query) => $query->where('date', $date->toDateString()),
            ))
            ->with([
                'lessonPeriod:id,number,start_time,end_time',
                'teachingAssignment.subject:id,name',
                'teachingAssignment.classroom:id,name',
                'attendanceSessions' => fn ($query) => $query->where('date', $date->toDateString()),
            ])
            ->join('lesson_periods', 'lesson_periods.id', '=', 'schedules.lesson_period_id')
            ->orderBy('lesson_periods.number')
            ->select('schedules.*')
            ->get();
    }
}
