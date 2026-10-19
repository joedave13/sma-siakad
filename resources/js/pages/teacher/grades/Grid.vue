<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import AlertError from '@/components/AlertError.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import gradesRoutes from '@/routes/teacher/grades';
import type {
    GradeCellState,
    GradeColumn,
    GradeComponentValue,
    GradeEntryPayload,
    GradeGridProps,
    GradeRemarkPayload,
} from '@/types/grades';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Nilai',
                href: gradesRoutes.index(),
            },
        ],
    },
});

type Props = GradeGridProps;

const props = defineProps<Props>();

const page = usePage();

const componentLabels: Record<GradeComponentValue, string> = {
    assignment: 'Tugas',
    quiz: 'Quiz',
    midterm: 'UTS',
    final: 'UAS',
};

const weights: Record<GradeComponentValue, number> = {
    assignment: 0.3,
    quiz: 0.2,
    midterm: 0.2,
    final: 0.3,
};

function buildColumns(): GradeColumn[] {
    const columns: GradeColumn[] = [];

    for (const title of props.columns.assignment) {
        columns.push({
            component: 'assignment',
            title,
            key: `assignment|${title}`,
        });
    }
    for (const title of props.columns.quiz) {
        columns.push({ component: 'quiz', title, key: `quiz|${title}` });
    }

    columns.push({ component: 'midterm', title: null, key: 'midterm|' });
    columns.push({ component: 'final', title: null, key: 'final|' });

    return columns;
}

const columns = ref<GradeColumn[]>(buildColumns());

const scores = reactive<Record<number, Record<string, GradeCellState>>>({});
const remarks = reactive<Record<number, string>>({});
const originalRemarks: Record<number, string | null> = {};

function hydrateScores(students: Props['students']): void {
    for (const student of students) {
        const cells: Record<string, GradeCellState> = {};

        for (const column of columns.value) {
            const match = student.cells.find(
                (cell) =>
                    cell.component === column.component &&
                    (cell.title ?? '') === (column.title ?? ''),
            );
            cells[column.key] = {
                score: match?.score ?? '',
                expected: match?.updated_at ?? null,
            };
        }

        scores[student.id] = cells;
        remarks[student.id] = student.remark ?? '';
        originalRemarks[student.id] = student.remark;
    }
}

hydrateScores(props.students);

watch(
    () => props.students,
    (students) => hydrateScores(students),
);

const gridErrors = computed(() => {
    const errors = (page.props.errors ?? {}) as Record<
        string,
        string | string[]
    >;
    const raw = errors.grades;

    if (raw === undefined) {
        return [];
    }

    return Array.isArray(raw) ? raw : [raw];
});

const form = useForm<{
    entries: GradeEntryPayload[];
    remarks: GradeRemarkPayload[];
}>({
    entries: [],
    remarks: [],
});

function columnLabel(column: GradeColumn): string {
    return column.title ?? componentLabels[column.component];
}

function addColumn(component: 'assignment' | 'quiz'): void {
    const used = new Set(
        columns.value
            .filter((column) => column.component === component)
            .map((column) => column.title),
    );
    const base = componentLabels[component];

    let title = `${base} 1`;
    let number = 2;
    while (used.has(title)) {
        title = `${base} ${number}`;
        number += 1;
    }

    const key = `${component}|${title}`;
    columns.value.push({ component, title, key });

    for (const student of props.students) {
        scores[student.id][key] = { score: '', expected: null };
    }
}

function finalPreview(studentId: number): number | null {
    const perComponent: Record<GradeComponentValue, number[]> = {
        assignment: [],
        quiz: [],
        midterm: [],
        final: [],
    };

    for (const column of columns.value) {
        const raw = scores[studentId][column.key]?.score ?? '';
        if (raw.trim() === '') {
            continue;
        }

        const value = Number(raw);
        if (Number.isNaN(value)) {
            continue;
        }

        perComponent[column.component].push(value);
    }

    let weighted = 0;
    for (const component of [
        'assignment',
        'quiz',
        'midterm',
        'final',
    ] as GradeComponentValue[]) {
        const values = perComponent[component];
        if (values.length === 0) {
            return null;
        }

        const average =
            values.reduce((sum, value) => sum + value, 0) / values.length;
        weighted += average * weights[component];
    }

    return Math.round(weighted);
}

function changedRemarks(): GradeRemarkPayload[] {
    const payload: GradeRemarkPayload[] = [];

    for (const student of props.students) {
        const value = remarks[student.id] ?? '';
        const normalized = value === '' ? null : value;

        if (normalized !== (originalRemarks[student.id] ?? null)) {
            payload.push({ student_id: student.id, remark: normalized });
        }
    }

    return payload;
}

