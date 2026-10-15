# PRD: Academic Information System for SMA (SIAKAD-SMA)

**Status:** v0.4, ready for build (no open questions) | **Stack:** Laravel 12, Vue 3 + Inertia, Laravel Vue starter kit with shadcn-vue + Tailwind, **MySQL 8.0+ (InnoDB)** | **Roles:** Student, Teacher, Admin Staff, Parent
**Locale:** Indonesian-only UI (`app.locale=id`), timezone fixed to `Asia/Jakarta` (WIB). **Curriculum:** Kurikulum Merdeka.

## 1. Problem Statement & Success Metrics

**Problem.** Student data, grades and attendance live in spreadsheets and paper registers. Teachers re-type grades into report card templates, attendance is recorded on paper and re-entered by staff, and parents only learn of problems at report card time. Result: transcription errors, late report cards, no early warning for absent or failing students.

**Goal.** One system of record for academic master data, per-lesson attendance, grade input, report cards and year-end promotion, with read access for students and parents.

| Metric | Baseline (assumed) | v1 Target |
|---|---|---|
| Time to produce report cards for one class (~28 students) | ~1 day | < 15 min after grades locked |
| Lesson periods with digital attendance | 0% | ≥ 95% in first full semester |
| Teacher time to submit attendance for one period | ~5 min | < 60 sec |
| Grade rows corrected after report card publish | unknown | < 2% |
| Parents who viewed a published report card | 0% | ≥ 70% by end of semester 1 |

**Scale and load.** ~500 students, ~50 teachers, 18 classes (3 IPA + 3 IPS per grade level, ~28 students each; grade 10 has no track). Peak: Monday 07:00, up to ~50 teachers submit attendance within a few minutes. Target: attendance submit p95 < 1 s at 50 concurrent teachers on one VPS (2 vCPU / 4 GB), MySQL 8.0+ (InnoDB buffer pool ~1 GB), queue worker for PDFs and notifications, nightly DB backup.

## 2. User Stories & Acceptance Criteria

**Admin Staff**
- **A1.** Manage academic years, semesters, lesson periods, classes, subjects.
  - AC: Only one academic year/semester is `active`. Deleting a record with dependent grades/attendance is blocked with an explanation.
- **A2.** Create all accounts (students, teachers, parents) manually or via CSV/XLSX import, and link parents to students.
  - AC: Import validates every row with a per-row error report; duplicate NISN/NIP/username rejected. Usernames: NISN (students), NIP/NUPTK (teachers), phone number in `628xxxx` format (parents). Generated initial password shown once, must be changed on first login. No self-registration.
- **A3.** Assign students to classes, teachers to subject-class pairs, and build the weekly schedule per lesson period.
  - AC: One class per student per academic year. One teacher per subject-class per semester. A teacher cannot be scheduled in two classes in the same period.
- **A4.** Lock grades and publish report cards.
  - AC: Lock blocks all teacher grade edits. Unlock requires a reason and is audit-logged. Publish is blocked if school identity is incomplete (A6).
- **A5.** Run year-end promotion.
  - AC: Preview shows promote/retain/graduate per student; nothing changes until admin confirms; any row can be overridden before applying.
- **A6 (new). Manage school identity.** Page "Pengaturan Sekolah" (admin only).
  - Fields: school name*, NPSN, address*, city* (used in report card date line), postal code, phone, email, headmaster name*, headmaster NIP*, logo* (PNG/JPG, ≤ 2 MB, min 200×200 px, auto-resized to max 400 px), optional headmaster signature image (transparent PNG, ≤ 1 MB). (*required)
  - AC: Save validates required fields and file type/size, with inline Indonesian error messages. Page shows a live preview of the report card header and signature block. Changes are audit-logged. Changing identity never alters already-published report cards (each report card stores an identity snapshot). Logo replacement deletes the old file.

