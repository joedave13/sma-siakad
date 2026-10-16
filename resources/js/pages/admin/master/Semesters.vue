<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Plus } from '@lucide/vue';
import SemesterController from '@/actions/App/Http/Controllers/Admin/Master/SemesterController';
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
import semestersRoutes from '@/routes/admin/master/semesters';
import { masterTabs } from './tabs';
import type { SemesterRecord } from '@/types/master';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Semester',
                href: semestersRoutes.index(),
            },
        ],
    },
});

type Props = {
    semesters: SemesterRecord[];
};

defineProps<Props>();

const page = usePage();
const serverErrors = computed(() =>
    Object.values((page.props.errors ?? {}) as Record<string, string>).filter(
        (message): message is string => typeof message === 'string',
    ),
);

const termLabels: Record<string, string> = {
    ganjil: 'Ganjil',
    genap: 'Genap',
};

const columns = [
    { key: 'academic_year', label: 'Tahun Ajaran' },
    { key: 'name', label: 'Nama' },
    { key: 'start_date', label: 'Mulai' },
    { key: 'end_date', label: 'Selesai' },
    { key: 'is_active', label: 'Status' },
    { key: 'actions', label: '', className: 'w-40 text-right' },
];
</script>

<template>
    <Head title="Semester" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            title="Semester"
            description="Kelola semester ganjil dan genap tiap tahun ajaran. Hanya satu yang aktif."
        >
            <template #actions>
                <Button
                    size="sm"
                    as-child
                    class="min-h-11 md:min-h-0"
                    data-test="create-semester"
                >
                    <Link :href="semestersRoutes.create()">
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
            :rows="semesters"
            :row-key="(semester: SemesterRecord) => semester.id"
            empty-title="Belum ada semester"
            empty-description="Tambahkan semester ganjil atau genap pada tahun ajaran agar penugasan mengajar dapat dibuat."
        >
            <template #cell="{ row, column }">
                <template v-if="column.key === 'academic_year'">
                    {{ row.academic_year?.name ?? '-' }}
                </template>
                <template v-else-if="column.key === 'name'">
                    {{ termLabels[row.name] ?? row.name }}
                </template>
                <template v-else-if="column.key === 'start_date'">
                    {{ row.start_date ?? '-' }}
                </template>
                <template v-else-if="column.key === 'end_date'">
                    {{ row.end_date ?? '-' }}
                </template>
                <template v-else-if="column.key === 'is_active'">
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
                        <Link :href="semestersRoutes.edit.url(row.id)"
                            >Ubah</Link
                        >
                    </Button>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="min-h-11 text-destructive hover:text-destructive md:min-h-0"
                                data-test="delete-semester"
                            >
                                Hapus
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="
                                    SemesterController.destroy.form({
                                        semester: row.id,
                                    })
                                "
                                :options="{ preserveScroll: true }"
                                class="space-y-6"
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>Hapus semester?</DialogTitle>
                                    <DialogDescription>
                                        Semester
                                        {{ termLabels[row.name] ?? row.name }}
                                        pada tahun ajaran
                                        {{ row.academic_year?.name ?? '-' }}
                                        akan dihapus dari daftar.
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
                                        data-test="confirm-delete-semester"
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
