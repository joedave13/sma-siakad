<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Jobs\RecomputeDailyAttendance;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ChildAbsent;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class AttendanceSubmitService
{
    /**
     * Submit (or re-submit) attendance for one schedule slot and date.
     *
     * One bulk upsert per session in a single transaction against the unique
     * (schedule_id, date) and (session_id, student_id) indexes. Recompute and
     * guardian notifications are queued so the request never blocks.
     *
     * @param  array<int, array{student_id?: int, status?: AttendanceStatus|string, note?: string|null}>  $entries
     *
     * @throws ValidationException
     */
    public function submit(Schedule $schedule, Carbon $date, array $entries, User $submitter): AttendanceSession
    {
        $dateString = $date->toDateString();

        $session = AttendanceSession::query()
            ->where('schedule_id', $schedule->getKey())
            ->where('date', $dateString)
            ->first();

        $this->authorizeSubmit($session, $schedule, $submitter);

        $isOverride = $session !== null
            && $submitter->role === Role::Admin
            && $session->submitted_by !== $submitter->getKey();

        $roster = $this->roster($schedule)->keyBy('id');
        $rows = $this->normalizeEntries($entries, $roster);

        $oldValues = $isOverride
            ? $this->existingStatuses($session, array_keys($rows))
            : [];

        $affectedStudentIds = array_keys($rows);

        $session = DB::transaction(function () use ($schedule, $dateString, $submitter, $rows, $isOverride): AttendanceSession {
            AttendanceSession::query()->upsert(
                [[
                    'schedule_id' => $schedule->getKey(),
                    'date' => $dateString,
                    'submitted_by' => $submitter->getKey(),
                    'submitted_at' => now(),
                ]],
                ['schedule_id', 'date'],
                ['submitted_by', 'submitted_at'],
            );

            $session = AttendanceSession::query()
                ->where('schedule_id', $schedule->getKey())
                ->where('date', $dateString)
                ->firstOrFail();

            if ($rows !== []) {
                Attendance::query()->upsert(
                    array_map(fn (array $row): array => [
                        'attendance_session_id' => $session->getKey(),
                        'student_id' => $row['student_id'],
                        'status' => $row['status']->value,
                        'note' => $row['note'],
                        'is_overridden' => $isOverride,
                    ], array_values($rows)),
                    ['attendance_session_id', 'student_id'],
                    ['status', 'note', 'is_overridden'],
                );
            }

            return $session;
        });

        foreach ($affectedStudentIds as $studentId) {
            RecomputeDailyAttendance::dispatch($studentId, $dateString);
        }

        $absentStudents = $roster
            ->filter(fn (Student $student, int $id): bool => isset($rows[$id]) && $rows[$id]['status'] === AttendanceStatus::Absent);

        foreach ($absentStudents as $student) {
            $guardians = $student->guardians;
            if ($guardians->isNotEmpty()) {
                Notification::send($guardians, new ChildAbsent($student, $date));
            }
        }

        if ($isOverride) {
            AuditLogger::record(
                'attendance.override',
                $session,
                ['statuses' => $oldValues],
                ['statuses' => $this->rowStatuses($rows)],
            );
        }

        return $session;
    }

    /**
     * Whether the given user may still edit this session (teacher window or admin).
     */
    public function canEdit(?AttendanceSession $session, Schedule $schedule, User $user): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if ($session === null) {
            return $schedule->teachingAssignment->teacher_id === $user->teacher?->id;
        }

        return $session->submitted_by === $user->getKey()
            && now()->lessThanOrEqualTo($session->teacherEditDeadline());
    }

    /**
     * Active students enrolled in the assignment's classroom for the assignment's year.
     *
     * @return Collection<int, Student>
     */
    public function roster(Schedule $schedule): Collection
    {
        $assignment = $schedule->teachingAssignment()->firstOrFail();

        return Student::query()
            ->where('status', StudentStatus::Active->value)
            ->whereHas('enrollments', fn ($query) => $query
                ->where('classroom_id', $assignment->classroom_id)
                ->where('academic_year_id', $assignment->semester->academic_year_id))
            ->get();
    }

    /**
     * Validate and normalize entries into rows keyed by student id (roster students only).
     *
     * @param  array<int, array{student_id?: int, status?: AttendanceStatus|string, note?: string|null}>  $entries
     * @param  Collection<int, Student>  $roster
     * @return array<int, array{student_id: int, status: AttendanceStatus, note: ?string}>
     */
    private function normalizeEntries(array $entries, Collection $roster): array
    {
        $rows = [];

        foreach ($entries as $entry) {
            $studentId = (int) ($entry['student_id'] ?? 0);

            if (! $roster->has($studentId)) {
                continue;
            }

            $rawStatus = $entry['status'] ?? null;

            if ($rawStatus instanceof AttendanceStatus) {
                $status = $rawStatus;
            } elseif (is_string($rawStatus)) {
                $status = AttendanceStatus::tryFrom($rawStatus);
            } else {
                $status = null;
            }

            if ($status === null) {
                throw ValidationException::withMessages([
                    'status' => 'Status kehadiran tidak dikenal.',
                ]);
            }

            $rows[$studentId] = [
                'student_id' => $studentId,
                'status' => $status,
                'note' => isset($entry['note']) ? (string) $entry['note'] : null,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, int>  $studentIds
     * @return array<int, AttendanceStatus>
     */
    private function existingStatuses(AttendanceSession $session, array $studentIds): array
    {
        return $session->attendances()
            ->whereIn('student_id', $studentIds)
            ->pluck('status', 'student_id')
            ->all();
    }

    /**
     * @param  array<int, array{student_id: int, status: AttendanceStatus, note: ?string}>  $rows
     * @return array<int, string>
     */
    private function rowStatuses(array $rows): array
    {
        $statuses = [];
        foreach ($rows as $row) {
            $statuses[$row['student_id']] = $row['status']->value;
        }

        return $statuses;
    }

    /**
     * @throws ValidationException
     */
    private function authorizeSubmit(?AttendanceSession $session, Schedule $schedule, User $submitter): void
    {
        if ($this->canEdit($session, $schedule, $submitter)) {
            return;
        }

        if ($session !== null && $submitter->role !== Role::Admin) {
            throw ValidationException::withMessages([
                'attendance' => 'Presensi hanya dapat diubah oleh penyusunnya hingga pukul 23:59 sehari setelah tanggal presensi. Setelah itu hanya admin yang dapat mengubah.',
            ]);
        }

        throw ValidationException::withMessages([
            'attendance' => 'Anda tidak memiliki akses untuk mengubah presensi jam pelajaran ini.',
        ]);
    }
}
