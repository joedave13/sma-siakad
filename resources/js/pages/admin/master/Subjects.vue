<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Plus } from '@lucide/vue';
import SubjectController from '@/actions/App/Http/Controllers/Admin/Master/SubjectController';
import AlertError from '@/components/AlertError.vue';
import DataTable from '@/components/DataTable.vue';
import SectionTabs from '@/components/SectionTabs.vue';
import PageHeader from '@/components/PageHeader.vue';
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
import subjectsRoutes from '@/routes/admin/master/subjects';
import { masterTabs } from './tabs';
import type { SubjectRecord } from '@/types/master';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Mata Pelajaran',
                href: subjectsRoutes.index(),
            },
        ],
    },
});

type Props = {
    subjects: SubjectRecord[];
};

defineProps<Props>();

const page = usePage();
const serverErrors = computed(() =>
    Object.values((page.props.errors ?? {}) as Record<string, string>).filter(
        (message): message is string => typeof message === 'string',
    ),
);

const columns = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama' },
    { key: 'kkm', label: 'KKM' },
    { key: 'actions', label: '', className: 'w-40 text-right' },
];
</script>

<template>
    <Head title="Mata Pelajaran" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            title="Mata Pelajaran"
            description="Kelola mata pelajaran dan nilai KKM setiap pelajaran."
        >
            <template #actions>
                <Button
                    size="sm"
                    as-child
                    class="min-h-11 md:min-h-0"
                    data-test="create-subject"
                >
                    <Link :href="subjectsRoutes.create()">
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
            :rows="subjects"
            :row-key="(subject: SubjectRecord) => subject.id"
            empty-title="Belum ada mata pelajaran"
            empty-description="Tambahkan mata pelajaran beserta KKM agar nilai dapat diinput untuk penilaian akhir."
        >
            <template #cell="{ row, column }">
                <div
                    v-if="column.key === 'actions'"
                    class="flex justify-end gap-1"
                >
                    <Button
                        variant="ghost"
                        size="sm"
                        as-child
                        class="min-h-11 md:min-h-0"
                    >
                        <Link :href="subjectsRoutes.edit.url(row.id)"
                            >Ubah</Link
                        >
                    </Button>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="min-h-11 text-destructive hover:text-destructive md:min-h-0"
                                data-test="delete-subject"
                            >
                                Hapus
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="
                                    SubjectController.destroy.form({
                                        subject: row.id,
                                    })
                                "
                                :options="{ preserveScroll: true }"
                                class="space-y-6"
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle
                                        >Hapus mata pelajaran?</DialogTitle
                                    >
                                    <DialogDescription>
                                        {{ row.name }} ({{ row.code }}) akan
                                        dihapus dari daftar mata pelajaran.
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
                                        data-test="confirm-delete-subject"
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
