<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { UserPlus } from '@lucide/vue';
import EnrollmentController from '@/actions/App/Http/Controllers/Admin/Schedule/EnrollmentController';
import academicYearsRoutes from '@/routes/admin/master/academic-years';
import classroomsRoutes from '@/routes/admin/master/classrooms';
import studentsRoutes from '@/routes/admin/users/students';
import enrollmentsRoutes from '@/routes/admin/schedule/enrollments';
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
    ActiveAcademicYear,
    EnrollmentClassroom,
    EnrolledStudent,
    UnenrolledStudent,
} from '@/types/schedule';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Penempatan & Jadwal',
                href: enrollmentsRoutes.index(),
            },
        ],
    },
});

type Props = {
    academicYear: ActiveAcademicYear | null;
    classrooms: EnrollmentClassroom[];
    students: UnenrolledStudent[];
    selectedClassroom: number | null;
};

const props = defineProps<Props>();

const page = usePage();
const deleteErrors = computed(() => {
    const errors = (page.props.errors ?? {}) as Record<string, string>;

    return errors.delete ? [errors.delete] : [];
});

const selected = computed(
    () =>
        props.classrooms.find(
            (classroom) => classroom.id === props.selectedClassroom,
        ) ?? null,
);

const columns = [
    { key: 'nisn', label: 'NISN' },
    { key: 'full_name', label: 'Nama' },
    { key: 'actions', label: '', className: 'w-28 text-right' },
];

function selectClassroom(event: Event): void {
    const classroomId = Number((event.target as HTMLSelectElement).value);

    router.get(
        enrollmentsRoutes.index(),
        { classroom: classroomId },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <Head title="Penempatan Siswa" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            title="Penempatan Siswa"
            description="Tempatkan siswa aktif ke ruang kelas pada tahun ajaran berjalan."
        />

        <SectionTabs :tabs="scheduleTabs" label="Penempatan dan jadwal" />

        <AlertError
            v-if="deleteErrors.length > 0"
            :errors="deleteErrors"
            title="Data tidak dapat dihapus"
            class="mb-4"
        />

        <EmptyState
            v-if="!academicYear"
            title="Belum ada tahun ajaran aktif"
            description="Tentukan satu tahun ajaran aktif agar penempatan siswa ke ruang kelas dapat dilakukan."
        >
            <template #actions>
                <Button size="sm" as-child class="min-h-11 md:min-h-0">
                    <Link :href="academicYearsRoutes.index()"
                        >Kelola tahun ajaran</Link
                    >
                </Button>
            </template>
        </EmptyState>

        <EmptyState
            v-else-if="classrooms.length === 0"
            title="Belum ada ruang kelas"
            description="Buat ruang kelas pada tahun ajaran ini sebelum menempatkan siswa."
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
                    <Label for="enrollments-classroom">Ruang kelas</Label>
                    <select
                        id="enrollments-classroom"
                        :value="selectedClassroom ?? ''"
                        class="flex h-9 w-full min-w-56 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        data-test="enrollments-classroom"
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
                    Tahun ajaran {{ academicYear.name }}
                </p>
            </div>

            <Form
                v-bind="enrollmentsRoutes.store.form()"
                :options="{ preserveScroll: true }"
                class="mb-6 flex flex-wrap items-end gap-3"
                data-test="enroll-form"
                v-slot="{ errors, processing }"
            >
                <input
                    type="hidden"
                    name="classroom_id"
                    :value="selectedClassroom"
                />
                <div class="grid min-w-64 flex-1 gap-2">
                    <Label for="enrollments-student">Siswa</Label>
                    <select
                        id="enrollments-student"
                        name="student_id"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="students.length === 0"
                        required
                        data-test="enrollments-student"
                    >
                        <option value="" disabled>Pilih siswa</option>
                        <option
                            v-for="student in students"
                            :key="student.id"
                            :value="student.id"
                        >
                            {{ student.full_name }} ({{ student.nisn }})
                        </option>
                    </select>
                    <InputError class="mt-2" :message="errors.student_id" />
                </div>
                <Button
                    type="submit"
                    :disabled="processing || students.length === 0"
                    class="min-h-11 md:min-h-0"
                    data-test="confirm-enroll"
                >
                    <UserPlus />
                    Tempatkan
                </Button>
            </Form>

            <p
                v-if="students.length === 0"
                class="mb-6 text-sm text-muted-foreground"
            >
                Semua siswa aktif sudah ditempatkan pada tahun ajaran ini.
                <Link
                    :href="studentsRoutes.index()"
                    class="underline underline-offset-4 hover:text-foreground"
                    >Kelola siswa</Link
                >
            </p>

            <DataTable
                v-if="selected"
                :columns="columns"
                :rows="selected.students"
                :row-key="(student: EnrolledStudent) => student.enrollment_id"
                :empty-title="`Belum ada siswa di kelas ${selected.name}`"
                empty-description="Tempatkan siswa lewat formulir di atas agar siswa muncul di daftar kelas ini."
            >
                <template #cell="{ row, column }">
                    <template v-if="column.key === 'nisn'">
                        {{ row.nisn }}
                    </template>
                    <template v-else-if="column.key === 'full_name'">
                        {{ row.full_name }}
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
                                    data-test="unenroll"
                                >
                                    Keluarkan
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <Form
                                    v-bind="
                                        EnrollmentController.destroy.form({
                                            classEnrollment: row.enrollment_id,
                                        })
                                    "
                                    :options="{ preserveScroll: true }"
                                    class="space-y-6"
                                    v-slot="{ processing }"
                                >
                                    <DialogHeader>
                                        <DialogTitle>
                                            Keluarkan siswa dari kelas?
                                        </DialogTitle>
                                        <DialogDescription>
                                            {{ row.full_name }} akan dihapus
                                            dari kelas {{ selected.name }} pada
                                            tahun ajaran berjalan.
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
                                            data-test="confirm-unenroll"
                                        >
                                            Keluarkan
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
