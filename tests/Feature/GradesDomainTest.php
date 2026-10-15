<?php

use App\Enums\GradeComponent;
use App\Models\GradeEntry;
use App\Models\GradeRemark;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Services\Grades\FinalScoreCalculator;
use App\Services\Grades\GradeEntryService;
use Illuminate\Validation\ValidationException;

it('computes the weighted final score with 30/20/20/30 weights, rounded to an integer', function (): void {
    $entries = [
        ['component' => GradeComponent::Assignment, 'score' => 82.5],
        ['component' => GradeComponent::Assignment, 'score' => 85.0],
        ['component' => GradeComponent::Quiz, 'score' => 90],
        ['component' => GradeComponent::Midterm, 'score' => 78],
        ['component' => GradeComponent::Final, 'score' => 88],
    ];

    // assignment avg 83.75 * .30 = 25.125; quiz 90 * .20 = 18; uts 78 * .20 = 15.6; uas 88 * .30 = 26.4 => 85.125 => 85
    expect(app(FinalScoreCalculator::class)->finalScore($entries))->toBe(85);
});

it('averages multiple assignment and quiz entries per component', function (): void {
    $entries = [
        ['component' => 'assignment', 'score' => '70.00'],
        ['component' => 'assignment', 'score' => '90.00'],
        ['component' => 'quiz', 'score' => '60'],
        ['component' => 'quiz', 'score' => '80'],
        ['component' => 'midterm', 'score' => '75'],
        ['component' => 'final', 'score' => '85'],
    ];

    // assignment 80*.3=24; quiz 70*.2=14; uts 75*.2=15; uas 85*.3=25.5 => 78.5 => 79 (round half up)
    expect(app(FinalScoreCalculator::class)->finalScore($entries))->toBe(79);
});

it('treats a blank component as missing, not zero', function (): void {
    $calculator = app(FinalScoreCalculator::class);

    $withZero = [
        ['component' => GradeComponent::Assignment, 'score' => 0],
        ['component' => GradeComponent::Quiz, 'score' => 80],
        ['component' => GradeComponent::Midterm, 'score' => 80],
        ['component' => GradeComponent::Final, 'score' => 80],
    ];

    $withBlank = [
        ['component' => GradeComponent::Assignment, 'score' => null],
        ['component' => GradeComponent::Quiz, 'score' => 80],
        ['component' => GradeComponent::Midterm, 'score' => 80],
        ['component' => GradeComponent::Final, 'score' => 80],
    ];

    expect($calculator->finalScore($withZero))->toBe(56)
        ->and($calculator->finalScore($withBlank))->toBeNull()
        ->and($calculator->missingComponents($withBlank))->toBe([GradeComponent::Assignment]);
});

it('creates singular midterm/final rows once and updates them in place', function (): void {
    $service = app(GradeEntryService::class);
    $assignment = TeachingAssignment::factory()->create();
    $student = Student::factory()->create();

    $service->upsertEntry($assignment, $student, GradeComponent::Midterm, null, '75');
    $service->upsertEntry($assignment, $student, GradeComponent::Midterm, null, '80');

    expect(GradeEntry::query()->where('component', 'midterm')->count())->toBe(1)
        ->and(GradeEntry::query()->where('component', 'midterm')->first()->score)->toBe('80.00');
});

it('keeps repeatable assignment entries separate per title and averages blanks as missing', function (): void {
    $service = app(GradeEntryService::class);
    $assignment = TeachingAssignment::factory()->create();
    $student = Student::factory()->create();

    $service->upsertEntry($assignment, $student, GradeComponent::Assignment, 'Tugas 1', '80');
    $service->upsertEntry($assignment, $student, GradeComponent::Assignment, 'Tugas 2', '90');
    $service->upsertEntry($assignment, $student, GradeComponent::Assignment, 'Tugas 3', null);

    $entries = GradeEntry::query()->where('student_id', $student->id)->get()
        ->map(fn (GradeEntry $entry) => ['component' => $entry->component, 'score' => $entry->score])
        ->all();

    // Tugas 3 blank is ignored in the average: (80+90)/2 = 85
    expect(GradeEntry::query()->count())->toBe(3)
        ->and(app(FinalScoreCalculator::class)->missingComponents($entries))->toBe([
            GradeComponent::Quiz,
            GradeComponent::Midterm,
            GradeComponent::Final,
        ]);
});

