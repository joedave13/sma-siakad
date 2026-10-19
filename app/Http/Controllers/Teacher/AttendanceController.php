<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\DayOfWeek;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceStoreRequest;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Services\Attendance\AttendanceSheetPresenter;
use App\Services\Attendance\AttendanceSubmitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceSheetPresenter $presenter,
        private readonly AttendanceSubmitService $submit,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $date = $this->presenter->resolveDate($request->query('date'));
        $day = DayOfWeek::fromDate($date);
        $teacher = Teacher::query()->where('user_id', $user->getKey())->first();

        $slots = [];
        if ($teacher !== null && $day !== null) {
            $schedules = Schedule::query()
                ->where('day_of_week', $day->value)
                ->whereHas('teachingAssignment', fn ($query) => $query
                    ->where('teacher_id', $teacher->getKey())
                    ->whereHas('semester', fn ($semester) => $semester->where('is_active', true)))
                ->join('lesson_periods', 'lesson_periods.id', '=', 'schedules.lesson_period_id')
                ->orderBy('lesson_periods.number')
                ->select('schedules.*')
                ->get();

            $slots = $schedules
                ->map(fn (Schedule $schedule): array => $this->presenter->slot($schedule, $date, $user))
                ->values()
                ->all();
        }

        return Inertia::render('teacher/attendance/Index', [
            'date' => $date->toDateString(),
            'slots' => $slots,
            'profileMissing' => $teacher === null,
        ]);
    }

    public function show(Request $request, Schedule $schedule): Response
    {
        $user = $request->user();
        abort_unless($schedule->teachingAssignment->teacher_id === $user->teacher?->id, 403);

        $date = $this->presenter->resolveDate($request->query('date'));

        return Inertia::render('teacher/attendance/Sheet', [
            'date' => $date->toDateString(),
            ...$this->presenter->sheet($schedule, $date, $user),
        ]);
    }

    public function store(AttendanceStoreRequest $request, Schedule $schedule): RedirectResponse
    {
        $user = $request->user();
        abort_unless($schedule->teachingAssignment->teacher_id === $user->teacher?->id, 403);

        $validated = $request->validated();
        $date = Carbon::parse($validated['date']);

        $this->submit->submit($schedule, $date, $validated['entries'], $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Presensi berhasil disimpan.',
        ]);

        return redirect()->route('teacher.attendance.show', [
            'schedule' => $schedule,
            'date' => $validated['date'],
        ]);
    }
}
