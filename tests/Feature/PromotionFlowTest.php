<?php

use App\Enums\ClassMajor;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\PromotionBatch;
use App\Models\PromotionDecision;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function promotionFlowContext(bool $semestersLocked = true): array
{
    $fromYear = AcademicYear::factory()->active()->create(['name' => '2026/2027']);
    Semester::factory()->ganjil()->create([
        'academic_year_id' => $fromYear->id,
        'grades_locked_at' => $semestersLocked ? now() : null,
        'report_published_at' => $semestersLocked ? now() : null,
    ]);
    Semester::factory()->genap()->create([
        'academic_year_id' => $fromYear->id,
        'grades_locked_at' => $semestersLocked ? now() : null,
        'report_published_at' => $semestersLocked ? now() : null,
    ]);
    $toYear = AcademicYear::factory()->create(['name' => '2027/2028']);
    User::factory()->asRole(Role::Admin)->create();

    return [$fromYear, $toYear];
}

function promotionFlowAdmin(): User
{
    return User::query()->where('role', Role::Admin)->firstOrFail();
}

function promotionFlowClassroom(AcademicYear $year, int $gradeLevel, string $name, ?ClassMajor $major = null): Classroom
{
    return Classroom::factory()->create([
        'academic_year_id' => $year->id,
        'grade_level' => $gradeLevel,
        'major' => $major,
        'name' => $name,
    ]);
}

function promotionFlowStudent(AcademicYear $year, Classroom $classroom): Student
{
    $student = Student::factory()->create();

    ClassEnrollment::factory()->create([
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'academic_year_id' => $year->id,
    ]);

    return $student;
}

it('redirects guests from promotion routes to login', function (): void {
    $decision = PromotionDecision::factory()->create();

    $this->get(route('admin.promotion.index'))->assertRedirect(route('login'));
    $this->post(route('admin.promotion.propose'))->assertRedirect(route('login'));
    $this->post(route('admin.promotion.override', $decision))->assertRedirect(route('login'));
    $this->post(route('admin.promotion.apply'))->assertRedirect(route('login'));
});

