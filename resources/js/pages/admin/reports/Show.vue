<script setup lang="ts">
import { Form, Head, Link, usePage, usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import ReportController from '@/actions/App/Http/Controllers/Admin/ReportController';
import AlertError from '@/components/AlertError.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
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
import reportsRoutes from '@/routes/admin/reports';
import studentsRoutes from '@/routes/admin/users/students';
import type { GenerationRow, ReportShowProps } from '@/types/reports';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Terbitkan Rapor',
                href: reportsRoutes.index(),
            },
        ],
    },
});

type Props = ReportShowProps;

const props = defineProps<Props>();

const page = usePage();

const reportErrors = computed(() => [
    ...new Set([
        ...readErrors('report_card'),
        ...(page.flash.report_card ?? []),
    ]),
]);

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

const semesterStatusText = computed(() => {
    const parts: string[] = [];

    if (props.semester.locked) {
        parts.push(
            props.semester.locked_at !== null
                ? `Nilai dikunci sejak ${props.semester.locked_at}.`
                : 'Nilai terkunci.',
        );
    } else {
        parts.push('Nilai masih terbuka untuk guru.');
    }

    if (props.semester.published) {
        parts.push(
            props.semester.published_at !== null
                ? `Rapor diterbitkan sejak ${props.semester.published_at}.`
                : 'Rapor sudah diterbitkan.',
        );
    } else {
        parts.push('Rapor belum diterbitkan.');
    }

    return parts.join(' ');
});

const statusLabels: Record<GenerationRow['status'], string> = {
    pending: 'Menunggu',
    failed: 'Gagal',
    done: 'Selesai',
    none: 'Belum dibuat',
};

const statusVariants: Record<
    GenerationRow['status'],
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    pending: 'outline',
    failed: 'destructive',
    done: 'default',
    none: 'secondary',
};

const columns = [
    { key: 'full_name', label: 'Siswa' },
    { key: 'nisn', label: 'NISN' },
    { key: 'status', label: 'Status PDF' },
    { key: 'generated_at', label: 'Dibuat' },
];

const { start, stop } = usePoll(5000, () => ({ only: ['generation'] }), {
    autoStart: false,
});

watch(
    () => props.generation.summary.pending,
    (pending) => {
        if (pending > 0) {
            start();
        } else {
            stop();
        }
    },
    { immediate: true },
);
</script>

