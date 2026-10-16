<script setup lang="ts">
import { Form, Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Plus, Trash2 } from '@lucide/vue';
import ScheduleController from '@/actions/App/Http/Controllers/Admin/Schedule/ScheduleController';
import classroomsRoutes from '@/routes/admin/master/classrooms';
import semestersRoutes from '@/routes/admin/master/semesters';
import assignmentsRoutes from '@/routes/admin/schedule/assignments';
import weeklyRoutes from '@/routes/admin/schedule/weekly';
import AlertError from '@/components/AlertError.vue';
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
    DayOption,
    LessonPeriodOption,
    ScheduleAssignmentRecord,
    ScheduleClassroomOption,
    ScheduleSlot,
} from '@/types/schedule';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Penempatan & Jadwal',
                href: weeklyRoutes.index(),
            },
        ],
    },
});

type Props = {
    semester: ActiveSemesterContext | null;
    classrooms: ScheduleClassroomOption[];
    assignments: ScheduleAssignmentRecord[];
    schedules: ScheduleSlot[];
    lessonPeriods: LessonPeriodOption[];
    days: DayOption[];
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

const gridRows = computed(() => {
    const assignmentById = new Map(
        props.assignments.map((assignment) => [assignment.id, assignment]),
    );
    const assignmentIds = new Set(
        classroomAssignments.value.map((assignment) => assignment.id),
    );
    const slots = props.schedules.filter((slot) =>
        assignmentIds.has(slot.teaching_assignment_id),
    );

    return props.lessonPeriods.map((period) => ({
        period,
        cells: props.days.map((day) => {
            const slot =
                slots.find(
                    (candidate) =>
                        candidate.day_of_week === day.value &&
                        candidate.lesson_period_id === period.id,
                ) ?? null;

            return {
                day,
                slot,
                assignment:
                    slot !== null
                        ? (assignmentById.get(slot.teaching_assignment_id) ??
                          null)
                        : null,
            };
        }),
    }));
});

const addOpen = ref(false);
const addContext = ref<{ day: DayOption; period: LessonPeriodOption } | null>(
    null,
);
const addForm = useForm({
    teaching_assignment_id: '',
    day_of_week: '',
    lesson_period_id: '',
});

function openAdd(day: DayOption, period: LessonPeriodOption): void {
    addForm.clearErrors();
    addForm.reset('teaching_assignment_id');
    addForm.day_of_week = day.value;
    addForm.lesson_period_id = String(period.id);
    addContext.value = { day, period };
    addOpen.value = true;
}

function submitAdd(): void {
    addForm
        .transform((data) => ({
            teaching_assignment_id: Number(data.teaching_assignment_id),
            day_of_week: data.day_of_week,
            lesson_period_id: Number(data.lesson_period_id),
        }))
        .post(weeklyRoutes.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                addOpen.value = false;
                addForm.reset();
            },
        });
}

