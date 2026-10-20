<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Enums\StudentStatus;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\ClassEnrollment;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Student;
use App\Notifications\ChildAbsent;
use App\Services\Attendance\DailyAttendanceRecomputer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class DemoAttendanceSeeder extends Seeder
{
    private const int WINDOW_DAYS = 10;

    /**
     * Seed ten school days of submitted attendance, recompute daily rows, and
     * notify guardians of every absent day in the window.
     */
    public function run(): void
    {
        $semester = Semester::query()->where('is_active', true)->firstOrFail();
        $window = $this->schoolWindow();
        $intents = $this->intents($semester, $window);
        $excluded = $this->excludedKeys($intents);
        $rosters = $this->rosters($semester);
        $ordinals = DemoData::ordinalsByNisn();
        $ordinalByStudentId = [];

        foreach ($rosters as $students) {
            foreach ($students as $student) {
                if (isset($ordinals[$student->nisn])) {
                    $ordinalByStudentId[$student->getKey()] = $ordinals[$student->nisn];
                }
            }
        }

        $active = [];
        foreach ($intents as $intent) {
            $key = $intent['schedule']->getKey().'|'.$intent['date']->toDateString();

            if (! isset($excluded[$key])) {
                $active[] = $intent;
            }
        }

        $sessions = $this->storeSessions($active);
        $this->storeAttendances($active, $sessions, $rosters, $ordinalByStudentId);
    }

    /**
     * The last ten school days, oldest first, ending today when it is one.
     *
     * @return list<Carbon>
     */
    private function schoolWindow(): array
    {
        $window = [];
        $cursor = Carbon::today();

        while (count($window) < self::WINDOW_DAYS) {
            if (DayOfWeek::fromDate($cursor) !== null) {
                $window[] = $cursor->copy();
            }

            $cursor = $cursor->copy()->subDay();
        }

        return array_reverse($window);
    }

    /**
     * One intent per schedule and window date it is taught on, each carrying
     * the day index used for deterministic statuses.
     *
     * @param  list<Carbon>  $window
     * @return list<array{schedule: Schedule, date: Carbon, index: int}>
     */
    private function intents(Semester $semester, array $window): array
    {
        $schedules = Schedule::query()
            ->whereHas('teachingAssignment', fn ($query) => $query->where('semester_id', $semester->getKey()))
            ->with(['teachingAssignment.teacher', 'teachingAssignment.classroom', 'lessonPeriod'])
            ->get();

        $intents = [];

        foreach ($schedules as $schedule) {
            foreach ($window as $index => $date) {
                if (DayOfWeek::fromDate($date) !== $schedule->day_of_week) {
                    continue;
                }

                $intents[] = ['schedule' => $schedule, 'date' => $date, 'index' => $index];
            }
        }

        return $intents;
    }

    /**
     * The schedule|date keys left unsubmitted so today and yesterday keep
     * pending submissions on the admin and teacher dashboards.
     *
     * @param  list<array{schedule: Schedule, date: Carbon, index: int}>  $intents
     * @return array<string, true>
     */
    private function excludedKeys(array $intents): array
    {
        $excluded = [];
        $today = DayOfWeek::fromDate(Carbon::today());

        if ($today !== null) {
            foreach ($this->firstIntentPerClassroom($intents, Carbon::today()->toDateString(), 2) as $intent) {
                $excluded[$intent['schedule']->getKey().'|'.$intent['date']->toDateString()] = true;
            }
        }

        $yesterday = DayOfWeek::fromDate(Carbon::yesterday());

        if ($yesterday !== null) {
            foreach ($this->firstIntentPerClassroom($intents, Carbon::yesterday()->toDateString(), 1) as $intent) {
                $excluded[$intent['schedule']->getKey().'|'.$intent['date']->toDateString()] = true;
            }
        }

        return $excluded;
    }

    /**
     * The earliest period of up to $limit different classrooms on one date.
     *
     * @param  list<array{schedule: Schedule, date: Carbon, index: int}>  $intents
     * @return list<array{schedule: Schedule, date: Carbon, index: int}>
     */
    private function firstIntentPerClassroom(array $intents, string $dateString, int $limit): array
    {
        $matched = array_values(array_filter(
            $intents,
            fn (array $intent): bool => $intent['date']->toDateString() === $dateString,
        ));

        usort($matched, function (array $first, array $second): int {
            return strcmp(
                (string) $first['schedule']->teachingAssignment->classroom?->name,
                (string) $second['schedule']->teachingAssignment->classroom?->name,
            ) ?: $first['schedule']->lessonPeriod->number <=> $second['schedule']->lessonPeriod->number;
        });

        $picked = [];
        $seen = [];

        foreach ($matched as $intent) {
            $classroomId = $intent['schedule']->teachingAssignment->classroom_id;

            if (isset($seen[$classroomId])) {
                continue;
            }

            $seen[$classroomId] = true;
            $picked[] = $intent;

            if (count($picked) >= $limit) {
                break;
            }
        }

        return $picked;
    }

    /**
     * The enrolled roster of every classroom in the active year.
     *
     * @return array<int, list<Student>>
     */
    private function rosters(Semester $semester): array
    {
        $rosters = [];
        $enrollments = ClassEnrollment::query()
            ->where('academic_year_id', $semester->academic_year_id)
            ->whereHas('student', fn ($query) => $query->where('status', StudentStatus::Active->value))
            ->with('student')
            ->get();

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;

            if ($student === null) {
                continue;
            }

            $rosters[$enrollment->classroom_id][] = $student;
        }

        return $rosters;
    }

    /**
     * Upsert one submitted session per intent and return them keyed by
     * schedule|date.
     *
     * @param  list<array{schedule: Schedule, date: Carbon, index: int}>  $intents
     * @return Collection<string, AttendanceSession>
     */
    private function storeSessions(array $intents): Collection
    {
        $rows = [];

        foreach ($intents as $intent) {
            $rows[] = [
                'schedule_id' => $intent['schedule']->getKey(),
                'date' => $intent['date']->toDateString(),
                'submitted_by' => $intent['schedule']->teachingAssignment->teacher?->user_id,
                'submitted_at' => $this->submittedAt($intent),
            ];
        }

        if ($rows === []) {
            return new Collection;
        }

        AttendanceSession::query()->upsert($rows, ['schedule_id', 'date'], ['submitted_by', 'submitted_at']);

        $scheduleIds = array_unique(array_column($rows, 'schedule_id'));
        $dates = array_unique(array_column($rows, 'date'));

        return AttendanceSession::query()
            ->whereIn('schedule_id', $scheduleIds)
            ->whereIn('date', $dates)
            ->get()
            ->keyBy(fn (AttendanceSession $session): string => $session->schedule_id.'|'.$session->date->toDateString());
    }

    /**
     * The session submission timestamp: right after the period ends, capped
     * at now so a future period never lands in the future.
     *
     * @param  array{schedule: Schedule, date: Carbon, index: int}  $intent
     */
    private function submittedAt(array $intent): Carbon
    {
        $endTime = substr((string) $intent['schedule']->lessonPeriod->end_time, 0, 5);
        $submittedAt = Carbon::parse($intent['date']->toDateString().' '.$endTime);

        if ($submittedAt->isFuture()) {
            $submittedAt = Carbon::now();
        }

        return $submittedAt;
    }

    /**
     * Upsert every attendance row, recompute daily attendance, and send one
     * guardian notification per absent student-day.
     *
     * @param  list<array{schedule: Schedule, date: Carbon, index: int}>  $intents
     * @param  Collection<string, AttendanceSession>  $sessions
     * @param  array<int, list<Student>>  $rosters
     * @param  array<int, int>  $ordinalByStudentId
     */
    private function storeAttendances(array $intents, Collection $sessions, array $rosters, array $ordinalByStudentId): void
    {
        $rows = [];
        $recomputeKeys = [];
        $absentDays = [];

        foreach ($intents as $intent) {
            $dateString = $intent['date']->toDateString();
            $session = $sessions->get($intent['schedule']->getKey().'|'.$dateString);

            if ($session === null) {
                throw new RuntimeException('Sesi presensi tidak ditemukan untuk jadwal demo.');
            }

            $students = $rosters[$intent['schedule']->teachingAssignment->classroom_id] ?? [];

            foreach ($students as $student) {
                $ordinal = $ordinalByStudentId[$student->getKey()] ?? null;

                if ($ordinal === null) {
                    continue;
                }

                $status = DemoData::statusFor($ordinal, $intent['index']);

                $rows[] = [
                    'attendance_session_id' => $session->getKey(),
                    'student_id' => $student->getKey(),
                    'status' => $status->value,
                    'note' => DemoData::attendanceNoteFor($status),
                    'is_overridden' => false,
                ];

                $key = $student->getKey().'|'.$dateString;
                $recomputeKeys[$key] = ['student_id' => $student->getKey(), 'date' => $intent['date']];

                if ($status === AttendanceStatus::Absent) {
                    $absentDays[$key] = ['student_id' => $student->getKey(), 'date' => $dateString];
                }
            }
        }

        if ($rows !== []) {
            Attendance::query()->upsert($rows, ['attendance_session_id', 'student_id'], ['status', 'note', 'is_overridden']);
        }

        $recomputer = app(DailyAttendanceRecomputer::class);

        foreach ($recomputeKeys as $pair) {
            $recomputer->recompute((int) $pair['student_id'], $pair['date']);
        }

        $this->notifyGuardians($absentDays);
    }

    /**
     * Send the queued-absent notification immediately for every absent day.
     *
     * @param  array<string, array{student_id: int, date: string}>  $absentDays
     */
    private function notifyGuardians(array $absentDays): void
    {
        $byStudent = [];

        foreach ($absentDays as $absent) {
            $byStudent[$absent['student_id']][] = $absent['date'];
        }

        if ($byStudent === []) {
            return;
        }

        $students = Student::query()->with('guardians')->whereIn('id', array_keys($byStudent))->get();

        foreach ($students as $student) {
            if ($student->guardians->isEmpty()) {
                continue;
            }

            foreach ($byStudent[$student->getKey()] as $dateString) {
                Notification::sendNow($student->guardians, new ChildAbsent($student, Carbon::parse($dateString)));
            }
        }
    }
}
