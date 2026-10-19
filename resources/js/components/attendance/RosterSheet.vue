<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import AlertError from '@/components/AlertError.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import type {
    AttendanceRosterStudent,
    AttendanceSessionState,
    AttendanceSlot,
    AttendanceStatusOption,
} from '@/types/attendance';

type Props = {
    date: string;
    slot: AttendanceSlot;
    roster: AttendanceRosterStudent[];
    session: AttendanceSessionState;
    statusOptions: AttendanceStatusOption[];
    submitUrl: string;
};

const props = defineProps<Props>();

const page = usePage();

const form = useForm<{
    date: string;
    entries: { student_id: number; status: string }[];
}>({
    date: props.date,
    entries: [],
});

const statuses = reactive<Record<number, string>>({});

function syncFromProps(): void {
    for (const key of Object.keys(statuses)) {
        delete statuses[Number(key)];
    }

    for (const student of props.roster) {
        statuses[student.id] =
            props.session.entries[String(student.id)] ?? 'present';
    }
}

watch(() => [props.session, props.roster], syncFromProps, { immediate: true });

const attendanceErrors = computed(() => {
    const errors = (page.props.errors ?? {}) as Record<
        string,
        string | string[]
    >;
    const raw = errors.attendance;

    if (raw === undefined) {
        return [];
    }

    return Array.isArray(raw) ? raw : [raw];
});

const submittedAt = computed(() => {
    if (props.session.submitted_at === null) {
        return null;
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(props.session.submitted_at));
});

function setStatus(studentId: number, value: string): void {
    statuses[studentId] = value;
}

function markAllPresent(): void {
    for (const student of props.roster) {
        statuses[student.id] = 'present';
    }
}

function save(): void {
    form.date = props.date;
    form.entries = props.roster.map((student) => ({
        student_id: student.id,
        status: statuses[student.id] ?? 'present',
    }));
    form.post(props.submitUrl, { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-4" data-test="attendance-sheet">
        <Alert
            v-if="session.submitted && !session.editable"
            data-test="attendance-lock"
        >
            <AlertTitle>Terkunci</AlertTitle>
            <AlertDescription>
                {{ session.lock_message }}
            </AlertDescription>
        </Alert>

        <p
            v-else-if="session.submitted && submittedAt !== null"
            class="text-sm text-muted-foreground"
            data-test="attendance-banner"
        >
            Dikirim {{ submittedAt }}
            <template v-if="session.submitted_by !== null">
                oleh {{ session.submitted_by }}</template
            >.
        </p>

        <AlertError
            v-if="attendanceErrors.length > 0"
            :errors="attendanceErrors"
            title="Presensi gagal disimpan."
        />

        <div v-if="roster.length === 0" data-test="attendance-roster-empty">
            <EmptyState
                title="Tidak ada siswa"
                description="Belum ada siswa aktif yang terdaftar di kelas ini. Hubungi admin untuk memeriksa penempatan siswa."
            />
        </div>

        <template v-else>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm text-muted-foreground">
                    {{ roster.length }} siswa
                </p>
                <Button
                    v-if="session.editable"
                    type="button"
                    variant="outline"
                    size="sm"
                    data-test="attendance-mark-all"
                    @click="markAllPresent"
                >
                    Tandai semua Hadir
                </Button>
            </div>

            <ul class="divide-y rounded-md border">
                <li
                    v-for="student in roster"
                    :key="student.id"
                    class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between"
                    :data-test="`attendance-student-${student.id}`"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            {{ student.full_name }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            NISN {{ student.nisn }}
                        </p>
                    </div>
                    <label class="flex items-center gap-2">
                        <span class="sr-only"
                            >Kehadiran {{ student.full_name }}</span
                        >
                        <select
                            class="h-11 w-full min-w-36 rounded-md border bg-background px-3 text-sm md:h-9"
                            :data-test="`attendance-status-${student.id}`"
                            :value="statuses[student.id] ?? 'present'"
                            :disabled="!session.editable"
                            @change="
                                setStatus(
                                    student.id,
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option
                                v-for="option in statusOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                </li>
            </ul>

            <div v-if="session.editable" class="flex justify-end">
                <Button
                    type="button"
                    data-test="attendance-save"
                    :disabled="form.processing"
                    @click="save"
                >
                    {{ form.processing ? 'Menyimpan…' : 'Simpan Presensi' }}
                </Button>
            </div>
        </template>
    </div>
</template>