<template>
    <Head :title="`Rapor ${props.semester.name}`" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            :title="`Rapor ${props.semester.name}`"
            :description="`Tahun Ajaran ${props.semester.academic_year}`"
        >
            <template #actions>
                <template v-if="props.generation.summary.total > 0">
                    <Form
                        v-bind="
                            ReportController.generate.form({
                                semester: props.semester.id,
                            })
                        "
                        :options="{ preserveScroll: true }"
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            size="sm"
                            class="min-h-11 md:min-h-0"
                            :disabled="processing"
                            data-test="report-generate"
                        >
                            {{
                                processing ? 'Menjalankan…' : 'Buat PDF Massal'
                            }}
                        </Button>
                    </Form>

                    <Form
                        v-if="props.generation.summary.failed > 0"
                        v-bind="
                            ReportController.retry.form({
                                semester: props.semester.id,
                            })
                        "
                        :options="{ preserveScroll: true }"
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            class="min-h-11 md:min-h-0"
                            :disabled="processing"
                            data-test="report-retry"
                        >
                            {{ processing ? 'Menjalankan…' : 'Coba Lagi' }}
                        </Button>
                    </Form>

                    <Dialog>
                        <DialogTrigger as-child>
                            <Button
                                size="sm"
                                class="min-h-11 md:min-h-0"
                                data-test="report-publish"
                            >
                                Terbitkan Rapor
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="
                                    ReportController.publish.form({
                                        semester: props.semester.id,
                                    })
                                "
                                :options="{ preserveScroll: true }"
                                class="space-y-6"
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle
                                        >Terbitkan rapor semester
                                        ini?</DialogTitle
                                    >
                                    <DialogDescription>
                                        Semua rapor yang sudah dibuat ditandai
                                        terbit sehingga siswa dan wali dapat
                                        mengunduhnya. Penerbitan dibatalkan bila
                                        ada siswa yang belum siap.
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
                                        class="min-h-11 md:min-h-0"
                                        :disabled="processing"
                                        data-test="confirm-report-publish"
                                    >
                                        Terbitkan
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </template>
            </template>
        </PageHeader>

        <div
            class="mb-4 flex flex-col gap-3 rounded-md border p-4"
            data-test="report-semester-status"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="min-w-0">
                    <p class="text-sm font-medium">Status semester</p>
                    <p class="text-sm text-muted-foreground">
                        {{ semesterStatusText }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <Badge
                        v-if="props.semester.locked"
                        variant="outline"
                        data-test="report-locked-badge"
                    >
                        Terkunci
                    </Badge>
                    <Badge
                        v-if="props.semester.published"
                        data-test="report-published-badge"
                    >
                        Sudah Terbit
                    </Badge>
                    <Badge v-if="props.semester.is_active">Aktif</Badge>
                </div>
            </div>
        </div>

        <AlertError
            v-if="reportErrors.length > 0"
            :errors="reportErrors"
            title="Permintaan gagal diproses."
            class="mb-4"
            data-test="report-error"
        />

        <div
            v-if="props.generation.summary.total > 0"
            class="mb-4 flex flex-wrap gap-2"
            data-test="report-summary"
        >
            <Badge variant="secondary">
                {{ props.generation.summary.total }} siswa
            </Badge>
            <Badge> {{ props.generation.summary.done }} selesai </Badge>
            <Badge variant="outline">
                {{ props.generation.summary.pending }} menunggu
            </Badge>
            <Badge variant="destructive">
                {{ props.generation.summary.failed }} gagal
            </Badge>
            <Badge
                v-if="props.generation.summary.without_card > 0"
                variant="outline"
            >
                {{ props.generation.summary.without_card }} belum dibuat
            </Badge>
        </div>

        <div
            v-if="props.generation.summary.total === 0"
            class="rounded-lg border border-border p-6"
            data-test="report-empty"
        >
            <EmptyState
                title="Belum ada siswa aktif"
                description="Tambahkan siswa dan penempatan kelas pada tahun ajaran ini agar rapor dapat dibuat dan diterbitkan."
            >
                <template #actions>
                    <Button
                        size="sm"
                        as-child
                        class="min-h-11 md:min-h-0"
                        data-test="report-empty-students"
                    >
                        <Link :href="studentsRoutes.index()">Kelola Siswa</Link>
                    </Button>
                </template>
            </EmptyState>
        </div>

        <DataTable
            v-else
            :columns="columns"
            :rows="props.generation.rows"
            :row-key="(row: GenerationRow) => row.student_id"
            empty-title="Belum ada siswa"
            empty-description="Belum ada siswa aktif yang terdaftar pada tahun ajaran ini."
        >
            <template #cell="{ row, column }">
                <div
                    v-if="column.key === 'full_name'"
                    class="min-w-0"
                    :data-test="`report-student-${row.student_id}`"
                >
                    <p class="truncate text-sm font-medium">
                        {{ row.full_name }}
                    </p>
                </div>

                <div v-else-if="column.key === 'status'" class="space-y-1">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <Badge :variant="statusVariants[row.status]">
                            {{ statusLabels[row.status] }}
                        </Badge>
                        <Badge
                            v-if="row.revised"
                            variant="outline"
                            :data-test="`report-revised-${row.student_id}`"
                        >
                            Direvisi
                        </Badge>
                    </div>
                    <p
                        v-if="row.error !== null"
                        class="text-xs break-words text-destructive"
                        :data-test="`report-error-${row.student_id}`"
                    >
                        {{ row.error }}
                    </p>
                </div>

                <span v-else-if="column.key === 'generated_at'">
                    {{ row.generated_at ?? '—' }}
                </span>
            </template>
        </DataTable>
    </div>
</template>
