<?php

namespace App\Http\Controllers;

use App\Enums\DayOfWeek;
use App\Enums\Role;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'missingSessions' => $user->role === Role::Admin
                ? $this->missingSessions()
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
}