it('rejects a stale save with the current value in an Indonesian message', function (): void {
    $service = app(GradeEntryService::class);
    $entry = GradeEntry::factory()->create(['score' => 77.5]);
    $stale = $entry->updated_at->toJSON();

    $this->travel(1)->seconds();
    $service->updateScore($entry, '88', $stale);

    expect(fn () => $service->updateScore($entry->fresh(), '90', $stale))
        ->toThrow(ValidationException::class, 'Data nilai telah diubah oleh pengguna lain. Nilai saat ini: 88.00.');

    $this->travelBack();
});

it('rejects grade edits once the semester is locked and allows them again after unlock', function (): void {
    $service = app(GradeEntryService::class);
    $assignment = TeachingAssignment::factory()->create();
    $student = Student::factory()->create();

    $service->upsertEntry($assignment, $student, GradeComponent::Midterm, null, '70');
    $assignment->semester->update(['grades_locked_at' => now()]);

    expect(fn () => $service->upsertEntry($assignment->fresh(), $student, GradeComponent::Midterm, null, '80'))
        ->toThrow(ValidationException::class, 'Nilai tidak dapat diubah karena semester telah dikunci.');

    Semester::query()->whereKey($assignment->semester_id)->update(['grades_locked_at' => null]);

    $service->upsertEntry($assignment->fresh(), $student, GradeComponent::Midterm, null, '80');

    expect($assignment->fresh()->semester->isLocked())->toBeFalse()
        ->and(GradeEntry::query()->where('student_id', $student->id)->first()->score)->toBe('80.00');
});

it('validates score bounds and decimal places', function (): void {
    $service = app(GradeEntryService::class);
    $assignment = TeachingAssignment::factory()->create();
    $student = Student::factory()->create();

    expect(fn () => $service->upsertEntry($assignment, $student, GradeComponent::Quiz, 'Kuis 1', '101'))
        ->toThrow(ValidationException::class, 'Nilai harus antara 0 dan 100.');

    expect(fn () => $service->upsertEntry($assignment, $student, GradeComponent::Quiz, 'Kuis 1', '-1'))
        ->toThrow(ValidationException::class, 'Nilai harus antara 0 dan 100.');

    expect(fn () => $service->upsertEntry($assignment, $student, GradeComponent::Quiz, 'Kuis 1', '80.123'))
        ->toThrow(ValidationException::class, 'Nilai maksimal 2 angka desimal.');

    $service->upsertEntry($assignment, $student, GradeComponent::Quiz, 'Kuis 1', '80.12');

    expect(GradeEntry::query()->where('component', 'quiz')->first()->score)->toBe('80.12');
});

it('upserts one remark per assignment-student pair', function (): void {
    $service = app(GradeEntryService::class);
    $assignment = TeachingAssignment::factory()->create();
    $student = Student::factory()->create();

    $service->saveRemark($assignment, $student, 'Perlu bimbingan.');
    $service->saveRemark($assignment, $student, 'Sudah meningkat.');

    expect(GradeRemark::query()->count())->toBe(1)
        ->and(GradeRemark::query()->first()->remark)->toBe('Sudah meningkat.');
});

it('stores a zero score as zero, distinct from blank', function (): void {
    $service = app(GradeEntryService::class);
    $assignment = TeachingAssignment::factory()->create();
    $student = Student::factory()->create();

    $service->upsertEntry($assignment, $student, GradeComponent::Midterm, null, '0');
    $zero = GradeEntry::query()->where('component', 'midterm')->first();

    $service->upsertEntry($assignment, $student, GradeComponent::Final, null, null);
    $blank = GradeEntry::query()->where('component', 'final')->first();

    expect($zero->score)->toBe('0.00')
        ->and($blank->score)->toBeNull();
});
