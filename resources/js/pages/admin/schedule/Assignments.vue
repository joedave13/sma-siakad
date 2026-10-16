<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Plus } from '@lucide/vue';
import TeachingAssignmentController from '@/actions/App/Http/Controllers/Admin/Schedule/TeachingAssignmentController';
import classroomsRoutes from '@/routes/admin/master/classrooms';
import semestersRoutes from '@/routes/admin/master/semesters';
import subjectsRoutes from '@/routes/admin/master/subjects';
import teachersRoutes from '@/routes/admin/users/teachers';
import assignmentsRoutes from '@/routes/admin/schedule/assignments';
import AlertError from '@/components/AlertError.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionTabs from '@/components/SectionTabs.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { scheduleTabs } from './tabs';
import type {
    ActiveSemesterContext,
    ScheduleClassroomOption,
    SubjectOption,
    TeachingAssignmentRecord,
} from '@/types/schedule';
import type { TeacherOption } from '@/types/master';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Penempatan & Jadwal',
                href: assignmentsRoutes.index(),
            },
        ],
    },
});

type Props = {
    semester: ActiveSemesterContext | null;
    classrooms: ScheduleClassroomOption[];
    subjects: SubjectOption[];
    teachers: TeacherOption[];
    assignments: TeachingAssignmentRecord[];
    selectedClassroom: number | null;
};

const props = defineProps<Props>();

const page = usePage();
const deleteErrors = computed(() => {
    const errors = (page.props.errors ?? {}) as Record<string, string>;

    return errors.delete ? [errors.delete] : [];
});

const classroomAssignments = computed(() =>
    props.assignments.filter(
        (assignment) => assignment.classroom_id === props.selectedClassroom,
    ),
);

const selectedClassroomName = computed(
    () =>
        props.classrooms.find(
            (classroom) => classroom.id === props.selectedClassroom,
        )?.name ?? '',
);

const columns = [
    { key: 'subject_name', label: 'Mata Pelajaran' },
    { key: 'teacher_name', label: 'Guru' },
    { key: 'actions', label: '', className: 'w-28 text-right' },
];

