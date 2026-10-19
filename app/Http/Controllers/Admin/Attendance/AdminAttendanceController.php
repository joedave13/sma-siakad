<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceStoreRequest;
use App\Models\Schedule;
use App\Services\Attendance\AttendanceSheetPresenter;
use App\Services\Attendance\AttendanceSubmitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AdminAttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceSheetPresenter $presenter,
        private readonly AttendanceSubmitService $submit,
    ) {}

    public function show(Request $request, Schedule $schedule): Response
    {
        $date = $this->presenter->resolveDate($request->query('date'));

        return Inertia::render('admin/attendance/Sheet', [
            'date' => $date->toDateString(),
            ...$this->presenter->sheet($schedule, $date, $request->user()),
        ]);
    }

    public function store(AttendanceStoreRequest $request, Schedule $schedule): RedirectResponse
    {
        $validated = $request->validated();
        $date = Carbon::parse($validated['date']);

        $this->submit->submit($schedule, $date, $validated['entries'], $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Presensi berhasil disimpan.',
        ]);

        return redirect()->route('admin.attendance.show', [
            'schedule' => $schedule,
            'date' => $validated['date'],
        ]);
    }
}
