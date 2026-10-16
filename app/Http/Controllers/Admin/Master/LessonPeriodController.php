<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Master\LessonPeriodRequest;
use App\Models\LessonPeriod;
use App\Services\AuditLogger;
use App\Services\MasterDataDeleteGuard;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LessonPeriodController extends Controller
{
    /**
     * @var list<string>
     */
    private const AUDIT_FIELDS = ['number', 'start_time', 'end_time'];

    public function __construct(private readonly MasterDataDeleteGuard $guard) {}

    public function index(): Response
    {
        $periods = LessonPeriod::query()->ordered()->get();

        return Inertia::render('admin/master/LessonPeriods', [
            'periods' => $periods,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/master/LessonPeriodForm', [
            'period' => null,
        ]);
    }

    public function store(LessonPeriodRequest $request): RedirectResponse
    {
        $period = LessonPeriod::query()->create($request->validated());

        AuditLogger::record('lesson_period.create', $period, [], $period->only(self::AUDIT_FIELDS));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Jam pelajaran berhasil ditambahkan.',
        ]);

        return redirect()->route('admin.master.lesson-periods.index');
    }

    public function edit(LessonPeriod $lessonPeriod): Response
    {
        return Inertia::render('admin/master/LessonPeriodForm', [
            'period' => $lessonPeriod,
        ]);
    }

    public function update(LessonPeriodRequest $request, LessonPeriod $lessonPeriod): RedirectResponse
    {
        $old = $lessonPeriod->only(self::AUDIT_FIELDS);

        $lessonPeriod->update($request->validated());

        AuditLogger::record('lesson_period.update', $lessonPeriod, $old, $lessonPeriod->only(self::AUDIT_FIELDS));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Jam pelajaran berhasil diperbarui.',
        ]);

        return redirect()->route('admin.master.lesson-periods.index');
    }

    public function destroy(LessonPeriod $lessonPeriod): RedirectResponse
    {
        $this->guard->blockIfInUse($lessonPeriod);

        $old = $lessonPeriod->only(self::AUDIT_FIELDS);
        $lessonPeriod->delete();

        AuditLogger::record('lesson_period.delete', $lessonPeriod, $old, []);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Jam pelajaran berhasil dihapus.',
        ]);

        return redirect()->route('admin.master.lesson-periods.index');
    }
}
