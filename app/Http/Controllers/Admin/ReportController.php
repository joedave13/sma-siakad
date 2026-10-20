<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Services\ReportCard\PublishService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(private readonly PublishService $publishService) {}

    public function index(): Response
    {
        return Inertia::render('admin/reports/Index', [
            'semesters' => $this->semesterRows(),
        ]);
    }

    public function show(Semester $semester): Response
    {
        return Inertia::render('admin/reports/Show', [
            'semester' => $this->semesterPayload($semester),
            'generation' => $this->publishService->status($semester),
        ]);
    }

    public function lock(Request $request, Semester $semester): RedirectResponse
    {
        $this->publishService->lock($semester, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nilai semester berhasil dikunci.']);

        return redirect()->route('admin.reports.index');
    }

    public function unlock(Request $request, Semester $semester): RedirectResponse
    {
        $validated = $request->validate(
            ['reason' => ['required', 'string', 'max:500']],
            [
                'reason.required' => 'Alasan pembukaan kunci wajib diisi.',
                'reason.max' => 'Alasan pembukaan kunci maksimal 500 karakter.',
            ],
        );

        $this->publishService->unlock($semester, $validated['reason'], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kunci nilai semester berhasil dibuka.']);

        return redirect()->route('admin.reports.index');
    }

    public function generate(Request $request, Semester $semester): RedirectResponse
    {
        $result = $this->publishService->generateBulk($semester);
        $skipped = count($result['skipped']);

        if ($result['queued'] === 0) {
            Inertia::flash('report_card', [
                $skipped > 0
                    ? 'PDF tidak dibuat karena nilai belum lengkap pada semua siswa.'
                    : 'Tidak ada siswa aktif untuk membuat PDF.',
            ]);

            return redirect()->route('admin.reports.show', $semester);
        }

        $message = sprintf('Pembuatan PDF dijalankan untuk %d siswa.', $result['queued']);
        if ($skipped > 0) {
            $message .= sprintf(' %d siswa dilewati karena nilai belum lengkap.', $skipped);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route('admin.reports.show', $semester);
    }

    public function retry(Request $request, Semester $semester): RedirectResponse
    {
        $retried = $this->publishService->retryFailed($semester);

        if ($retried === 0) {
            Inertia::flash('report_card', ['Tidak ada PDF gagal untuk diulangi.']);

            return redirect()->route('admin.reports.show', $semester);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Percobaan ulang dijalankan untuk %d PDF yang gagal.', $retried),
        ]);

        return redirect()->route('admin.reports.show', $semester);
    }

    public function publish(Request $request, Semester $semester): RedirectResponse
    {
        $result = $this->publishService->publish($semester, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Rapor berhasil diterbitkan untuk %d siswa.', $result['published']),
        ]);

        return redirect()->route('admin.reports.show', $semester);
    }

    /**
     * @return list<array{id: int, name: string, academic_year: string, is_active: bool, locked: bool, locked_at: string|null, published: bool, published_at: string|null}>
     */
    private function semesterRows(): array
    {
        $rows = [];

        foreach (Semester::query()->with('academicYear')->orderByDesc('academic_year_id')->orderByDesc('id')->get() as $semester) {
            $rows[] = $this->semesterPayload($semester);
        }

        return $rows;
    }

    /**
     * @return array{id: int, name: string, academic_year: string, is_active: bool, locked: bool, locked_at: string|null, published: bool, published_at: string|null}
     */
    private function semesterPayload(Semester $semester): array
    {
        return [
            'id' => $semester->getKey(),
            'name' => $semester->name->label(),
            'academic_year' => $semester->academicYear->name,
            'is_active' => $semester->is_active,
            'locked' => $semester->isLocked(),
            'locked_at' => $semester->grades_locked_at?->format('d/m/Y H:i'),
            'published' => $semester->isPublished(),
            'published_at' => $semester->report_published_at?->format('d/m/Y H:i'),
        ];
    }
}
