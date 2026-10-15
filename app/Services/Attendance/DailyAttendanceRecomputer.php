<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\DailyAttendance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DailyAttendanceRecomputer
{
    /**
     * Recompute the daily attendance row for one student and date.
     *
     * The daily status is the status of the student's first recorded period of the
     * day (earliest lesson period). Terlambat becomes Hadir with the is_late flag.
     */
    public function recompute(int $studentId, Carbon $date): void
    {
        $dateString = $date->toDateString();

        $first = DB::table('attendances')
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendances.attendance_session_id')
            ->join('schedules', 'schedules.id', '=', 'attendance_sessions.schedule_id')
            ->join('lesson_periods', 'lesson_periods.id', '=', 'schedules.lesson_period_id')
            ->where('attendances.student_id', $studentId)
            ->where('attendance_sessions.date', $dateString)
            ->orderBy('lesson_periods.number')
            ->orderBy('attendances.id')
            ->select('attendances.status', 'attendances.is_overridden')
            ->first();

        if ($first === null) {
            DailyAttendance::query()
                ->where('student_id', $studentId)
                ->where('date', $dateString)
                ->delete();

            return;
        }

        $status = AttendanceStatus::from($first->status);

        DailyAttendance::query()->updateOrCreate(
            ['student_id' => $studentId, 'date' => $dateString],
            [
                'status' => $status->dailyValue()->value,
                'is_late' => $status === AttendanceStatus::Late,
                'is_overridden' => (bool) $first->is_overridden,
                'computed_at' => now(),
            ],
        );
    }
}