**Teacher**
- **T1.** Take attendance per lesson period for my scheduled class.
  - AC: List defaults all students to Present. Statuses: Present, Sick (Sakit), Permitted (Izin), Absent (Alpa), Late (Terlambat). One session per schedule slot per date, saved in one action. Editable by the submitting teacher until 23:59 the next day; later edits by admin only.
- **T2.** Enter scores per student per subject.
  - AC: Components: Assignments 30%, Quiz 20%, Midterm 20%, Final 30%. Assignments and quizzes may have multiple entries, averaged per component. Scores 0–100, max 2 decimals. Blank ≠ 0. Final score = weighted sum, computed server-side, rounded to integer.
- **T3.** Homeroom teacher: preview class report cards, write homeroom note, see missing grades.
  - AC: Cannot mark class "ready" while any student has a missing component.

**Student**
- **S1.** View schedule, my attendance, my grades and published report cards (read-only).
  - AC: Only my own data; PDF downloadable after publish only.

**Parent**
- **P1.** View each linked child's attendance, grades and report cards; switch between children.
  - AC: Unlinked student returns 403.
- **P2.** Receive an in-app notification when my child is marked Absent (Alpa).
  - AC: Appears within 5 minutes of submit. No email/WhatsApp in v1.

## 3. Scope

**Ships in v1**
- Role-based auth (Policies + Spatie Permission), forced first-login password change, admin-only password reset.
- Master data CRUD + CSV/XLSX import (no legacy migration). School identity settings (A6).
- Class enrollment, teaching assignments, lesson periods, weekly schedule.
- Per-lesson-period attendance with a derived **daily status** for reporting (rule below); student/parent read views; in-app absence notifications.
- Grade entry with fixed 30/20/20/30 weights, per-subject KKM (default 75), lock/unlock.
- Report card PDF (layout below), bulk generation per class, publish.
- **Promotion** (automatic proposal + manual override): at even-semester publish, a student is *retained* if final score < KKM in **more than 3 subjects** (N = 3, configurable), else *promoted*. Grade 10 → 11, 11 → 12, grade 12 → `graduated`. Default placement keeps cohorts together (X-1 → XI-1); grade 11 track (IPA/IPS) is chosen by admin per student. Admin confirms before apply.
- Audit log (grades, attendance, promotion, unlock, settings). Role dashboards.

**Does not ship in v1**
- Native mobile app, QR/face/geo attendance; email/WhatsApp/SMS; knowledge/skills split grades, attitude grades, P5, extracurricular, counseling.
- Fees, library, LMS, online exams; Dapodik/e-Rapor sync; multi-school; English UI; legacy data import; data-retention/privacy tooling.

**Daily attendance rule (derived from period data, used on report card).** A school day = a date with ≥ 1 submitted session for the student's class. The student's day status is taken from the **first recorded period of that day** (the earliest lesson period with a submitted session): Sakit, Izin or Alpa → that status; Late → Hadir with a **Terlambat** flag; Present → Hadir. Later periods never change the daily status (they stay visible in per-period history). Admin can override a day; overrides are audit-logged.

### Report Card Layout (A4 portrait, one page per student)
1. **Header:** logo (left), school name, address, phone/email (from A6), "LAPORAN HASIL BELAJAR" centered bold, thin rule.
2. **Identity block:** Nama, NISN, Kelas | Semester, Tahun Ajaran, Wali Kelas.
3. **Grades table:** No | Mata Pelajaran | Nilai Akhir | Predikat | Keterangan. Predikat: A ≥ 90, B 80–89, C 75–79 (KKM to 79), D < KKM. Keterangan = optional one-line teacher remark. Footer rows: Jumlah, Rata-rata.
4. **Kehadiran (in days):** Sakit, Izin, Alpa, Terlambat, and attendance % = Hadir days / school days.
5. **Catatan Wali Kelas:** boxed text, max 300 chars.
6. **Keputusan:** even-semester card only: "Naik ke kelas XI" / "Tinggal di kelas X" / "Lulus".
7. **Signatures:** Orang Tua/Wali (blank), Wali Kelas (name), Kepala Sekolah (name + NIP, signature image if uploaded); date line = "{city}, {publish date in Indonesian}".
8. **Footer:** "Dokumen digital dari SIAKAD-SMA", generated timestamp (WIB).

