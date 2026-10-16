<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Master\AcademicYearRequest;
use App\Models\AcademicYear;
use App\Services\AuditLogger;
use App\Services\MasterDataDeleteGuard;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    /**
     * @var list<string>
     */
    private const AUDIT_FIELDS = ['name', 'start_date', 'end_date', 'is_active'];

    public function __construct(private readonly MasterDataDeleteGuard $guard) {}

    public function index(): Response
    {
        $years = AcademicYear::query()
            ->orderByDesc('is_active')
            ->orderByDesc('name')
            ->get();

        return Inertia::render('admin/master/AcademicYears', [
            'years' => $years,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/master/AcademicYearForm', [
            'year' => null,
        ]);
    }

    public function store(AcademicYearRequest $request): RedirectResponse
    {
        $year = AcademicYear::query()->create($request->validated());

        AuditLogger::record('academic_year.create', $year, [], $year->only(self::AUDIT_FIELDS));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tahun ajaran berhasil ditambahkan.',
        ]);

        return redirect()->route('admin.master.academic-years.index');
    }

    public function edit(AcademicYear $academicYear): Response
    {
        return Inertia::render('admin/master/AcademicYearForm', [
            'year' => $academicYear,
        ]);
    }

    public function update(AcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $old = $academicYear->only(self::AUDIT_FIELDS);

        $academicYear->update($request->validated());

        AuditLogger::record('academic_year.update', $academicYear, $old, $academicYear->only(self::AUDIT_FIELDS));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tahun ajaran berhasil diperbarui.',
        ]);

        return redirect()->route('admin.master.academic-years.index');
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        $this->guard->blockIfInUse($academicYear);

        $old = $academicYear->only(self::AUDIT_FIELDS);
        $academicYear->delete();

        AuditLogger::record('academic_year.delete', $academicYear, $old, []);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tahun ajaran berhasil dihapus.',
        ]);

        return redirect()->route('admin.master.academic-years.index');
    }
}
