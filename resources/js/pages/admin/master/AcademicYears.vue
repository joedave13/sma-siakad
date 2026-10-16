<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Plus } from '@lucide/vue';
import AcademicYearController from '@/actions/App/Http/Controllers/Admin/Master/AcademicYearController';
import AlertError from '@/components/AlertError.vue';
import DataTable from '@/components/DataTable.vue';
import SectionTabs from '@/components/SectionTabs.vue';
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
import academicYears from '@/routes/admin/master/academic-years';
import { masterTabs } from './tabs';
import type { AcademicYearRecord } from '@/types/master';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Tahun Ajaran',
                href: academicYears.index(),
            },
        ],
    },
});

type Props = {
    years: AcademicYearRecord[];
};

defineProps<Props>();

const page = usePage();
const serverErrors = computed(() =>
    Object.values((page.props.errors ?? {}) as Record<string, string>).filter(
        (message): message is string => typeof message === 'string',
    ),
);

const columns = [
    { key: 'name', label: 'Nama' },
    { key: 'start_date', label: 'Mulai' },
    { key: 'end_date', label: 'Selesai' },
    { key: 'is_active', label: 'Status' },
    { key: 'actions', label: '', className: 'w-40 text-right' },
];
</script>

<template>
    <Head title="Tahun Ajaran" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            title="Tahun Ajaran"
            description="Kelola tahun ajaran. Hanya satu tahun ajaran yang dapat aktif."
        >
            <template #actions>
                <Button
                    size="sm"
                    as-child
                    class="min-h-11 md:min-h-0"
                    data-test="create-academic-year"
                >
                    <Link :href="academicYears.create()">
                        <Plus />
                        Tambah
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <SectionTabs :tabs="masterTabs" label="Data master" />

        <AlertError
            v-if="serverErrors.length > 0"
            :errors="serverErrors"
            title="Data tidak dapat dihapus"
            class="mb-4"
        />

        <DataTable
            :columns="columns"
            :rows="years"
            :row-key="(year: AcademicYearRecord) => year.id"
            empty-title="Belum ada tahun ajaran"
            empty-description="Tambahkan tahun ajaran seperti 2026/2027 agar semester dan ruang kelas dapat dibuat."
        >
            <template #cell="{ row, column }">
                <template v-if="column.key === 'is_active'">
                    <Badge v-if="row.is_active" variant="secondary"
                        >Aktif</Badge
                    >
                    <span v-else class="text-muted-foreground">Nonaktif</span>
                </template>
                <div
                    v-else-if="column.key === 'actions'"
                    class="flex justify-end gap-1"
                >
                    <Button
                        variant="ghost"
                        size="sm"
                        as-child
                        class="min-h-11 md:min-h-0"
                    >
                        <Link :href="academicYears.edit.url(row.id)">Ubah</Link>
                    </Button>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="min-h-11 text-destructive hover:text-destructive md:min-h-0"
                                data-test="delete-academic-year"
                            >
                                Hapus
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="
                                    AcademicYearController.destroy.form({
                                        academicYear: row.id,
                                    })
                                "
                                :options="{ preserveScroll: true }"
                                class="space-y-6"
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle
                                        >Hapus tahun ajaran?</DialogTitle
                                    >
                                    <DialogDescription>
                                        {{ row.name }} akan dihapus dari daftar
                                        tahun ajaran.
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
                                        data-test="confirm-delete-academic-year"
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
    </div>
</template>