## 4. Data Model (new tables; `id` + timestamps + soft deletes unless noted)

- `users`: name, username (unique), email nullable, password, `role` enum(student, teacher, admin, parent), `is_active`, `must_change_password`.
- `academic_years`: name, start_date, end_date, `is_active`. `semesters`: academic_year_id, name (Ganjil/Genap), `is_active`, `grades_locked_at`, `report_published_at`.
- `students`: user_id, nisn (unique), nis, full_name, gender, birth_date, birth_place, address, status enum(active, graduated, transferred, dropped).
- `teachers`: user_id, nip/nuptk (unique), full_name, phone. `guardians`: user_id, full_name, phone. `guardian_student`: guardian_id, student_id, relationship; unique pair.
- `classrooms`: academic_year_id, name, grade_level (10/11/12), `major` nullable enum(IPA, IPS), homeroom_teacher_id.
- `class_enrollments`: classroom_id, student_id, academic_year_id; unique (student_id, academic_year_id). Track for grade 11/12 is read from the classroom.
- `subjects`: code (unique), name, kkm default 75.
- `teaching_assignments`: semester_id, classroom_id, subject_id, teacher_id; unique (semester_id, classroom_id, subject_id).
- `lesson_periods`: number, start_time, end_time. `schedules`: teaching_assignment_id, day_of_week, lesson_period_id; unique triple.
- `attendance_sessions`: schedule_id, date, submitted_by, submitted_at; unique (schedule_id, date). `attendances`: attendance_session_id, student_id, status enum, note; unique pair.
- `daily_attendances`: student_id, date, status enum(present, sick, permitted, absent), is_late, is_overridden, computed_at; unique (student_id, date). Recomputed by queued job on each session submit/edit.
- `grade_entries`: teaching_assignment_id, student_id, component enum(assignment, quiz, midterm, final), title, score decimal(5,2) nullable. Midterm/final: max one row per student per assignment.
- `grade_remarks`: teaching_assignment_id, student_id, remark; unique pair.
- `report_cards`: student_id, semester_id, homeroom_note, promotion_outcome nullable enum, pdf_path, snapshot JSON (final scores, daily attendance counts, **school identity**), generated_at, published_at; unique (student_id, semester_id).
- `promotion_batches`: from_academic_year_id, to_academic_year_id, status enum(draft, applied), applied_by, applied_at. `promotion_decisions`: batch_id, student_id, from_classroom_id, to_classroom_id nullable, outcome enum(promote, retain, graduate), is_overridden.
- `school_profiles` (single row): name, npsn, address, city, postal_code, phone, email, headmaster_name, headmaster_nip, logo_path, headmaster_signature_path, updated_by. `academic_settings` (key/value): predicate bands, promotion_max_failed_subjects (3), default_kkm (75).
- `notifications` (Laravel default, database channel). `audit_logs`: user_id, auditable_type, auditable_id, action, old_values JSON, new_values JSON.

Indexes: attendances(student_id), daily_attendances(student_id, date), grade_entries(student_id, teaching_assignment_id), class_enrollments(classroom_id).

