# DESIGN.md

Design direction for SIAKAD-SMA.
Authored by the product owner through four direction questions (identity feel, palette mood, typography, energy); the agent only transcribes and formats.
antislop applies on top of this direction as a filter, not a source of style (R-37).

## Identity feel

Hybrid: formal screens, warm dashboards.

- **Formal screens** (report cards, academic records, transcripts, printable documents, master data forms): official, orderly, document-like. The screen should feel like school letterhead and academic transcripts translated to the browser.
- **Warm dashboards** (role dashboards, student/parent read views, attendance and grade summaries): warmer and more visual, friendly enough for teachers, students and parents who read but rarely configure.

The personality school staff will sense every day: institutional where records are at stake, approachable where people only read.

## Palette mood

- **Core:** forest green (growth and education; fresh but still institutional).
- **Accent:** warm amber (single accent, used sparingly at key moments).
- **Base:** neutral greys and white (excluded from the core-color count per R-29).

Constraints carried with this choice:

- Exact values are chosen at build time within these families, and every text/background pair must pass WCAG AA (R-25).
- One deliberate accent only: amber marks the key action or highlight per screen, never everything at once.
- Maximum 2-3 core colors plus this accent (R-29).

## Typography

- **Headings:** serif.
  Reason: serif headings evoke diplomas and academic documents, matching the formal-screen identity.
- **Body:** sans-serif.
  Reason: keeps tables, forms and dense data views readable.

Exact families are chosen at build time and recorded with a one-line reason (R-06). Tabular data must stay aligned (grade and attendance numbers).

## Dials

| Dial   | Value        | Meaning for this product                                                                               |
| ------ | ------------ | ------------------------------------------------------------------------------------------------------ |
| ENERGY | 2 (Balanced) | Clear focal point per screen; the design says hello through hierarchy, not volume.                     |
| RHYTHM | 2 (Balanced) | Predictable structure with a few deliberate breaks (dashboards may vary; record screens stay uniform). |
| MOTION | 1 (Calm)     | Hover states and light transitions only; motion confirms actions, it never performs.                   |

Every screen holds these three values from first section to last.

## Decision log

One-line reasons for the four direction choices, as answered by the owner:

- Hybrid identity: records must read as official documents; read views must stay approachable for non-admin users.
- Forest green + warm amber: growth and education core color, one warm accent for highlights, neutral base.
- Serif headings + sans body: academic authority in headings, data readability in body.
- ENERGY 2 / RHYTHM 2 / MOTION 1: a back-office tool should be efficient and calm, not loud.