function selectClassroom(event: Event): void {
    const classroomId = Number((event.target as HTMLSelectElement).value);

    router.get(
        weeklyRoutes.index(),
        { classroom: classroomId },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <Head title="Jadwal Mingguan" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            title="Jadwal Mingguan"
            description="Susun jadwal pelajaran tiap kelas per hari dan jam pelajaran."
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
            description="Tentukan satu semester aktif agar jadwal pelajaran dapat disusun."
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
            description="Buat ruang kelas pada tahun ajaran ini sebelum menyusun jadwal."
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
                    <Label for="weekly-classroom">Ruang kelas</Label>
                    <select
                        id="weekly-classroom"
                        :value="selectedClassroom ?? ''"
                        class="flex h-9 w-full min-w-56 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        data-test="weekly-classroom"
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

            <EmptyState
                v-if="classroomAssignments.length === 0"
                title="Belum ada penugasan mengajar"
                description="Tugaskan guru ke mata pelajaran kelas ini agar jadwalnya dapat diisi."
            >
                <template #actions>
                    <Button size="sm" as-child class="min-h-11 md:min-h-0">
                        <Link :href="assignmentsRoutes.index()"
                            >Buat penugasan</Link
                        >
                    </Button>
                </template>
            </EmptyState>

            <div v-else class="overflow-x-auto rounded-lg border border-border">
                <table class="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr
                            class="border-b border-border bg-muted/40 text-left text-muted-foreground"
                        >
                            <th
                                scope="col"
                                class="w-36 px-4 py-2.5 font-medium"
                            >
                                Jam Pelajaran
                            </th>
                            <th
                                v-for="day in days"
                                :key="day.value"
                                scope="col"
                                class="px-4 py-2.5 font-medium"
                            >
                                {{ day.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in gridRows"
                            :key="row.period.id"
                            class="border-b border-border/60 last:border-0"
                        >
                            <th
                                scope="row"
                                class="px-4 py-2 text-left align-top font-medium"
                            >
                                <span>Jam ke-{{ row.period.number }}</span>
                                <span
                                    class="block text-xs font-normal text-muted-foreground"
                                >
                                    {{ row.period.start_time }}–{{
                                        row.period.end_time
                                    }}
                                </span>
                            </th>
                            <td
                                v-for="cell in row.cells"
                                :key="cell.day.value"
                                class="border-l border-border/60 p-0 align-top"
                            >
                                <div
                                    v-if="cell.slot && cell.assignment"
                                    class="flex min-h-16 items-start justify-between gap-1 p-2"
                                >
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">
                                            {{ cell.assignment.subject_name }}
                                        </p>
                                        <p
                                            class="truncate text-xs text-muted-foreground"
                                        >
                                            {{ cell.assignment.teacher_name }}
                                        </p>
                                    </div>
                                    <Dialog>
                                        <DialogTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                                                aria-label="Hapus jadwal"
                                                data-test="delete-schedule"
                                            >
                                                <Trash2 />
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <Form
                                                v-bind="
                                                    ScheduleController.destroy.form(
                                                        {
                                                            schedule:
                                                                cell.slot.id,
                                                        },
                                                    )
                                                "
                                                :options="{
                                                    preserveScroll: true,
                                                }"
                                                class="space-y-6"
                                                v-slot="{ processing }"
                                            >
                                                <DialogHeader>
                                                    <DialogTitle>
                                                        Hapus jadwal?
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        {{
                                                            cell.assignment
                                                                .subject_name
                                                        }}
                                                        di
                                                        {{
                                                            selectedClassroomName
                                                        }}
                                                        pada
                                                        {{ cell.day.label }}
                                                        jam ke-{{
                                                            row.period.number
                                                        }}
                                                        akan dihapus dari
                                                        jadwal.
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
                                                        data-test="confirm-delete-schedule"
                                                    >
                                                        Hapus
                                                    </Button>
                                                </DialogFooter>
                                            </Form>
                                        </DialogContent>
                                    </Dialog>
                                </div>
                                <button
                                    v-else
                                    type="button"
                                    class="flex min-h-16 w-full items-center justify-center gap-1 text-xs text-muted-foreground transition-colors hover:bg-muted/40 hover:text-foreground focus-visible:bg-muted/40 focus-visible:outline-none"
                                    :aria-label="`Tambah jadwal ${cell.day.label} jam ke-${row.period.number}`"
                                    data-test="add-slot"
                                    @click="openAdd(cell.day, row.period)"
                                >
                                    <Plus class="size-3.5" />
                                    Tambah
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <Dialog v-model:open="addOpen">
            <DialogContent>
                <form class="space-y-6" @submit.prevent="submitAdd">
                    <DialogHeader>
                        <DialogTitle>Tambah jadwal</DialogTitle>
                        <DialogDescription v-if="addContext">
                            Pilih penugasan untuk
                            {{ addContext.day.label }} jam ke-{{
                                addContext.period.number
                            }}
                            di kelas {{ selectedClassroomName }}.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-2">
                        <Label for="slot-assignment">Penugasan</Label>
                        <select
                            id="slot-assignment"
                            v-model="addForm.teaching_assignment_id"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            required
                            data-test="slot-assignment"
                        >
                            <option value="" disabled>Pilih penugasan</option>
                            <option
                                v-for="assignment in classroomAssignments"
                                :key="assignment.id"
                                :value="String(assignment.id)"
                            >
                                {{ assignment.subject_name }} -
                                {{ assignment.teacher_name }}
                            </option>
                        </select>
                        <InputError
                            class="mt-2"
                            :message="addForm.errors.teaching_assignment_id"
                        />
                    </div>
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
                            :disabled="addForm.processing"
                            class="min-h-11 md:min-h-0"
                            data-test="confirm-schedule"
                        >
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