**MySQL 8 specifics**
- **Engine/charset:** InnoDB, `utf8mb4` / `utf8mb4_unicode_ci`. `.env`: `DB_CONNECTION=mysql`; Laravel `app.timezone=Asia/Jakarta` and MySQL connection `timezone='+07:00'`.
- **Types:** `BIGINT UNSIGNED` PKs/FKs; scores `DECIMAL(5,2)`; attendance `date` columns use `DATE` (no timezone ambiguity); `snapshot` and audit `old_values`/`new_values` use native `JSON`.
- **Enums:** every "enum" above is a `VARCHAR(20)` column cast to a PHP backed enum in Eloquent (not MySQL `ENUM`), so adding a value needs no `ALTER TABLE`.
- **Foreign keys:** all relations have real FK constraints. Academic records (grades, attendance, enrollments, report cards) use `ON DELETE RESTRICT`; only pure link rows (`guardian_student`) cascade.
- **Unique keys + soft deletes:** unique indexes (nisn, nip, username, the composite keys above) are global and include soft-deleted rows. To reuse an identifier, admin restores the soft-deleted record instead of creating a duplicate.
- **Upserts:** attendance and grade saves use Eloquent `upsert()` (`INSERT ... ON DUPLICATE KEY UPDATE`) against the unique indexes, inside one transaction (default `REPEATABLE READ`).
- **Queue/cache/session:** `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`, `CACHE_STORE=database` are sufficient at this scale (no Redis required in v1).
- **Backup:** nightly `mysqldump --single-transaction` of the application database plus the uploaded-files directory (logos, signatures, report card PDFs), copied off the VPS; restore procedure documented and tested before go-live.
- **Seeders:** default roles/permissions, one initial admin user, the 10 lesson periods, default `academic_settings`.

## 5. Edge Cases & Failure States

- **Double submit / two devices on one session:** unique (schedule_id, date) + upsert in one transaction; last write wins; both logged.
- **Teacher absent / substitute:** admin can submit or reassign a session; `submitted_by` records who.
- **Unscheduled slot or holiday:** blocked; admin can create an ad-hoc session.
- **First period not recorded (teacher forgot):** daily status falls back to the earliest recorded period and is recomputed if the missing session is submitted later; admin dashboard lists days/classes with missing sessions.
- **Student skips a later lesson (present in period 1):** day stays Hadir; visible in per-period history and parent view. **Student absent in period 1 but arrives later:** teacher should mark Late; otherwise the day counts as absent until admin overrides.
- **Student transfers mid-semester:** history kept; status `transferred`; excluded from new sessions and bulk reports; partial report card can be generated manually.
- **Missing component at publish:** that student's PDF fails listing missing subjects/components; others still generate.
- **Identity incomplete or logo file missing on disk:** publish and PDF generation blocked with a clear message pointing to Pengaturan Sekolah; no PDF with a broken header.
- **Invalid logo upload (wrong type, > 2 MB, corrupt, SVG with script):** rejected; only PNG/JPG accepted, re-encoded server-side; previous logo stays.
- **Identity changed after publish:** published PDFs unchanged (snapshot); regenerated/revised cards use the new identity.
- **Weights/KKM changed after scores exist:** recompute and audit-log; blocked after lock.
- **Edit after publish:** admin unlock + reason → regenerate PDF, keep previous version, mark "Direvisi"; promotion outcome re-evaluated and flagged for admin review if it changes.
- **Bulk PDF failure/timeout:** queued per student, status pending/failed/done, retry per student; never overwrite a published PDF with a partial file.
- **Promotion applied twice or before both semesters locked:** blocked; batch idempotent via `status`. Rows with no target class flagged; apply blocked until assigned. Retained students re-enrolled at the same grade level.
- **Parents sharing a phone number:** username must be unique; admin links both parents to one account or gives the second a distinct number. One parent account can link many children.
- **Parent linked to wrong child:** admin unlinks; access revoked on next request (permission not cached).
- **Import errors:** row-level errors with line number; nothing silently overwritten.
- **Lost password:** admin resets and forces change on next login.
- **Concurrent grade edits:** optimistic check on `updated_at`; second saver gets a conflict message with the current value.
- **Peak load:** attendance saved as one bulk upsert per session; notifications and daily recompute queued so submit never blocks.
- **Authorization:** teachers see only their teaching assignments (homeroom teachers also their class); enforced via Policies with feature tests per role.

## 6. Open Questions

None. Resolved: daily attendance is decided by the first recorded period of the day; the headmaster signature is an optional uploaded image; all parents have a phone number (used as username).