function save(): void {
    const entries: GradeEntryPayload[] = [];

    for (const student of props.students) {
        for (const column of columns.value) {
            const cell = scores[student.id][column.key];
            const trimmed = cell.score.trim();

            entries.push({
                student_id: student.id,
                component: column.component,
                title: column.title,
                score: trimmed === '' ? null : trimmed,
                expected_updated_at: cell.expected,
            });
        }
    }

    form.entries = entries;
    form.remarks = changedRemarks();
    form.post(gradesRoutes.store.url(props.assignment.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Nilai ${props.assignment.subject}`" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            :title="`Nilai ${props.assignment.subject}`"
            :description="`${props.assignment.classroom} · KKM ${props.assignment.kkm} · ${props.semester.name} ${props.semester.academic_year}`"
        >
            <template #actions>
                <Button
                    as-child
                    variant="outline"
                    size="sm"
                    class="min-h-11 md:min-h-0"
                >
                    <Link :href="gradesRoutes.index()">
                        Kembali ke daftar
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <p
            class="mb-4 text-sm text-muted-foreground"
            data-test="grades-weights"
        >
            Bobot nilai akhir: Tugas 30% · Quiz 20% · UTS 20% · UAS 30%.
        </p>

        <Alert
            v-if="props.semester.locked"
            class="mb-4"
            data-test="grades-lock"
        >
            <AlertTitle>Semester terkunci</AlertTitle>
            <AlertDescription>
                Nilai tidak dapat diubah karena semester telah dikunci.
            </AlertDescription>
        </Alert>

        <AlertError
            v-if="gridErrors.length > 0"
            class="mb-4"
            :errors="gridErrors"
            title="Nilai gagal disimpan."
            data-test="grades-error"
        />

        <div
            v-if="props.students.length === 0"
            data-test="grades-roster-empty"
            class="rounded-md border p-6"
        >
            <EmptyState
                title="Tidak ada siswa"
                description="Belum ada siswa aktif yang terdaftar di kelas ini. Hubungi admin untuk memeriksa penempatan siswa."
            />
        </div>

        <template v-else>
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm text-muted-foreground">
                    {{ props.students.length }} siswa
                </p>
                <div v-if="!props.semester.locked" class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="min-h-11 md:min-h-0"
                        data-test="grades-add-assignment"
                        @click="addColumn('assignment')"
                    >
                        Tambah Tugas
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="min-h-11 md:min-h-0"
                        data-test="grades-add-quiz"
                        @click="addColumn('quiz')"
                    >
                        Tambah Quiz
                    </Button>
                </div>
            </div>

            <p
                v-if="!props.semester.locked"
                class="mb-3 text-xs text-muted-foreground"
                data-test="grades-add-hint"
            >
                Kolom baru hanya tersimpan setelah minimal satu nilai diisi.
            </p>

            <div
                class="overflow-x-auto rounded-md border"
                data-test="grade-grid"
            >
                <table class="w-full min-w-max border-collapse text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50">
                            <th
                                class="sticky left-0 z-10 min-w-44 border-r bg-background px-3 py-2 text-left font-medium"
                            >
                                Nama
                            </th>
                            <th
                                v-for="column in columns"
                                :key="column.key"
                                class="px-3 py-2 text-center font-medium whitespace-nowrap"
                                :data-test="`grades-col-${column.key}`"
                            >
                                {{ columnLabel(column) }}
                            </th>
                            <th
                                class="px-3 py-2 text-center font-medium whitespace-nowrap"
                                data-test="grades-col-final"
                            >
                                Nilai Akhir
                            </th>
                            <th
                                class="min-w-52 px-3 py-2 text-left font-medium whitespace-nowrap"
                            >
                                Keterangan
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="student in props.students"
                            :key="student.id"
                            :data-test="`grade-row-${student.id}`"
                        >
                            <th
                                scope="row"
                                class="sticky left-0 z-10 border-r bg-background px-3 py-2 text-left font-normal"
                            >
                                <span class="block truncate font-medium">
                                    {{ student.full_name }}
                                </span>
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                >
                                    NISN {{ student.nisn }}
                                </span>
                            </th>
                            <td
                                v-for="column in columns"
                                :key="column.key"
                                class="px-2 py-1.5"
                            >
                                <input
                                    v-model="
                                        scores[student.id][column.key].score
                                    "
                                    type="text"
                                    inputmode="decimal"
                                    maxlength="16"
                                    class="h-11 w-24 rounded-md border bg-transparent px-2 text-center text-base md:h-9 md:w-20 md:text-sm"
                                    :aria-label="`Nilai ${columnLabel(column)} ${student.full_name}`"
                                    :disabled="props.semester.locked"
                                    :data-test="`grade-cell-${student.id}-${column.key}`"
                                />
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span
                                    class="font-medium"
                                    :data-test="`grade-final-${student.id}`"
                                >
                                    {{ finalPreview(student.id) ?? '—' }}
                                </span>
                            </td>
                            <td class="px-2 py-1.5">
                                <input
                                    v-model="remarks[student.id]"
                                    type="text"
                                    maxlength="255"
                                    class="h-11 w-full rounded-md border bg-transparent px-2 text-sm md:h-9"
                                    :aria-label="`Keterangan ${student.full_name}`"
                                    :disabled="props.semester.locked"
                                    :data-test="`grade-remark-${student.id}`"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="!props.semester.locked" class="mt-4 flex justify-end">
                <Button
                    type="button"
                    data-test="grades-save"
                    :disabled="form.processing"
                    @click="save"
                >
                    {{ form.processing ? 'Menyimpan…' : 'Simpan Nilai' }}
                </Button>
            </div>
        </template>
    </div>
</template>