it('forbids non admin users from promotion pages', function (): void {
    $decision = PromotionDecision::factory()->create();

    foreach ([Role::Teacher, Role::Student, Role::Parent] as $role) {
        $user = User::factory()->asRole($role)->create();

        $this->actingAs($user)->get(route('admin.promotion.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.promotion.propose'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.promotion.override', $decision))->assertForbidden();
        $this->actingAs($user)->post(route('admin.promotion.apply'))->assertForbidden();
    }
});

it('exposes the promotion entry in the admin navigation', function (): void {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nav.2.label', 'Rapor')
            ->where('nav.2.items.1.key', 'promotion')
            ->where('nav.2.items.1.title', 'Kenaikan Kelas')
            ->where('nav.2.items.1.href', route('admin.promotion.index'))
            ->where('nav.2.items.1.coming_soon', false)
        );
});

it('shows the promotion years before a preview exists', function (): void {
    promotionFlowContext();

    $this->actingAs(promotionFlowAdmin())
        ->get(route('admin.promotion.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/promotion/Index')
            ->where('from.name', '2026/2027')
            ->where('to.name', '2027/2028')
            ->where('missing_year_message', null)
            ->where('batch', null)
            ->where('summary', null)
            ->where('decisions', [])
            ->where('target_classes', [])
            ->where('blockers', [])
        );
});

it('explains when the target academic year has not been created', function (): void {
    AcademicYear::factory()->active()->create(['name' => '2026/2027']);
    $admin = User::factory()->asRole(Role::Admin)->create();

    $this->actingAs($admin)
        ->get(route('admin.promotion.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/promotion/Index')
            ->where('to', null)
            ->where('missing_year_message', 'Tahun ajaran tujuan 2027/2028 belum dibuat. Tambahkan tahun ajaran tersebut di Data Master.')
        );
});

it('requires an active academic year before proposing', function (): void {
    AcademicYear::factory()->create(['name' => '2026/2027']);
    AcademicYear::factory()->create(['name' => '2027/2028']);
    $admin = User::factory()->asRole(Role::Admin)->create();

    $this->actingAs($admin)
        ->post(route('admin.promotion.propose'))
        ->assertSessionHasErrors(['promotion' => 'Belum ada tahun ajaran aktif. Tetapkan tahun ajaran aktif di Data Master terlebih dahulu.']);

    $this->actingAs($admin)
        ->get(route('admin.promotion.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('from', null)
            ->where('to', null)
            ->where('missing_year_message', 'Belum ada tahun ajaran aktif. Tetapkan tahun ajaran aktif di Data Master terlebih dahulu.')
        );
});

it('creates a preview from the page and flashes the confirmation', function (): void {
    [$fromYear, $toYear] = promotionFlowContext();
    $source = promotionFlowClassroom($fromYear, 10, 'X-1');
    promotionFlowClassroom($toYear, 11, 'XI-1');
    $student = promotionFlowStudent($fromYear, $source);

    $this->actingAs(promotionFlowAdmin())
        ->post(route('admin.promotion.propose'))
        ->assertRedirect(route('admin.promotion.index'));

    expect(session('inertia.flash_data')['toast']['message'])
        ->toBe('Pratinjau kenaikan kelas berhasil dibuat.');

    $this->actingAs(promotionFlowAdmin())
        ->get(route('admin.promotion.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/promotion/Index')
            ->where('batch.status', 'draft')
            ->where('batch.status_label', 'Draf')
            ->where('batch.applied_at', null)
            ->where('summary.total', 1)
            ->where('summary.promoted', 1)
            ->where('summary.retained', 0)
            ->where('summary.graduated', 0)
            ->where('summary.unassigned', 0)
            ->has('decisions', 1)
            ->where('decisions.0.student_id', $student->id)
            ->where('decisions.0.from_class', 'X-1')
            ->where('decisions.0.outcome', 'promote')
            ->where('decisions.0.to_class', 'XI-1')
            ->where('decisions.0.is_overridden', false)
            ->where('target_classes.0.name', 'XI-1')
            ->where('blockers', [])
        );
});

it('lists the exact apply blockers before the batch can run', function (): void {
    [$fromYear] = promotionFlowContext(semestersLocked: false);
    $source = promotionFlowClassroom($fromYear, 10, 'X-1');
    promotionFlowStudent($fromYear, $source);

    $this->actingAs(promotionFlowAdmin())->post(route('admin.promotion.propose'));

    $this->actingAs(promotionFlowAdmin())
        ->get(route('admin.promotion.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('blockers', 2)
            ->where('blockers.0', 'Kenaikan kelas hanya dapat dijalankan setelah semua semester terkunci.')
            ->where('blockers.1', 'Penerapan dibatalkan. Ada siswa yang belum memiliki kelas tujuan: 1 siswa menunggu penetapan kelas.')
            ->where('summary.unassigned', 1)
        );
});

it('validates override requests with exact messages', function (): void {
    [$fromYear, $toYear] = promotionFlowContext();
    $source = promotionFlowClassroom($fromYear, 10, 'X-1');
    promotionFlowClassroom($toYear, 11, 'XI-1');
    promotionFlowStudent($fromYear, $source);

    $admin = promotionFlowAdmin();
    $this->actingAs($admin)->post(route('admin.promotion.propose'));

    $decision = PromotionDecision::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.promotion.override', $decision), [])
        ->assertSessionHasErrors([
            'outcome' => 'Keputusan kenaikan wajib dipilih.',
            'reason' => 'Alasan perubahan keputusan wajib diisi.',
        ]);

    $this->actingAs($admin)
        ->post(route('admin.promotion.override', $decision), [
            'outcome' => 'maybe',
            'reason' => 'Alasan uji coba.',
        ])
        ->assertSessionHasErrors(['outcome' => 'Keputusan kenaikan tidak valid.']);

    $this->actingAs($admin)
        ->post(route('admin.promotion.override', $decision), [
            'outcome' => 'retain',
            'reason' => str_repeat('a', 501),
        ])
        ->assertSessionHasErrors(['reason' => 'Alasan perubahan keputusan maksimal 500 karakter.']);

    expect($decision->fresh()->is_overridden)->toBeFalse();
});

it('validates the target class against the destination year', function (): void {
    [$fromYear, $toYear] = promotionFlowContext();
    $source = promotionFlowClassroom($fromYear, 10, 'X-1');
    promotionFlowClassroom($toYear, 11, 'XI-1');
    promotionFlowStudent($fromYear, $source);

    $admin = promotionFlowAdmin();
    $this->actingAs($admin)->post(route('admin.promotion.propose'));

    $decision = PromotionDecision::query()->firstOrFail();
    $wrongYearClassroom = promotionFlowClassroom($fromYear, 11, 'XI-Percobaan');

    $this->actingAs($admin)
        ->post(route('admin.promotion.override', $decision), [
            'outcome' => 'promote',
            'reason' => 'Alasan uji coba.',
            'target_classroom_id' => 99999,
        ])
        ->assertSessionHasErrors(['target_classroom_id' => 'Kelas tujuan tidak ditemukan.']);

    $this->actingAs($admin)
        ->post(route('admin.promotion.override', $decision), [
            'outcome' => 'promote',
            'reason' => 'Alasan uji coba.',
            'target_classroom_id' => $wrongYearClassroom->id,
        ])
        ->assertSessionHasErrors(['target_classroom_id' => 'Kelas tujuan harus berada di tahun ajaran tujuan.']);

    expect($decision->fresh()->is_overridden)->toBeFalse();
});

it('overrides a decision with an audit trail and an ips track target', function (): void {
    [$fromYear, $toYear] = promotionFlowContext();
    $source = promotionFlowClassroom($fromYear, 11, 'XI IPA-1', ClassMajor::Ipa);
    $ipaTarget = promotionFlowClassroom($toYear, 12, 'XII IPA-1', ClassMajor::Ipa);
    $ipsTarget = promotionFlowClassroom($toYear, 12, 'XII IPS-1', ClassMajor::Ips);
    $student = promotionFlowStudent($fromYear, $source);

    $admin = promotionFlowAdmin();
    $this->actingAs($admin)->post(route('admin.promotion.propose'));

    $decision = PromotionDecision::query()->where('student_id', $student->id)->firstOrFail();

    expect($decision->outcome->value)->toBe('promote')
        ->and($decision->to_classroom_id)->toBe($ipaTarget->id);

    $this->actingAs($admin)
        ->get(route('admin.promotion.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('decisions.0.to_class', 'XII IPA-1')
            ->where('decisions.0.is_overridden', false)
        );

    $this->actingAs($admin)
        ->post(route('admin.promotion.override', $decision), [
            'outcome' => 'promote',
            'reason' => 'Memilih jurusan IPS sesuai minat siswa.',
            'target_classroom_id' => $ipsTarget->id,
        ])
        ->assertRedirect(route('admin.promotion.index'));

    expect(session('inertia.flash_data')['toast']['message'])
        ->toBe('Keputusan kenaikan siswa berhasil diubah.');

    $overridden = $decision->fresh();

    expect($overridden->is_overridden)->toBeTrue()
        ->and($overridden->override_reason)->toBe('Memilih jurusan IPS sesuai minat siswa.')
        ->and($overridden->to_classroom_id)->toBe($ipsTarget->id)
        ->and(AuditLog::query()->where('action', 'promotion.override')->count())->toBe(1);

    $this->actingAs($admin)
        ->get(route('admin.promotion.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('decisions.0.to_class', 'XII IPS-1')
            ->where('decisions.0.is_overridden', true)
        );
});

it('applies the batch from the page and moves enrollments', function (): void {
    [$fromYear, $toYear] = promotionFlowContext();
    $source = promotionFlowClassroom($fromYear, 10, 'X-1');
    $target = promotionFlowClassroom($toYear, 11, 'XI-1');
    $student = promotionFlowStudent($fromYear, $source);

    $admin = promotionFlowAdmin();

    $this->actingAs($admin)->post(route('admin.promotion.propose'));

    $this->actingAs($admin)
        ->post(route('admin.promotion.apply'))
        ->assertRedirect(route('admin.promotion.index'));

    expect(session('inertia.flash_data')['toast']['message'])
        ->toBe('Kenaikan kelas berhasil diterapkan untuk 1 siswa.');

    $batch = PromotionBatch::query()->firstOrFail();
    $enrollment = ClassEnrollment::query()
        ->where('student_id', $student->id)
        ->where('academic_year_id', $toYear->id)
        ->firstOrFail();

    expect($batch->status->value)->toBe('applied')
        ->and($batch->applied_at)->not->toBeNull()
        ->and($enrollment->classroom_id)->toBe($target->id)
        ->and(AuditLog::query()->where('action', 'promotion.apply')->count())->toBe(1);

    $this->actingAs($admin)
        ->get(route('admin.promotion.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('batch.status', 'applied')
            ->where('batch.status_label', 'Diterapkan')
            ->where('batch.applied_at', $batch->applied_at->format('d/m/Y H:i'))
            ->where('blockers', [])
        );
});

it('flashes an error when applying an already applied batch', function (): void {
    [$fromYear, $toYear] = promotionFlowContext();
    $source = promotionFlowClassroom($fromYear, 10, 'X-1');
    promotionFlowClassroom($toYear, 11, 'XI-1');
    promotionFlowStudent($fromYear, $source);

    $admin = promotionFlowAdmin();

    $this->actingAs($admin)->post(route('admin.promotion.propose'));
    $this->actingAs($admin)->post(route('admin.promotion.apply'));

    expect(AuditLog::query()->where('action', 'promotion.apply')->count())->toBe(1);

    $this->actingAs($admin)
        ->post(route('admin.promotion.apply'))
        ->assertRedirect(route('admin.promotion.index'));

    expect(session('inertia.flash_data')['promotion'])
        ->toBe(['Batch kenaikan kelas ini sudah pernah diterapkan.'])
        ->and(AuditLog::query()->where('action', 'promotion.apply')->count())->toBe(1)
        ->and(ClassEnrollment::query()->where('academic_year_id', $toYear->id)->count())->toBe(1);
});

it('blocks applying while a semester of the year is unlocked', function (): void {
    [$fromYear, $toYear] = promotionFlowContext(semestersLocked: false);
    $source = promotionFlowClassroom($fromYear, 10, 'X-1');
    promotionFlowClassroom($toYear, 11, 'XI-1');
    promotionFlowStudent($fromYear, $source);

    $admin = promotionFlowAdmin();

    $this->actingAs($admin)->post(route('admin.promotion.propose'));

    $this->actingAs($admin)
        ->post(route('admin.promotion.apply'))
        ->assertSessionHasErrors(['academic_year' => 'Kenaikan kelas hanya dapat dijalankan setelah semua semester terkunci.']);

    expect(PromotionBatch::query()->firstOrFail()->status->value)->toBe('draft')
        ->and(ClassEnrollment::query()->where('academic_year_id', $toYear->id)->count())->toBe(0);
});

it('blocks applying while students wait for a target class', function (): void {
    [$fromYear] = promotionFlowContext();
    $source = promotionFlowClassroom($fromYear, 10, 'X-1');
    promotionFlowStudent($fromYear, $source);

    $admin = promotionFlowAdmin();

    $this->actingAs($admin)->post(route('admin.promotion.propose'));

    $this->actingAs($admin)
        ->post(route('admin.promotion.apply'))
        ->assertSessionHasErrors(['promotion_batch' => 'Penerapan dibatalkan. Ada siswa yang belum memiliki kelas tujuan: 1 siswa menunggu penetapan kelas.']);

    expect(PromotionBatch::query()->firstOrFail()->status->value)->toBe('draft');
});

it('blocks a new preview after the batch has been applied', function (): void {
    [$fromYear, $toYear] = promotionFlowContext();
    $source = promotionFlowClassroom($fromYear, 10, 'X-1');
    promotionFlowClassroom($toYear, 11, 'XI-1');
    promotionFlowStudent($fromYear, $source);

    $admin = promotionFlowAdmin();

    $this->actingAs($admin)->post(route('admin.promotion.propose'));
    $this->actingAs($admin)->post(route('admin.promotion.apply'));

    $this->actingAs($admin)
        ->post(route('admin.promotion.propose'))
        ->assertSessionHasErrors(['promotion_batch' => 'Batch kenaikan kelas untuk pasangan tahun ajaran ini sudah pernah diterapkan.']);
});
