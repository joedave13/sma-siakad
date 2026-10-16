<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Subject;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests from the audit log page', function (): void {
    $this->get(route('admin.audit.index'))->assertRedirect(route('login'));
});

it('forbids a teacher from reading the audit log', function (): void {
    $this->actingAs(User::factory()->asRole(Role::Teacher)->create())
        ->get(route('admin.audit.index'))
        ->assertForbidden();
});

it('lists the newest audit entries with actor, time and object', function (): void {
    $admin = User::factory()->asRole(Role::Admin)->create();
    $subjectEntry = AuditLog::factory()->create([
        'action' => 'subject.update',
        'auditable_type' => Subject::class,
        'auditable_id' => 5,
        'new_values' => ['kkm' => 80],
    ]);
    AuditLog::factory()->create(['action' => 'semester.unlock']);

    $this->actingAs($admin)->get(route('admin.audit.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/audit/Index')
            ->has('logs', 2)
            ->where('logs.0.action', 'semester.unlock')
            ->where('logs.1.action', 'subject.update')
            ->where('logs.1.user', $subjectEntry->user->name)
            ->where('logs.1.object', 'Subject #5')
            ->where('logs.1.old_values', null)
            ->where('logs.1.new_values.kkm', 80)
            ->where('logs.1.time', fn ($time): bool => (bool) preg_match('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}$/', $time))
            ->has('actions', 2)
            ->where('meta.total', 2)
            ->where('filter.action', ''));
});

it('filters the list by action', function (): void {
    $admin = User::factory()->asRole(Role::Admin)->create();
    AuditLog::factory()->create(['action' => 'subject.update']);
    AuditLog::factory()->create(['action' => 'semester.unlock']);

    $this->actingAs($admin)->get(route('admin.audit.index', ['action' => 'subject.update']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs', 1)
            ->where('logs.0.action', 'subject.update')
            ->where('meta.total', 1)
            ->where('filter.action', 'subject.update')
            ->has('actions', 2));
});

it('shows a fallback when the acting user was deleted', function (): void {
    $admin = User::factory()->asRole(Role::Admin)->create();
    AuditLog::factory()->create(['user_id' => null, 'action' => 'system.import']);

    $this->actingAs($admin)->get(route('admin.audit.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs', 1)
            ->where('logs.0.user', 'Pengguna terhapus'));
});

it('paginates the list at twenty entries per page', function (): void {
    $admin = User::factory()->asRole(Role::Admin)->create();
    AuditLog::factory()->count(21)->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->get(route('admin.audit.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs', 20)
            ->where('meta.current_page', 1)
            ->where('meta.last_page', 2)
            ->where('meta.total', 21)
            ->where('meta.prev_page_url', null)
            ->whereNotNull('meta.next_page_url'));

    $this->actingAs($admin)->get(route('admin.audit.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs', 1)
            ->where('meta.current_page', 2)
            ->where('meta.total', 21)
            ->whereNotNull('meta.prev_page_url'));
});

it('renders an empty list when nothing has been recorded', function (): void {
    $admin = User::factory()->asRole(Role::Admin)->create();

    $this->actingAs($admin)->get(route('admin.audit.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs', 0)
            ->has('actions', 0)
            ->where('meta.total', 0)
            ->where('filter.action', ''));
});
