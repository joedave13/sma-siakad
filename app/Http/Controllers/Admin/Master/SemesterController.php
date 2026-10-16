<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Master\SemesterRequest;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Services\AuditLogger;
use App\Services\MasterDataDeleteGuard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SemesterController extends Controller
{
    /**
     * @var list<string>
     */
    private const AUDIT_FIELDS = ['academic_year_id', 'name', 'start_date', 'end_date', 'is_active'];

    public function __construct(private readonly MasterDataDeleteGuard $guard) {}

    public function index(): Response
    {
        $semesters = Semester::query()
            ->with('academicYear:id,name')
            ->orderByDesc('academic_year_id')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/master/Semesters', [
            'semesters' => $semesters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/master/SemesterForm', [
            'academicYears' => $this->academicYears(),
            'semester' => null,
        ]);
    }

    public function store(SemesterRequest $request): RedirectResponse
    {
        $semester = Semester::query()->create($request->validated());

        AuditLogger::record('semester.create', $semester, [], $semester->only(self::AUDIT_FIELDS));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Semester berhasil ditambahkan.',
        ]);

        return redirect()->route('admin.master.semesters.index');
    }

    public function edit(Semester $semester): Response
    {
        return Inertia::render('admin/master/SemesterForm', [
            'academicYears' => $this->academicYears(),
            'semester' => $semester,
        ]);
    }

    public function update(SemesterRequest $request, Semester $semester): RedirectResponse
    {
        $old = $semester->only(self::AUDIT_FIELDS);

        $semester->update($request->validated());

        AuditLogger::record('semester.update', $semester, $old, $semester->only(self::AUDIT_FIELDS));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Semester berhasil diperbarui.',
        ]);

        return redirect()->route('admin.master.semesters.index');
    }

    public function destroy(Semester $semester): RedirectResponse
    {
        $this->guard->blockIfInUse($semester);

        $old = $semester->only(self::AUDIT_FIELDS);
        $semester->delete();

        AuditLogger::record('semester.delete', $semester, $old, []);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Semester berhasil dihapus.',
        ]);

        return redirect()->route('admin.master.semesters.index');
    }

    /**
     * @return Collection<int, AcademicYear>
     */
    private function academicYears(): Collection
    {
        return AcademicYear::query()
            ->orderByDesc('name')
            ->get(['id', 'name']);
    }
}
