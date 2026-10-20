<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ReportController from '@/actions/App/Http/Controllers/Admin/ReportController';
import AlertError from '@/components/AlertError.vue';
import DataTable from '@/components/DataTable.vue';
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
import type { ReportIndexProps, SemesterRecord } from '@/types/reports';

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

type Props = ReportIndexProps;

defineProps<Props>();

const page = usePage();

const reasonErrors = computed(() => readErrors('reason'));

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
    { key: 'name', label: 'Semester' },
    { key: 'academic_year', label: 'Tahun Ajaran' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '', className: 'w-56 text-right' },
];
</script>

<template>
    <Head title="Terbitkan Rapor" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            title="Terbitkan Rapor"
            description="Kunci nilai, buat PDF rapor, dan terbitkan rapor untuk setiap semester."
        />

        <AlertError
            v-if="reasonErrors.length > 0"
            :errors="reasonErrors"
            title="Kunci gagal dibuka."
            class="mb-4"
            data-test="report-reason-error"
        />

        <DataTable
            :columns="columns"
            :rows="semesters"
            :row-key="(semester: SemesterRecord) => semester.id"
            empty-title="Belum ada semester"
            empty-description="Tambahkan tahun ajaran dan semester di Data Master agar status kunci dan penerbitan rapor dapat diatur."
        >
            <template #cell="{ row, column }">
                <div
                    v-if="column.key === 'status'"
                    class="flex flex-wrap gap-1.5"
                    :data-test="`report-status-${row.id}`"
                >
                    <Badge :variant="row.locked ? 'outline' : 'secondary'">
                        {{ row.locked ? 'Terkunci' : 'Terbuka' }}
                    </Badge>
                    <Badge :variant="row.published ? 'default' : 'outline'">
                        {{ row.published ? 'Sudah Terbit' : 'Belum Terbit' }}
                    </Badge>
                    <Badge v-if="row.is_active">Aktif</Badge>
                </div>

                <div
                    v-else-if="column.key === 'actions'"
                    class="flex justify-end gap-1"
                >
                    <Button
                        variant="ghost"
                        size="sm"
                        as-child
                        class="min-h-11 md:min-h-0"
                        data-test="report-manage"
                    >
                        <Link :href="reportsRoutes.show.url(row.id)"
                            >Kelola</Link
                        >
                    </Button>

                    <Dialog v-if="!row.locked">
                        <DialogTrigger as-child>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="min-h-11 md:min-h-0"
                                data-test="report-lock"
                            >
                                Kunci
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="
                                    ReportController.lock.form({
                                        semester: row.id,
                                    })
                                "
                                :options="{ preserveScroll: true }"
                                class="space-y-6"
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle
                                        >Kunci nilai semester?</DialogTitle
                                    >
                                    <DialogDescription>
                                        Nilai {{ row.name }} tahun ajaran
                                        {{ row.academic_year }} tidak dapat
                                        diubah guru setelah dikunci. Admin dapat
                                        membukanya kembali dengan alasan.
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
                                        data-test="confirm-report-lock"
                                    >
                                        Kunci Nilai
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>

                    <Dialog v-else>
                        <DialogTrigger as-child>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="min-h-11 md:min-h-0"
                                data-test="report-unlock"
                            >
                                Buka Kunci
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="
                                    ReportController.unlock.form({
                                        semester: row.id,
                                    })
                                "
                                :options="{ preserveScroll: true }"
                                class="space-y-6"
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>Buka kunci nilai?</DialogTitle>
                                    <DialogDescription>
                                        Alasan pembukaan dicatat di log audit,
                                        lalu guru dapat mengubah nilai kembali.
                                    </DialogDescription>
                                </DialogHeader>
                                <div class="space-y-2">
                                    <label
                                        for="unlock-reason"
                                        class="text-sm font-medium"
                                    >
                                        Alasan
                                    </label>
                                    <textarea
                                        id="unlock-reason"
                                        name="reason"
                                        rows="3"
                                        maxlength="500"
                                        placeholder="Tulis alasan pembukaan kunci."
                                        class="flex min-h-11 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:min-h-0"
                                        data-test="report-unlock-reason"
                                    ></textarea>
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
                                        class="min-h-11 md:min-h-0"
                                        :disabled="processing"
                                        data-test="confirm-report-unlock"
                                    >
                                        Buka Kunci
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
            </template>
        </DataTable>
    </div>
</template>
