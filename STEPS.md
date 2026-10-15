# Build Steps: SIAKAD-SMA (from PRD.md)

Protocol: run one step at a time, wait for "go" between steps. Every step must end with the app booting, migrations clean, and `php artisan test --compact` green. All steps sized **M**.

**Baseline facts** (override a few PRD assumptions):
- Installed stack is **Laravel 13** (`laravel/framework ^13.17`), not 12. Inertia v3, Wayfinder, Fortify + passkeys, shadcn-vue (reka-ui) on Tailwind 4, Pest 5, larastan, Pint.
- PHP 8.4, MySQL 8 local (`sma_siakad`), `QUEUE/SESSION/CACHE=database` already set. GD available (logo resize, no dep). Tests run on sqlite `:memory:`.
- `users` today: name/email/password only; User implements `MustVerifyEmail`; `register` routes active; dashboard behind `verified` middleware. All three conflict with PRD A2 and are fixed in step 3.
- No `notifications` table migration yet; no PDF/roles/import packages; no `DESIGN.md`.

**Locked decisions:**
- Roles: native `users.role` enum + Gates/Policies + role middleware (no spatie).
- PDF: `barryvdh/laravel-dompdf`.
- Import: `maatwebsite/excel` (CSV + XLSX).

**Risk register (retired first):**
1. `maatwebsite/excel` compat with Laravel 13 -> step 1. Fallback branch: `openspout/openspout` + native CSV, back to user for approval if it fails.
2. dompdf A4 fidelity for the Indonesian report card -> proven by a real PDF test in step 9, before any UI is built on it.
3. sqlite (tests) vs MySQL (prod) divergence -> every migration step verified on both.
4. Attendance upsert/daily-status concurrency -> isolated, heavily tested in step 7, no UI yet.

---

## Phase 0 - Foundation (risk first)

### Step 1. Install dependencies and prove they work on Laravel 13 [NEW DEPS]
- Touches: `composer.json`, `composer.lock`, tests
- Changes: `composer require barryvdh/laravel-dompdf maatwebsite/excel`. Smoke tests: render a one-page dummy PDF; round-trip a 2-row XLSX. If maatwebsite/excel cannot resolve against Laravel 13, stop and report with `composer why-not`; fallback is openspout.
- Verify: `php artisan about`; both smoke tests pass; full suite green.

### Step 2. Indonesian locale, WIB timezone, MySQL session TZ [ENV]
- Touches: `config/app.php`, `config/database.php`, `.env`, `.env.example`, `lang/id` as needed
- Changes: `APP_LOCALE=id`, `APP_TIMEZONE=Asia/Jakarta`, app timezone `Asia/Jakarta`, MySQL connection `timezone=+07:00`. Carbon Indonesian month names for the report card date line.
- Verify: `php artisan config:show`; date-line test asserting e.g. `15 Oktober 2026`; suite green.

### Step 3. Users schema + auth hardening [MIGRATION]
- Touches: new migration, `app/Enums/Role.php`, `app/Models/User.php`, `app/Http/Middleware/ForceChangePassword.php`, `app/Providers/FortifyServiceProvider.php`, `bootstrap/app.php`, `routes/web.php`, `resources/js/pages/auth/*`, tests, `UserFactory`
- Changes: add `username` (unique), `role` (VARCHAR cast to `Role` enum), `is_active`, `must_change_password`. Remove `MustVerifyEmail` + `verified` middleware (email stays nullable). Disable self-registration. `role` middleware alias. Forced password change after login via Fortify `LoginResponse`. Admin-only password reset endpoint.
- Verify: tests for register disabled, inactive user cannot log in, forced-change redirect, role middleware blocks cross-role; `migrate:fresh` on MySQL.

### Step 4. Academic core schema + audit log + notifications [MIGRATION]
- Touches: migrations for `academic_years`, `semesters`, `lesson_periods`, `subjects`, `school_profiles`, `academic_settings`, `audit_logs`, Laravel `notifications`; models/enums; `app/Services/AuditLogger.php`; `DatabaseSeeder`
- Changes: full PRD section 4 core tables incl. `grades_locked_at` / `report_published_at`, single-row `school_profiles`, key/value `academic_settings` (predicate bands, `promotion_max_failed_subjects=3`, `default_kkm=75`). Seeders: initial admin, 10 lesson periods, default settings (idempotent).
- Verify: `migrate:fresh --seed` on sqlite and MySQL; single-active year/semester test; audit logger writes a row.

### Step 5. People & org schema + factories [MIGRATION]
- Touches: migrations `teachers`, `students`, `guardians`, `guardian_student`, `classrooms`, `class_enrollments`, `teaching_assignments`, `schedules`; models; factories; PRD indexes
- Changes: FKs `RESTRICT` except `guardian_student` cascade; unique keys global across soft deletes; required indexes.
- Verify: `migrate:fresh` both engines; factory smoke test creates full linked graph.

