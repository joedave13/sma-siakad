<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Master\SubjectRequest;
use App\Models\Subject;
use App\Services\AuditLogger;
use App\Services\MasterDataDeleteGuard;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    /**
     * @var list<string>
     */
    private const AUDIT_FIELDS = ['code', 'name', 'kkm'];

    public function __construct(private readonly MasterDataDeleteGuard $guard) {}

    public function index(): Response
    {
        $subjects = Subject::query()->orderBy('name')->get();

        return Inertia::render('admin/master/Subjects', [
            'subjects' => $subjects,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/master/SubjectForm', [
            'subject' => null,
        ]);
    }

    public function store(SubjectRequest $request): RedirectResponse
    {
        $subject = Subject::query()->create($request->validated());

        AuditLogger::record('subject.create', $subject, [], $subject->only(self::AUDIT_FIELDS));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Mata pelajaran berhasil ditambahkan.',
        ]);

        return redirect()->route('admin.master.subjects.index');
    }

    public function edit(Subject $subject): Response
    {
        return Inertia::render('admin/master/SubjectForm', [
            'subject' => $subject,
        ]);
    }

    public function update(SubjectRequest $request, Subject $subject): RedirectResponse
    {
        $old = $subject->only(self::AUDIT_FIELDS);

        $subject->update($request->validated());

        AuditLogger::record('subject.update', $subject, $old, $subject->only(self::AUDIT_FIELDS));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Mata pelajaran berhasil diperbarui.',
        ]);

        return redirect()->route('admin.master.subjects.index');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $this->guard->blockIfInUse($subject);

        $old = $subject->only(self::AUDIT_FIELDS);
        $subject->delete();

        AuditLogger::record('subject.delete', $subject, $old, []);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Mata pelajaran berhasil dihapus.',
        ]);

        return redirect()->route('admin.master.subjects.index');
    }
}
