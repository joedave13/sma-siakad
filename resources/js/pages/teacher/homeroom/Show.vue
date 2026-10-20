<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import AlertError from '@/components/AlertError.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import homeroomNotesRoutes from '@/routes/teacher/homeroom/notes';
import homeroomRoutes from '@/routes/teacher/homeroom';
import type { HomeroomShowProps } from '@/types/grades';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Wali Kelas',
                href: homeroomRoutes.index(),
            },
        ],
    },
});

type Props = HomeroomShowProps;

const props = defineProps<Props>();

const page = usePage();

const notes = reactive<Record<number, string>>({});
for (const student of props.students) {
    notes[student.id] = student.homeroom_note ?? '';
}

const noteForm = useForm<{
    student_id: number;
    note: string;
}>({
    student_id: 0,
    note: '',
});

const readyForm = useForm<Record<string, never>>({});

const noteErrors = computed(() => readErrors('note'));
const readyErrors = computed(() => readErrors('ready'));

function readErrors(key: string): string[] {
    const errors = (page.props.errors ?? {}) as Record<
        string,
        string | string[]
    >;
    const raw = errors[key];

    if (raw === undefined) {
        return [];
    }

    return Array.isArray(raw) ? raw : [raw];
}

const missingStudents = computed(() =>
    props.students.filter((student) => student.missing.length > 0),
);

function saveNote(studentId: number): void {
    noteForm.student_id = studentId;
    noteForm.note = notes[studentId] ?? '';
    noteForm.post(homeroomNotesRoutes.store.url(props.classroom.id), {
        preserveScroll: true,
    });
}

function markReady(): void {
    readyForm.post(homeroomRoutes.ready.url(props.classroom.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Wali Kelas ${props.classroom.name}`" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            :title="props.classroom.name"
            :description="
                `Kelas ${props.classroom.grade_level}` +
                (props.semester
                    ? ` · ${props.semester.name} ${props.semester.academic_year}`
                    : '')
            "
        >
            <template #actions>
                <Button
                    as-child
                    variant="outline"
                    size="sm"
                    class="min-h-11 md:min-h-0"
                >
                    <Link :href="homeroomRoutes.index()">
                        Kembali ke daftar
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="props.semester === null"
            data-test="homeroom-show-no-semester"
            class="rounded-md border p-6"
        >
            <EmptyState
                title="Belum ada semester aktif"
                description="Admin perlu mengaktifkan semester terlebih dahulu sebelum catatan wali kelas dan kesiapan rapor dapat diisi."
            />
        </div>

        <div
            v-else-if="props.students.length === 0"
            data-test="homeroom-students-empty"
            class="rounded-md border p-6"
        >
            <EmptyState
                title="Tidak ada siswa"
                description="Belum ada siswa aktif yang terdaftar di kelas ini. Hubungi admin untuk memeriksa penempatan siswa."
            />
        </div>

        <template v-else>
            <AlertError
                v-if="noteErrors.length > 0"
                class="mb-4"
                :errors="noteErrors"
                title="Catatan gagal disimpan."
                data-test="homeroom-note-error"
            />

            <AlertError
                v-if="readyErrors.length > 0"
                class="mb-4"
                :errors="readyErrors"
                title="Kelas gagal ditandai siap."
                data-test="homeroom-ready-error"
            />

            <div class="mb-4 flex flex-col gap-3 rounded-md border p-4">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-medium">Kesiapan rapor</p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                missingStudents.length > 0
                                    ? `${missingStudents.length} siswa masih memiliki nilai yang belum lengkap.`
                                    : 'Semua siswa memiliki nilai lengkap.'
                            }}
                        </p>
                    </div>
                    <Badge v-if="props.ready" data-test="homeroom-ready-badge">
                        Ditandai siap
                    </Badge>
                    <Button
                        v-else
                        type="button"
                        size="sm"
                        class="min-h-11 md:min-h-0"
                        :disabled="
                            missingStudents.length > 0 || readyForm.processing
                        "
                        data-test="homeroom-ready"
                        @click="markReady"
                    >
                        {{
                            readyForm.processing ? 'Menyimpan…' : 'Tandai Siap'
                        }}
                    </Button>
                </div>

                <Alert
                    v-if="!props.ready && missingStudents.length > 0"
                    data-test="homeroom-ready-blocked"
                >
                    <AlertTitle>Nilai belum lengkap</AlertTitle>
                    <AlertDescription>
                        Penandaan siap dinonaktifkan sampai semua siswa memiliki
                        nilai lengkap di setiap mata pelajaran.
                    </AlertDescription>
                </Alert>
            </div>

            <ul class="space-y-3" data-test="homeroom-students">
                <li
                    v-for="student in props.students"
                    :key="student.id"
                    class="space-y-3 rounded-md border p-4"
                    :data-test="`homeroom-student-${student.id}`"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            {{ student.full_name }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            NISN {{ student.nisn }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-1.5">
                        <template
                            v-for="grade in student.grades"
                            :key="grade.subject"
                        >
                            <Badge
                                v-if="grade.score !== null"
                                :variant="grade.passed ? 'default' : 'outline'"
                            >
                                {{ grade.subject }}: {{ grade.score }} ({{
                                    grade.predicate
                                }})
                            </Badge>
                            <Badge v-else variant="outline">
                                {{ grade.subject }}: -
                            </Badge>
                        </template>
                        <Badge
                            v-for="item in student.missing"
                            :key="`missing-${item}`"
                            variant="destructive"
                            :data-test="`homeroom-missing-${student.id}`"
                        >
                            Belum: {{ item }}
                        </Badge>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            :for="`homeroom-note-${student.id}`"
                            class="text-sm font-medium"
                        >
                            Catatan wali kelas
                        </label>
                        <textarea
                            :id="`homeroom-note-${student.id}`"
                            v-model="notes[student.id]"
                            rows="2"
                            maxlength="300"
                            placeholder="Catatan untuk siswa ini (opsional)."
                            class="flex min-h-11 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:min-h-0"
                            :data-test="`homeroom-note-${student.id}`"
                        ></textarea>
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <span
                                class="text-xs text-muted-foreground"
                                :data-test="`homeroom-note-count-${student.id}`"
                            >
                                {{ (notes[student.id] ?? '').length }}/300
                            </span>
                            <Button
                                type="button"
                                size="sm"
                                class="min-h-11 md:min-h-0"
                                :disabled="
                                    noteForm.processing &&
                                    noteForm.student_id === student.id
                                "
                                :data-test="`homeroom-note-save-${student.id}`"
                                @click="saveNote(student.id)"
                            >
                                {{
                                    noteForm.processing &&
                                    noteForm.student_id === student.id
                                        ? 'Menyimpan…'
                                        : 'Simpan Catatan'
                                }}
                            </Button>
                        </div>
                    </div>
                </li>
            </ul>
        </template>
    </div>
</template>
