<?php

namespace App\Http\Controllers\Admin\Accounts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounts\GuardianLinkRequest;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GuardianLinkController extends Controller
{
    public function store(GuardianLinkRequest $request, Guardian $guardian): RedirectResponse
    {
        $validated = $request->validated();
        $student = Student::query()->whereKey($validated['student_id'])->firstOrFail();

        $guardian->students()->attach($student->getKey(), [
            'relationship' => $validated['relationship'],
        ]);

        AuditLogger::record('guardian.link', $guardian, [], [
            'student_id' => $student->getKey(),
            'student_nisn' => $student->nisn,
            'relationship' => $validated['relationship'],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$student->full_name} ({$student->nisn}) tertaut sebagai anak dari {$guardian->full_name}.",
        ]);

        return back();
    }

    public function destroy(Guardian $guardian, Student $student): RedirectResponse
    {
        $relationship = DB::table('guardian_student')
            ->where('guardian_id', $guardian->getKey())
            ->where('student_id', $student->getKey())
            ->value('relationship');

        $guardian->students()->detach($student->getKey());

        AuditLogger::record('guardian.unlink', $guardian, [
            'student_id' => $student->getKey(),
            'student_nisn' => $student->nisn,
            'relationship' => (string) $relationship,
        ], []);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Tautan {$student->full_name} ({$student->nisn}) dengan {$guardian->full_name} dilepas.",
        ]);

        return back();
    }
}