### Step 6. Schedule integrity service
- Touches: `app/Services/Scheduling/ScheduleCollisionService.php`, admin policies baseline, tests
- Changes: enforce A3 ACs in one place: one class per student per year, one teacher per subject-class per semester, teacher never in two classes in the same period.
- Verify: feature tests for each AC incl. same-period cross-class conflict.

### Step 7. Attendance domain + daily recompute + absence notifications [MIGRATION]
- Touches: migrations `attendance_sessions`, `attendances`, `daily_attendances`; `app/Services/Attendance/*`, `app/Jobs/RecomputeDailyAttendance.php`, `app/Notifications/ChildAbsent.php`, models, tests
- Changes: single bulk `upsert()` per session in one transaction against `(schedule_id, date)`; default all Present; edit window (submitting teacher until 23:59 next day, admin after); daily status = first recorded period of the day, Late -> Hadir + flag; recompute queued; absent triggers queued in-app notification to linked guardians; admin override with audit.
- Verify: tests for first-period rule, missing-first-period fallback + later recompute, late flag, upsert idempotency, edit-window denial, notification queued.

### Step 8. Grades domain [MIGRATION]
- Touches: migrations `grade_entries`, `grade_remarks`; `app/Services/Grades/FinalScoreCalculator.php`; casts; tests
- Changes: fixed 30/20/20/30 weights, multi-entry assignment/quiz averaged, midterm/final max one row, `DECIMAL(5,2)`, blank != 0, server-side integer final score, per-subject KKM, optimistic check on `updated_at`.
- Verify: tests for each weighting case, blank-vs-zero, stale-save conflict, locked-grade edit rejection.

### Step 9. Report card PDF service + `report_cards` schema [MIGRATION] (retires dompdf risk)
- Touches: migration `report_cards`; `app/Services/ReportCard/ReportCardSnapshotBuilder.php`, `ReportCardPdfService.php`; `resources/views/pdf/report-card.blade.php`; tests
- Changes: snapshot JSON (final scores, attendance counts, school identity) at generate time; full A4 layout per PRD section 3 (header/logo, identity, grades + predikat, kehadiran + %, catatan wali kelas, keputusan, signatures, `{city}, {tanggal}` line, footer with WIB timestamp); blocked with Indonesian message when identity incomplete or logo missing.
- Verify: test generates real PDF asserting `%PDF` header + size; missing-identity blocked test; no-signature blank block test. Open one PDF manually for a visual check.

### Step 10. Lock, publish, and bulk PDF generation
- Touches: `app/Services/ReportCard/PublishService.php`, `app/Jobs/GenerateReportCardPdf.php`, tests
- Changes: lock blocks teacher edits; unlock requires reason + audit; publish blocked by incomplete identity or any missing component (listing students/subjects); bulk generation queued per student with pending/failed/done + retry; never overwrite a published PDF with a partial file; regenerate marks "Direvisi" and keeps previous version; identity changes never touch published snapshots.
- Verify: tests for every blocked/unblocked path in PRD section 5.

### Step 11. Promotion domain [MIGRATION]
- Touches: migrations `promotion_batches`, `promotion_decisions`; `app/Services/Promotion/PromotionService.php`; tests
- Changes: proposal at even-semester publish: retain if final < KKM in more than N subjects (N from settings), grade 12 -> graduate; default placement keeps cohorts (X-1 -> XI-1); admin per-student track (IPA/IPS) for grade 11; preview-only until confirm; rows overridable; apply blocked unless both semesters locked, no unassigned target class, batch idempotent by `status`; retained students re-enrolled same grade.
- Verify: retain threshold boundary (N vs N+1), double-apply no-op, unassigned-target block, override path.

## Phase 1 - Direction and UI

### Step 12. Design direction [NEEDS USER INPUT] (antislop R-37)
- Touches: `DESIGN.md` (new)
- Changes: 3-4 direction questions (identity feel, palette mood, typography, energy). User answers; agent only transcribes into `DESIGN.md` plus dials ENERGY/RHYTHM/MOTION. No code changes.
- Verify: `DESIGN.md` exists; suite green.

### Step 13. App shell: role-aware nav, shared props, base patterns
- Touches: `HandleInertiaRequests`, `AppSidebar.vue`, `Dashboard.vue`, new `DataTable`/`PageHeader`/`EmptyState` components, types, tests
- Changes: share `auth.user.role`, school name/logo; sidebar nav per role; starter-kit GitHub footer links removed; role dashboard stubs with empty states; Wayfinder regenerated.
- Verify: feature tests hit each role's dashboard (200, correct nav props); `npm run check && npm run types:check`; click-through per role.

### Step 14. Admin: master data CRUD (A1)
- Touches: `routes/web.php`, `app/Http/Controllers/Admin/Master/*`, form requests, policies, `resources/js/pages/admin/master/*`, audit writes, tests
- Changes: academic years/semesters (single-active guard), lesson periods, subjects + KKM, classrooms. Delete blocked with Indonesian explanation when dependents exist.
- Verify: CRUD tests + blocked-delete + single-active; click-through each entity.