function selectClassroom(event: Event): void {
    const classroomId = Number((event.target as HTMLSelectElement).value);

    router.get(
        assignmentsRoutes.index(),
        { classroom: classroomId },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <Head title="Penugasan Mengajar" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            title="Penugasan Mengajar"
            description="Tugaskan guru ke mata pelajaran tiap ruang kelas pada semester berjalan."
        />

        <SectionTabs :tabs="scheduleTabs" label="Penempatan dan jadwal" />

        <AlertError
            v-if="deleteErrors.length > 0"
            :errors="deleteErrors"
            title="Data tidak dapat dihapus"
            class="mb-4"
        />

        <EmptyState
            v-if="!semester"
            title="Belum ada semester aktif"
            description="Tentukan satu semester aktif agar penugasan mengajar dapat dibuat."
        >
            <template #actions>
                <Button size="sm" as-child class="min-h-11 md:min-h-0">
                    <Link :href="semestersRoutes.index()">Kelola semester</Link>
                </Button>
            </template>
        </EmptyState>

        <EmptyState
            v-else-if="classrooms.length === 0"
            title="Belum ada ruang kelas"
            description="Buat ruang kelas pada tahun ajaran ini sebelum menugaskan guru."
        >
            <template #actions>
                <Button size="sm" as-child class="min-h-11 md:min-h-0">
                    <Link :href="classroomsRoutes.index()"
                        >Kelola ruang kelas</Link
                    >
                </Button>
            </template>
        </EmptyState>

        <template v-else>
            <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div class="grid gap-2">
                    <Label for="assignments-classroom">Ruang kelas</Label>
                    <select
                        id="assignments-classroom"
                        :value="selectedClassroom ?? ''"
                        class="flex h-9 w-full min-w-56 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        data-test="assignments-classroom"
                        @change="selectClassroom"
                    >
                        <option
                            v-for="classroom in classrooms"
                            :key="classroom.id"
                            :value="classroom.id"
                        >
                            {{ classroom.name }}
                        </option>
                    </select>
                </div>
                <p class="text-sm text-muted-foreground">
                    Semester {{ semester.name }} {{ semester.academic_year }}
                </p>
            </div>

            <Form
                v-bind="assignmentsRoutes.store.form()"
                :options="{ preserveScroll: true }"
                class="mb-6 flex flex-wrap items-end gap-3"
                data-test="assignment-form"
                v-slot="{ errors, processing }"
            >
                <input type="hidden" name="semester_id" :value="semester.id" />
                <input
                    type="hidden"
                    name="classroom_id"
                    :value="selectedClassroom"
                />
                <div class="grid min-w-56 flex-1 gap-2">
                    <Label for="assignment-subject">Mata pelajaran</Label>
                    <select
                        id="assignment-subject"
                        name="subject_id"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="subjects.length === 0"
                        required
                        data-test="assignment-subject"
                    >
                        <option value="" disabled>Pilih mata pelajaran</option>
                        <option
                            v-for="subject in subjects"
                            :key="subject.id"
                            :value="subject.id"
                        >
                            {{ subject.name }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="errors.subject_id" />
                </div>
                <div class="grid min-w-56 flex-1 gap-2">
                    <Label for="assignment-teacher">Guru</Label>
                    <select
                        id="assignment-teacher"
                        name="teacher_id"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="teachers.length === 0"
                        required
                        data-test="assignment-teacher"
                    >
                        <option value="" disabled>Pilih guru</option>
                        <option
                            v-for="teacher in teachers"
                            :key="teacher.id"
                            :value="teacher.id"
                        >
                            {{ teacher.full_name }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="errors.teacher_id" />
                </div>
                <Button
                    type="submit"
                    :disabled="
                        processing ||
                        subjects.length === 0 ||
                        teachers.length === 0
                    "
                    class="min-h-11 md:min-h-0"
                    data-test="confirm-assignment"
                >
                    <Plus />
                    Tambah
                </Button>
            </Form>

            <div
                v-if="subjects.length === 0 || teachers.length === 0"
                class="mb-6 flex flex-wrap gap-x-1 gap-y-0.5 text-sm text-muted-foreground"
            >
                <span v-if="subjects.length === 0">
                    Belum ada mata pelajaran.
                    <Link
                        :href="subjectsRoutes.index()"
                        class="underline underline-offset-4 hover:text-foreground"
                        >Kelola mata pelajaran</Link
                    >
                </span>
                <span v-if="teachers.length === 0">
                    Belum ada guru.
                    <Link
                        :href="teachersRoutes.index()"
                        class="underline underline-offset-4 hover:text-foreground"
                        >Kelola guru</Link
                    >
                </span>
            </div>

            <DataTable
                :columns="columns"
                :rows="classroomAssignments"
                :row-key="
                    (assignment: TeachingAssignmentRecord) => assignment.id
                "
                :empty-title="`Belum ada penugasan di kelas ${selectedClassroomName}`"
                empty-description="Tambahkan penugasan mata pelajaran untuk kelas ini lewat formulir di atas."
            >
                <template #cell="{ row, column }">
                    <template v-if="column.key === 'subject_name'">
                        {{ row.subject_name }}
                    </template>
                    <template v-else-if="column.key === 'teacher_name'">
                        {{ row.teacher_name }}
                    </template>
                    <div
                        v-else-if="column.key === 'actions'"
                        class="flex justify-end"
                    >
                        <Dialog>
                            <DialogTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="min-h-11 text-destructive hover:text-destructive md:min-h-0"
                                    data-test="delete-assignment"
                                >
                                    Hapus
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <Form
                                    v-bind="
                                        TeachingAssignmentController.destroy.form(
                                            {
                                                teachingAssignment: row.id,
                                            },
                                        )
                                    "
                                    :options="{ preserveScroll: true }"
                                    class="space-y-6"
                                    v-slot="{ processing }"
                                >
                                    <DialogHeader>
                                        <DialogTitle>
                                            Hapus penugasan mengajar?
                                        </DialogTitle>
                                        <DialogDescription>
                                            {{ row.subject_name }} untuk
                                            {{ row.classroom_name }} tidak lagi
                                            ditugaskan ke
                                            {{ row.teacher_name }}.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter class="gap-2">
                                        <DialogClose as-child>
                                            <Button
                                                variant="secondary"
                                                class="min-h-11 md:min-h-0"
                                                >Batal</Button
                                            >
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            class="min-h-11 md:min-h-0"
                                            :disabled="processing"
                                            data-test="confirm-delete-assignment"
                                        >
                                            Hapus
                                        </Button>
                                    </DialogFooter>
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </template>
            </DataTable>
        </template>
    </div>
</template>
