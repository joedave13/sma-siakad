<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Builds the Inertia payloads shared by the teacher and admin attendance sheets.
 */
class AttendanceSheetPresenter
{
    public function __construct(private readonly AttendanceSubmitService $submit) {}

    /**
     * Resolve a `?date=Y-m-d` query parameter, falling back to today when
     * absent or malformed.
     */
    public function resolveDate(?string $raw): Carbon
    {
        if ($raw === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) !== 1) {
            return Carbon::today();
        }

        $date = Carbon::createFromFormat('Y-m-d', $raw);

        if ($date->format('Y-m-d') !== $raw) {
            return Carbon::today();
        }

        return $date;
    }

    /**
     * One slot row for the teacher's daily list.
     *
     * @return array{schedule_id: int, period_number: int, start_time: string, end_time: string, subject: string, classroom: string, state: string, submitted_at: string|null}
     */
    public function slot(Schedule $schedule, CarbonInterface $date, User $teacher): array
    {
        $schedule->load(['lessonPeriod', 'teachingAssignment.subject:id,name', 'teachingAssignment.classroom:id,name']);

        $session = $schedule->attendanceSessions()
            ->where('date', $date->toDateString())
            ->first(['id', 'date', 'submitted_at', 'submitted_by']);

        $state = 'none';

        if ($session !== null) {
            $state = $this->submit->canEdit($session, $schedule, $teacher) ? 'submitted' : 'locked';
        }

        $period = $schedule->lessonPeriod;
        $assignment = $schedule->teachingAssignment;

        return [
            'schedule_id' => $schedule->id,
            'period_number' => $period->number,
            'start_time' => substr((string) $period->start_time, 0, 5),
            'end_time' => substr((string) $period->end_time, 0, 5),
            'subject' => $assignment->subject->name,
            'classroom' => $assignment->classroom->name,
            'state' => $state,
            'submitted_at' => $session?->submitted_at?->toIso8601String(),
        ];
    }

    /**
     * The full sheet payload for one schedule slot and date.
     *
     * @return array{slot: array{schedule_id: int, period_number: int, start_time: string, end_time: string, subject: string, classroom: string}, roster: list<array{id: int, nisn: string, full_name: string}>, session: array{submitted: bool, submitted_at: string|null, submitted_by: string|null, editable: bool, edit_deadline: string|null, lock_message: string|null, entries: array<int, string>}, status_options: list<array{value: string, label: string}>}
     */
    public function sheet(Schedule $schedule, CarbonInterface $date, User $viewer): array
    {
        $schedule->load(['lessonPeriod', 'teachingAssignment.subject:id,name', 'teachingAssignment.classroom:id,name']);

        $session = AttendanceSession::query()
            ->where('schedule_id', $schedule->id)
            ->where('date', $date->toDateString())
            ->with('submittedBy:id,name')
            ->first();

        $editable = $this->submit->canEdit($session, $schedule, $viewer);
        $period = $schedule->lessonPeriod;
        $assignment = $schedule->teachingAssignment;

        $entries = [];
        if ($session !== null) {
            foreach ($session->attendances as $attendance) {
                $entries[(int) $attendance->student_id] = $attendance->status->value;
            }
        }

        $roster = $this->submit->roster($schedule)
            ->map(fn (Student $student): array => [
                'id' => $student->id,
                'nisn' => $student->nisn,
                'full_name' => $student->full_name,
            ])
            ->values()
            ->all();

        return [
            'slot' => [
                'schedule_id' => $schedule->id,
                'period_number' => $period->number,
                'start_time' => substr((string) $period->start_time, 0, 5),
                'end_time' => substr((string) $period->end_time, 0, 5),
                'subject' => $assignment->subject->name,
                'classroom' => $assignment->classroom->name,
            ],
            'roster' => array_values($roster),
            'session' => [
                'submitted' => $session !== null,
                'submitted_at' => $session?->submitted_at?->toIso8601String(),
                'submitted_by' => $session?->submittedBy->name ?? ($session !== null ? 'Pengguna terhapus' : null),
                'editable' => $editable,
                'edit_deadline' => $session?->teacherEditDeadline()->toIso8601String(),
                'lock_message' => $session !== null && ! $editable
                    ? 'Presensi hanya dapat diubah oleh penyusunnya hingga pukul 23:59 sehari setelah tanggal presensi. Setelah itu hanya admin yang dapat mengubah.'
                    : null,
                'entries' => $entries,
            ],
            'status_options' => array_map(
                fn (AttendanceStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                AttendanceStatus::cases(),
            ),
        ];
    }
}