### Step 15. Admin: accounts, parent linking, CSV/XLSX import (A2) [maatwebsite/excel real use]
- Touches: import controller + `app/Services/Import/UserImporter.php`, user CRUD controllers/pages, `resources/js/pages/admin/users/*`, tests + fixtures
- Changes: create students/teachers/parents; username rules NISN / NIP-NUPTK / `628...` phone; import validates every row with line-numbered error report, rejects duplicates, nothing silently overwritten; generated password shown once + `must_change_password`; link/unlink parents (unlink revokes access next request); admin password reset.
- Verify: import tests (bad rows, duplicates, mixed files), link/unlink access tests; upload a real Excel export and a CSV in the browser.

### Step 16. Admin: enrollment, teaching assignments, schedule builder (A3)
- Touches: `resources/js/pages/admin/schedule/*`, controllers, reuses step-6 service, tests
- Changes: assign students to classes (one per student per year), teacher-per-subject-class, weekly grid by lesson period with collision service rejecting double-booking.
- Verify: conflict tests surface inline Indonesian errors; build a week-long schedule by hand.

### Step 17. Admin: Pengaturan Sekolah (A6) + audit viewer
- Touches: `SchoolProfileController` + `resources/js/pages/admin/settings-school.vue`, `app/Services/SchoolIdentity/LogoProcessor.php` (GD), audit page, tests
- Changes: all A6 fields with required markers; logo PNG/JPG <= 2 MB min 200x200 auto-resized max 400 px, re-encoded, old file deleted; signature transparent PNG <= 1 MB; live preview of report card header + signature block; inline Indonesian validation; audit-logged.
- Verify: upload validation tests (type/size/dimensions/corrupt), audit rows, preview updates; try bad + good upload in browser.

### Step 18. Teacher: attendance UI (T1)
- Touches: `AttendanceController`, `resources/js/pages/teacher/attendance/*`, missing-session admin widget, tests
- Changes: today's scheduled periods, roster defaulting Hadir, Hadir/Sakit/Izin/Alpa/Terlambat controls, one-action save, edit window enforced, all three UI states.
- Verify: submit test creates session + rows + recompute + notification job; late edit blocked for teacher, allowed for admin; mobile-width click-through.

### Step 19. Teacher: grade input (T2) + homeroom (T3)
- Touches: `GradeController`, `HomeroomController`, `resources/js/pages/teacher/grades/*`, `.../homeroom/*`, tests
- Changes: grade grid per subject-class with component entries (assignment/quiz repeatable, midterm/final single), remark column, live final-score preview, stale-save conflict message; homeroom preview, catatan wali kelas (<= 300 chars), missing-component list, "ready" blocked while any missing.
- Verify: stale-save conflict test, missing-component block test; enter grades for a real class end to end.

### Step 20. Publish flow UI + downloads
- Touches: admin lock/unlock/publish pages, bulk generation status page with retry, student/parent PDF download routes, tests
- Changes: lock with reason, publish with blocked-state explanations, generation status list, "Direvisi" on regenerate, PDF download only after publish and only for own data.
- Verify: publish blocked tests; download authorization tests; click through publish -> download for one class.

### Step 21. Promotion UI (A5)
- Touches: admin promotion pages, tests
- Changes: preview table promote/retain/graduate with per-row override + IPA/IPS track choice, confirm dialog before apply, blocked-state messaging from step 11.
- Verify: override + apply tests; apply a batch on seeded data and confirm enrollments moved.

### Step 22. Student & parent read views (S1, P1, P2)
- Touches: `StudentController`/`GuardianController` read pages, children switcher, notifications bell + list, tests
- Changes: schedule, per-period + daily attendance history, grades, published report cards; parent switches children; unlinked student -> 403; in-app absence notifications.
- Verify: 403 tests for unlinked access, own-data-only tests, parent sees notification after a step-18 submit.

### Step 23. Role dashboards with real data + polish pass
- Touches: `Dashboard.vue` split per role, small CSS/copy fixes, tests
- Changes: admin (missing sessions, students below KKM, unpublished cards), teacher (today's periods, pending submissions), student/parent (latest report card). Numbers from real queries only. Sweep: empty/loading/error states, WCAG AA contrast, 44 px tap targets, keyboard nav + visible focus, Indonesian copy.
- Verify: `composer run lint`, `composer run types:check`, `npm run check`, `npm run types:check`, `npm run build`, `php artisan test --compact`; contrast check; record per-element click-through.

### Step 24. Delivery Gate + handover
- Touches: none (report only)
- Changes: full antislop Delivery Gate PASS/FAIL report, click-through evidence list, note to run the complete suite.
- Verify: every gate item PASS; if any FAIL, fix before declaring done.

---

Flags summary: migrations in steps 3, 4, 5, 7, 8, 9, 11 | new dependencies in step 1 | env change in step 2 | user input needed in step 12 | known risk in step 1 (maatwebsite/excel on Laravel 13, stop-and-ask fallback).
