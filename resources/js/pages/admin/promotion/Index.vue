<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { AlertCircle } from '@lucide/vue';
import { computed, reactive } from 'vue';
import PromotionController from '@/actions/App/Http/Controllers/Admin/PromotionController';
import AlertError from '@/components/AlertError.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import academicYearsRoutes from '@/routes/admin/master/academic-years';
import promotionRoutes from '@/routes/admin/promotion';
import type {
    PromotionDecisionRow,
    PromotionIndexProps,
    PromotionOutcomeValue,
    PromotionTargetClassroom,
} from '@/types/promotion';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Kenaikan Kelas',
                href: promotionRoutes.index(),
            },
        ],
    },
});

type Props = PromotionIndexProps;

const props = defineProps<Props>();

const page = usePage();

const promotionErrors = computed(() => [
    ...new Set([
        ...readErrors('promotion'),
        ...readErrors('promotion_batch'),
        ...readErrors('academic_year'),
        ...readErrors('outcome'),
        ...readErrors('reason'),
        ...readErrors('target_classroom_id'),
        ...(page.flash.promotion ?? []),
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

const columns = [
    { key: 'full_name', label: 'Siswa' },
    { key: 'nisn', label: 'NISN' },
    { key: 'from_class', label: 'Kelas Asal' },
    { key: 'outcome', label: 'Keputusan' },
    { key: 'to_class', label: 'Kelas Tujuan' },
    { key: 'override', label: 'Penyesuaian' },
    { key: 'actions', label: '', className: 'w-44 text-right' },
];

const outcomeLabels: Record<PromotionOutcomeValue, string> = {
    promote: 'Naik Kelas',
    retain: 'Tinggal Kelas',
    graduate: 'Lulus',
};

const outcomeBadgeVariants: Record<
    PromotionOutcomeValue,
    'default' | 'outline' | 'secondary'
> = {
    promote: 'default',
    retain: 'outline',
    graduate: 'secondary',
};

const yearLine = computed(() =>
    props.from !== null && props.to !== null
        ? `Tahun ajaran ${props.from.name} menuju ${props.to.name}.`
        : null,
);

const canPropose = computed(
    () => props.from !== null && props.to !== null && props.batch === null,
);

const canApply = computed(
    () =>
        props.batch?.status === 'draft' &&
        props.blockers.length === 0 &&
        (props.summary?.total ?? 0) > 0,
);

const canOverride = computed(() => props.batch?.status === 'draft');

type OverrideDraft = {
    outcome: PromotionOutcomeValue;
    target: string;
};

const overrideDrafts = reactive<Record<number, OverrideDraft>>({});

function draft(row: PromotionDecisionRow): OverrideDraft {
    const existing = overrideDrafts[row.id];

    if (existing !== undefined) {
        return existing;
    }

    const fresh: OverrideDraft = {
        outcome: row.outcome,
        target: row.to_class_id !== null ? String(row.to_class_id) : '',
    };

    overrideDrafts[row.id] = fresh;

    return fresh;
}

function openOverride(row: PromotionDecisionRow, open: boolean): void {
    if (!open) {
        return;
    }

    const validTarget =
        row.to_class_id !== null &&
        targetOptions(row, row.outcome).some(
            (classroom) => classroom.id === row.to_class_id,
        );

    overrideDrafts[row.id] = {
        outcome: row.outcome,
        target: validTarget ? String(row.to_class_id) : '',
    };
}

function onOutcomeChange(row: PromotionDecisionRow, event: Event): void {
    const select = event.target as HTMLSelectElement;
    const current = draft(row);

    current.outcome = select.value as PromotionOutcomeValue;

    if (
        current.target !== '' &&
        !targetOptions(row, current.outcome).some(
            (classroom) => String(classroom.id) === current.target,
        )
    ) {
        current.target = '';
    }
}

function onTargetChange(row: PromotionDecisionRow, event: Event): void {
    const select = event.target as HTMLSelectElement;

    draft(row).target = select.value;
}

function targetOptions(
    row: PromotionDecisionRow,
    outcome?: PromotionOutcomeValue,
): PromotionTargetClassroom[] {
    const current = outcome ?? draft(row).outcome;

    if (current === 'graduate') {
        return [];
    }

    if (row.from_grade_level === null) {
        return props.target_classes;
    }

    const level =
        current === 'retain'
            ? row.from_grade_level
            : Math.min(row.from_grade_level + 1, 12);

    return props.target_classes.filter(
        (classroom) => classroom.grade_level === level,
    );
}
</script>

<template>
    <Head title="Kenaikan Kelas" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            title="Kenaikan Kelas"
            description="Tinjau keputusan otomatis per siswa, ubah bila perlu, lalu terapkan kenaikan kelas."
        >
            <template #actions>
                <Form
                    v-if="canPropose"
                    v-bind="PromotionController.propose.form()"
                    :options="{ preserveScroll: true }"
                    v-slot="{ processing }"
                >
                    <Button
                        type="submit"
                        class="min-h-11 md:min-h-0"
                        :disabled="processing"
                        data-test="promotion-propose"
                    >
                        Buat Pratinjau
                    </Button>
                </Form>

                <Dialog v-else-if="canApply">
                    <DialogTrigger as-child>
                        <Button
                            class="min-h-11 md:min-h-0"
                            data-test="promotion-apply"
                        >
                            Terapkan Kenaikan
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <Form
                            v-bind="PromotionController.apply.form()"
                            :options="{ preserveScroll: true }"
                            class="space-y-6"
                            v-slot="{ processing }"
                        >
                            <DialogHeader>
                                <DialogTitle
                                    >Terapkan kenaikan kelas?</DialogTitle
                                >
                                <DialogDescription>
                                    {{ summary?.total ?? 0 }} siswa akan
                                    dipindahkan sesuai keputusan pada tabel.
                                    Tindakan ini tidak dapat dibatalkan.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button
                                        variant="secondary"
                                        class="min-h-11 md:min-h-0"
                                        data-test="cancel-promotion-apply"
                                        >Batal</Button
                                    >
                                </DialogClose>
                                <Button
                                    type="submit"
                                    class="min-h-11 md:min-h-0"
                                    :disabled="processing"
                                    data-test="confirm-promotion-apply"
                                >
                                    Terapkan Kenaikan
                                </Button>
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </template>
        </PageHeader>

        <AlertError
            v-if="promotionErrors.length > 0"
            :errors="promotionErrors"
            title="Permintaan gagal diproses."
            class="mb-4"
            data-test="promotion-error"
        />

        <EmptyState
            v-if="missing_year_message !== null"
            data-test="promotion-missing-year"
            title="Tahun ajaran belum lengkap"
            :description="missing_year_message"
        >
            <template #actions>
                <Button size="sm" as-child class="min-h-11 md:min-h-0">
                    <Link :href="academicYearsRoutes.index()"
                        >Kelola tahun ajaran</Link
                    >
                </Button>
            </template>
        </EmptyState>

        <template v-else>
            <div
                v-if="yearLine !== null"
                class="mb-6 rounded-lg border border-border p-4"
                data-test="promotion-status"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-sm font-medium">{{ yearLine }}</p>
                    <Badge
                        v-if="batch !== null"
                        :variant="
                            batch.status === 'applied' ? 'default' : 'secondary'
                        "
                        data-test="promotion-status-badge"
                    >
                        {{ batch.status_label }}
                    </Badge>
                </div>
                <p
                    v-if="batch?.applied_at != null"
                    class="mt-1 text-sm text-muted-foreground"
                    data-test="promotion-applied-at"
                >
                    Diterapkan {{ batch.applied_at }}.
                </p>
            </div>

            <Alert
                v-if="batch !== null && blockers.length > 0"
                variant="destructive"
                class="mb-6"
                data-test="promotion-blockers"
            >
                <AlertCircle class="size-4" />
                <AlertTitle>Penerapan belum dapat dijalankan.</AlertTitle>
                <AlertDescription>
                    <ul class="list-inside list-disc text-sm">
                        <li v-for="blocker in blockers" :key="blocker">
                            {{ blocker }}
                        </li>
                    </ul>
                </AlertDescription>
            </Alert>

            <div
                v-if="summary !== null"
                class="mb-6 flex flex-wrap gap-1.5"
                data-test="promotion-summary"
            >
                <Badge variant="outline">Total {{ summary.total }} siswa</Badge>
                <Badge variant="secondary">
                    Naik Kelas {{ summary.promoted }}
                </Badge>
                <Badge variant="secondary">
                    Tinggal Kelas {{ summary.retained }}
                </Badge>
                <Badge variant="secondary">Lulus {{ summary.graduated }}</Badge>
                <Badge
                    v-if="summary.unassigned > 0"
                    variant="destructive"
                    data-test="promotion-unassigned"
                >
                    Menunggu kelas {{ summary.unassigned }}
                </Badge>
            </div>

            <EmptyState
                v-if="batch === null"
                data-test="promotion-empty"
                title="Belum ada pratinjau"
                description="Buat pratinjau untuk melihat rincian keputusan otomatis sebelum kenaikan kelas diterapkan."
            />

            <EmptyState
                v-else-if="decisions.length === 0"
                data-test="promotion-no-students"
                title="Belum ada siswa"
                description="Tidak ada siswa aktif pada tahun ajaran ini, sehingga tidak ada keputusan kenaikan yang dapat dibuat."
            />

            <DataTable
                v-else
                :columns="columns"
                :rows="decisions"
                :row-key="(row: PromotionDecisionRow) => row.id"
                empty-title="Belum ada siswa"
                empty-description="Tidak ada siswa aktif pada tahun ajaran ini."
            >
                <template #cell="{ row, column }">
                    <div
                        v-if="column.key === 'full_name'"
                        :data-test="`promotion-student-${row.student_id}`"
                    >
                        {{ row.full_name ?? '-' }}
                    </div>

                    <Badge
                        v-else-if="column.key === 'outcome'"
                        :variant="outcomeBadgeVariants[row.outcome]"
                        :data-test="`promotion-outcome-${row.id}`"
                    >
                        {{ outcomeLabels[row.outcome] }}
                    </Badge>

                    <span v-else-if="column.key === 'to_class'">
                        {{ row.to_class ?? '-' }}
                    </span>

                    <div v-else-if="column.key === 'override'">
                        <Badge
                            v-if="row.is_overridden"
                            variant="outline"
                            :data-test="`promotion-override-flag-${row.id}`"
                        >
                            Diubah
                        </Badge>
                        <span v-else class="text-muted-foreground">-</span>
                    </div>

                    <div
                        v-else-if="column.key === 'actions'"
                        class="flex justify-end"
                    >
                        <Dialog
                            v-if="canOverride"
                            @update:open="(open) => openOverride(row, open)"
                        >
                            <DialogTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="min-h-11 md:min-h-0"
                                    :data-test="`promotion-override-${row.id}`"
                                >
                                    Ubah Keputusan
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <Form
                                    v-bind="
                                        PromotionController.override.form({
                                            promotionDecision: row.id,
                                        })
                                    "
                                    :options="{ preserveScroll: true }"
                                    class="space-y-6"
                                    v-slot="{ processing }"
                                >
                                    <DialogHeader>
                                        <DialogTitle
                                            >Ubah keputusan
                                            kenaikan?</DialogTitle
                                        >
                                        <DialogDescription>
                                            Keputusan untuk
                                            {{ row.full_name ?? 'siswa ini' }}
                                            dapat berbeda dari hasil aturan
                                            otomatis. Perubahan dicatat di log
                                            audit.
                                        </DialogDescription>
                                    </DialogHeader>

                                    <div class="space-y-2">
                                        <label
                                            :for="`override-outcome-${row.id}`"
                                            class="text-sm font-medium"
                                        >
                                            Keputusan
                                        </label>
                                        <select
                                            :id="`override-outcome-${row.id}`"
                                            name="outcome"
                                            :value="draft(row).outcome"
                                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            :data-test="`promotion-outcome-select-${row.id}`"
                                            @change="
                                                onOutcomeChange(row, $event)
                                            "
                                        >
                                            <option
                                                v-for="(
                                                    label, value
                                                ) in outcomeLabels"
                                                :key="value"
                                                :value="value"
                                            >
                                                {{ label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div
                                        v-if="targetOptions(row).length > 0"
                                        class="space-y-2"
                                    >
                                        <label
                                            :for="`override-target-${row.id}`"
                                            class="text-sm font-medium"
                                        >
                                            Kelas tujuan
                                        </label>
                                        <select
                                            :id="`override-target-${row.id}`"
                                            name="target_classroom_id"
                                            :value="draft(row).target"
                                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            :data-test="`promotion-target-select-${row.id}`"
                                            @change="
                                                onTargetChange(row, $event)
                                            "
                                        >
                                            <option value="">
                                                Otomatis (sesuai aturan)
                                            </option>
                                            <option
                                                v-for="classroom in targetOptions(
                                                    row,
                                                )"
                                                :key="classroom.id"
                                                :value="classroom.id"
                                            >
                                                {{ classroom.name }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <label
                                            :for="`override-reason-${row.id}`"
                                            class="text-sm font-medium"
                                        >
                                            Alasan
                                        </label>
                                        <textarea
                                            :id="`override-reason-${row.id}`"
                                            name="reason"
                                            rows="3"
                                            maxlength="500"
                                            placeholder="Tulis alasan perubahan keputusan."
                                            class="flex min-h-11 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:min-h-0"
                                            :data-test="`promotion-reason-${row.id}`"
                                        ></textarea>
                                    </div>

                                    <DialogFooter class="gap-2">
                                        <DialogClose as-child>
                                            <Button
                                                variant="secondary"
                                                class="min-h-11 md:min-h-0"
                                                :data-test="`cancel-promotion-override-${row.id}`"
                                                >Batal</Button
                                            >
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            class="min-h-11 md:min-h-0"
                                            :disabled="processing"
                                            :data-test="`confirm-promotion-override-${row.id}`"
                                        >
                                            Simpan Keputusan
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
