<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PromotionBatchStatus;
use App\Enums\PromotionOutcome;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\PromotionBatch;
use App\Models\PromotionDecision;
use App\Services\Promotion\PromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function __construct(private readonly PromotionService $promotionService) {}

    public function index(): Response
    {
        [$from, $to] = $this->resolveYears();

        $batch = null;
        $summary = null;
        $decisions = [];
        $targetClasses = [];
        $blockers = [];

        if ($from !== null && $to !== null) {
            $batch = $this->findBatch($from, $to);

            if ($batch !== null) {
                $decisions = $this->decisionRows($batch);
                $summary = $this->summary($batch);
                $targetClasses = $this->targetClasses($batch);
                $blockers = $this->blockers($batch, $from);
            }
        }

        return Inertia::render('admin/promotion/Index', [
            'from' => $from !== null ? ['id' => $from->getKey(), 'name' => $from->name] : null,
            'to' => $to !== null ? ['id' => $to->getKey(), 'name' => $to->name] : null,
            'missing_year_message' => $this->missingYearMessage($from, $to),
            'batch' => $batch !== null ? [
                'id' => $batch->getKey(),
                'status' => $batch->status->value,
                'status_label' => $batch->status->label(),
                'applied_at' => $batch->applied_at?->format('d/m/Y H:i'),
            ] : null,
            'summary' => $summary,
            'decisions' => $decisions,
            'target_classes' => $targetClasses,
            'blockers' => $blockers,
        ]);
    }

    public function propose(): RedirectResponse
    {
        [$from, $to] = $this->resolveYearsOrFail();

        $this->promotionService->propose($from, $to);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pratinjau kenaikan kelas berhasil dibuat.']);

        return redirect()->route('admin.promotion.index');
    }

    public function override(Request $request, PromotionDecision $promotionDecision): RedirectResponse
    {
        $validated = $request->validate(
            [
                'outcome' => ['required', 'string', Rule::enum(PromotionOutcome::class)],
                'reason' => ['required', 'string', 'max:500'],
                'target_classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            ],
            [
                'outcome.required' => 'Keputusan kenaikan wajib dipilih.',
                'outcome.enum' => 'Keputusan kenaikan tidak valid.',
                'reason.required' => 'Alasan perubahan keputusan wajib diisi.',
                'reason.max' => 'Alasan perubahan keputusan maksimal 500 karakter.',
                'target_classroom_id.exists' => 'Kelas tujuan tidak ditemukan.',
            ],
        );

        $targetClassroomId = $validated['target_classroom_id'] ?? null;

        if ($targetClassroomId !== null) {
            $belongs = Classroom::query()
                ->whereKey($targetClassroomId)
                ->where('academic_year_id', $promotionDecision->promotionBatch->to_academic_year_id)
                ->exists();

            if (! $belongs) {
                throw ValidationException::withMessages([
                    'target_classroom_id' => 'Kelas tujuan harus berada di tahun ajaran tujuan.',
                ]);
            }
        }

        $this->promotionService->override(
            $promotionDecision,
            PromotionOutcome::from($validated['outcome']),
            trim($validated['reason']),
            $targetClassroomId,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Keputusan kenaikan siswa berhasil diubah.']);

        return redirect()->route('admin.promotion.index');
    }

    public function apply(): RedirectResponse
    {
        [$from, $to] = $this->resolveYearsOrFail();

        $batch = $this->findBatch($from, $to);

        if ($batch === null) {
            throw ValidationException::withMessages([
                'promotion' => 'Belum ada pratinjau kenaikan kelas. Buat pratinjau terlebih dahulu.',
            ]);
        }

        if ($batch->status === PromotionBatchStatus::Applied) {
            Inertia::flash('promotion', ['Batch kenaikan kelas ini sudah pernah diterapkan.']);

            return redirect()->route('admin.promotion.index');
        }

        $total = $batch->decisions()->count();

        $this->promotionService->apply($batch);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Kenaikan kelas berhasil diterapkan untuk %d siswa.', $total),
        ]);

        return redirect()->route('admin.promotion.index');
    }

    /**
     * @return array{0: AcademicYear|null, 1: AcademicYear|null}
     */
    private function resolveYears(): array
    {
        $from = AcademicYear::query()->where('is_active', true)->first();

        if ($from === null) {
            return [null, null];
        }

        $expected = $this->expectedToYearName($from);
        $to = $expected !== null
            ? AcademicYear::query()->where('name', $expected)->first()
            : null;

        return [$from, $to];
    }

    /**
     * @return array{0: AcademicYear, 1: AcademicYear}
     *
     * @throws ValidationException
     */
    private function resolveYearsOrFail(): array
    {
        [$from, $to] = $this->resolveYears();

        if ($from === null) {
            throw ValidationException::withMessages(['promotion' => $this->missingActiveYearMessage()]);
        }

        if ($to === null) {
            throw ValidationException::withMessages([
                'promotion' => $this->missingToYearMessage($this->expectedToYearName($from)),
            ]);
        }

        return [$from, $to];
    }

    private function missingYearMessage(?AcademicYear $from, ?AcademicYear $to): ?string
    {
        if ($from === null) {
            return $this->missingActiveYearMessage();
        }

        if ($to === null) {
            return $this->missingToYearMessage($this->expectedToYearName($from));
        }

        return null;
    }

    private function missingActiveYearMessage(): string
    {
        return 'Belum ada tahun ajaran aktif. Tetapkan tahun ajaran aktif di Data Master terlebih dahulu.';
    }

    private function missingToYearMessage(?string $expected): string
    {
        return $expected !== null
            ? sprintf('Tahun ajaran tujuan %s belum dibuat. Tambahkan tahun ajaran tersebut di Data Master.', $expected)
            : 'Tahun ajaran tujuan belum dapat ditentukan. Periksa nama tahun ajaran aktif di Data Master.';
    }

    private function expectedToYearName(AcademicYear $from): ?string
    {
        if (! preg_match('/^(\d{4})\//', $from->name, $matches)) {
            return null;
        }

        $start = ((int) $matches[1]) + 1;

        return $start.'/'.($start + 1);
    }

    private function findBatch(AcademicYear $from, AcademicYear $to): ?PromotionBatch
    {
        return PromotionBatch::query()
            ->where('from_academic_year_id', $from->getKey())
            ->where('to_academic_year_id', $to->getKey())
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return list<array{id: int, student_id: int, full_name: string|null, nisn: string|null, from_class: string|null, from_grade_level: int|null, outcome: string, to_class: string|null, to_class_id: int|null, is_overridden: bool, override_reason: string|null}>
     */
    private function decisionRows(PromotionBatch $batch): array
    {
        $rows = [];

        $decisions = $batch->decisions()
            ->with(['student', 'fromClassroom', 'toClassroom'])
            ->orderBy('id')
            ->get();

        foreach ($decisions as $decision) {
            $rows[] = [
                'id' => $decision->getKey(),
                'student_id' => $decision->student_id,
                'full_name' => $decision->student?->full_name,
                'nisn' => $decision->student?->nisn,
                'from_class' => $decision->fromClassroom?->name,
                'from_grade_level' => $decision->fromClassroom?->grade_level,
                'outcome' => $decision->outcome->value,
                'to_class' => $decision->toClassroom?->name,
                'to_class_id' => $decision->to_classroom_id,
                'is_overridden' => $decision->is_overridden,
                'override_reason' => $decision->override_reason,
            ];
        }

        return $rows;
    }

    /**
     * @return array{total: int, promoted: int, retained: int, graduated: int, unassigned: int}
     */
    private function summary(PromotionBatch $batch): array
    {
        $promoted = $batch->decisions()->where('outcome', PromotionOutcome::Promote->value)->count();
        $retained = $batch->decisions()->where('outcome', PromotionOutcome::Retain->value)->count();
        $graduated = $batch->decisions()->where('outcome', PromotionOutcome::Graduate->value)->count();
        $unassigned = $batch->decisions()
            ->whereNull('to_classroom_id')
            ->where('outcome', '!=', PromotionOutcome::Graduate->value)
            ->count();

        return [
            'total' => $promoted + $retained + $graduated,
            'promoted' => $promoted,
            'retained' => $retained,
            'graduated' => $graduated,
            'unassigned' => $unassigned,
        ];
    }

    /**
     * @return list<array{id: int, name: string, grade_level: int, major: string|null}>
     */
    private function targetClasses(PromotionBatch $batch): array
    {
        $rows = [];

        $classrooms = Classroom::query()
            ->where('academic_year_id', $batch->to_academic_year_id)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        foreach ($classrooms as $classroom) {
            $rows[] = [
                'id' => $classroom->getKey(),
                'name' => $classroom->name,
                'grade_level' => $classroom->grade_level,
                'major' => $classroom->major?->value,
            ];
        }

        return $rows;
    }

    /**
     * The same blocked-state messages the service throws on apply, surfaced
     * before the admin confirms.
     *
     * @return list<string>
     */
    private function blockers(PromotionBatch $batch, AcademicYear $from): array
    {
        if ($batch->status !== PromotionBatchStatus::Draft) {
            return [];
        }

        $blockers = [];

        $unlocked = $from->semesters()->whereNull('grades_locked_at')->count();

        if ($unlocked > 0) {
            $blockers[] = 'Kenaikan kelas hanya dapat dijalankan setelah semua semester terkunci.';
        }

        $unassigned = $batch->decisions()
            ->whereNull('to_classroom_id')
            ->where('outcome', '!=', PromotionOutcome::Graduate->value)
            ->count();

        if ($unassigned > 0) {
            $blockers[] = sprintf(
                'Penerapan dibatalkan. Ada siswa yang belum memiliki kelas tujuan: %d siswa menunggu penetapan kelas.',
                $unassigned,
            );
        }

        return $blockers;
    }
}
