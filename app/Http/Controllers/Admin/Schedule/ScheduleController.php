<?php

namespace App\Http\Controllers\Admin\Schedule;

use App\Enums\DayOfWeek;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Schedule\ScheduleStoreRequest;
use App\Models\Classroom;
use App\Models\LessonPeriod;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\TeachingAssignment;
use App\Services\AuditLogger;
use App\Services\MasterDataDeleteGuard;
use App\Services\Scheduling\ScheduleCollisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleCollisionService $collisions,
        private readonly MasterDataDeleteGuard $guard,
    ) {}

    public function index(Request $request): Response
    {
        $semester = Semester::query()
            ->where('is_active', true)
            ->with('academicYear:id,name')
            ->first();

        if ($semester === null) {
            return Inertia::render('admin/schedule/WeeklySchedule', [
                'semester' => null,
                'classrooms' => [],
                'assignments' => [],
                'schedules' => [],
                'lessonPeriods' => [],
                'days' => $this->dayPayload(),
                'selectedClassroom' => null,
            ]);
        }

        $classrooms = array_values(Classroom::query()
            ->where('academic_year_id', $semester->academic_year_id)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Classroom $classroom): array => [
                'id' => $classroom->id,
                'name' => $classroom->name,
            ])
            ->all());

        return Inertia::render('admin/schedule/WeeklySchedule', [
            'semester' => [
                'id' => $semester->getKey(),
                'name' => $semester->name->label(),
                'academic_year' => $semester->academicYear->name,
            ],
            'classrooms' => $classrooms,
            'assignments' => $this->assignmentPayload($semester),
            'schedules' => Schedule::query()
                ->whereHas('teachingAssignment', fn ($query) => $query
                    ->where('semester_id', $semester->getKey()))
                ->get(['id', 'teaching_assignment_id', 'day_of_week', 'lesson_period_id'])
                ->map(fn (Schedule $schedule): array => [
                    'id' => $schedule->id,
                    'teaching_assignment_id' => $schedule->teaching_assignment_id,
                    'day_of_week' => $schedule->day_of_week->value,
                    'lesson_period_id' => $schedule->lesson_period_id,
                ])
                ->values()
                ->all(),
            'lessonPeriods' => LessonPeriod::query()
                ->ordered()
                ->get(['id', 'number', 'start_time', 'end_time'])
                ->map(fn (LessonPeriod $period): array => [
                    'id' => $period->id,
                    'number' => $period->number,
                    'start_time' => substr((string) $period->start_time, 0, 5),
                    'end_time' => substr((string) $period->end_time, 0, 5),
                ])
                ->values()
                ->all(),
            'days' => $this->dayPayload(),
            'selectedClassroom' => $this->selectedClassroom($request, $classrooms),
        ]);
    }

    public function store(ScheduleStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $assignment = TeachingAssignment::query()
            ->with(['classroom:id,name', 'subject:id,name', 'teacher:id,full_name'])
            ->findOrFail((int) $validated['teaching_assignment_id']);
        $day = DayOfWeek::from((string) $validated['day_of_week']);
        $period = LessonPeriod::query()->findOrFail((int) $validated['lesson_period_id']);

        $conflict = $this->collisions->duplicateScheduleMessage($assignment, $day, $period)
            ?? $this->collisions->teacherConflictMessage($assignment->teacher, $day, $period)
            ?? $this->collisions->classroomConflictMessage($assignment->classroom, $day, $period);

        if ($conflict !== null) {
            return back()->withErrors(['teaching_assignment_id' => $conflict]);
        }

        $schedule = DB::transaction(function () use ($assignment, $day, $period): Schedule {
            $existing = Schedule::withTrashed()
                ->where('teaching_assignment_id', $assignment->getKey())
                ->where('day_of_week', $day->value)
                ->where('lesson_period_id', $period->getKey())
                ->first();

            if ($existing !== null) {
                $existing->restore();

                return $existing;
            }

            return Schedule::query()->create([
                'teaching_assignment_id' => $assignment->getKey(),
                'day_of_week' => $day->value,
                'lesson_period_id' => $period->getKey(),
            ]);
        });

        AuditLogger::record('schedule.create', $schedule, [], $schedule->only([
            'teaching_assignment_id', 'day_of_week', 'lesson_period_id',
        ]));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$assignment->subject?->name} dijadwalkan di kelas {$assignment->classroom?->name} pada {$day->label()} jam ke-{$period->number}.",
        ]);

        return redirect()->route('admin.schedule.weekly.index', [
            'classroom' => $assignment->classroom_id,
        ]);
    }

    public function destroy(Schedule $schedule): RedirectResponse
    {
        $this->guard->blockIfInUse($schedule);

        $schedule->load(['teachingAssignment.classroom:id,name', 'teachingAssignment.subject:id,name', 'lessonPeriod:number']);
        $assignment = $schedule->teachingAssignment;
        $old = $schedule->only(['teaching_assignment_id', 'day_of_week', 'lesson_period_id']);

        $schedule->delete();

        AuditLogger::record('schedule.delete', $schedule, $old, []);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Jadwal {$assignment?->subject?->name} di kelas {$assignment?->classroom?->name} pada {$schedule->day_of_week->label()} jam ke-{$schedule->lessonPeriod?->number} dihapus.",
        ]);

        return redirect()->route('admin.schedule.weekly.index', [
            'classroom' => $assignment?->classroom_id,
        ]);
    }

    /**
     * The semester's assignments with resolved names, used both as the
     * per-classroom lists and as the slot picker options on the grid.
     *
     * @return list<array{id: int, classroom_id: int, classroom_name: string, subject_name: string, teacher_name: string}>
     */
    private function assignmentPayload(Semester $semester): array
    {
        return array_values(TeachingAssignment::query()
            ->where('semester_id', $semester->getKey())
            ->with(['classroom:id,name', 'subject:id,name', 'teacher:id,full_name'])
            ->orderBy('id')
            ->get()
            ->map(fn (TeachingAssignment $assignment): array => [
                'id' => $assignment->id,
                'classroom_id' => $assignment->classroom_id,
                'classroom_name' => $assignment->classroom->name,
                'subject_name' => $assignment->subject->name,
                'teacher_name' => $assignment->teacher->full_name,
            ])
            ->all());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function dayPayload(): array
    {
        return array_map(
            fn (DayOfWeek $day): array => ['value' => $day->value, 'label' => $day->label()],
            DayOfWeek::cases(),
        );
    }

    /**
     * @param  list<array{id: int, name: string}>  $classrooms
     */
    private function selectedClassroom(Request $request, array $classrooms): ?int
    {
        $requested = (int) $request->query('classroom', '0');

        foreach ($classrooms as $classroom) {
            if ($classroom['id'] === $requested) {
                return $requested;
            }
        }

        return $classrooms[0]['id'] ?? null;
    }
}
