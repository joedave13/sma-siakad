<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Plus } from '@lucide/vue';
import LessonPeriodController from '@/actions/App/Http/Controllers/Admin/Master/LessonPeriodController';
import AlertError from '@/components/AlertError.vue';
import DataTable from '@/components/DataTable.vue';
import MasterSectionNav from '@/components/MasterSectionNav.vue';
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
import lessonPeriods from '@/routes/admin/master/lesson-periods';
import { masterTabs } from './tabs';
import type { LessonPeriodRecord } from '@/types/master';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Jam Pelajaran',
                href: lessonPeriods.index(),
            },
        ],
    },
});

type Props = {
    periods: LessonPeriodRecord[];
};

defineProps<Props>();

const page = usePage();
const serverErrors = computed(() =>
    Object.values((page.props.errors ?? {}) as Record<string, string>).filter(
        (message): message is string => typeof message === 'string',
    ),
);

const columns = [
    { key: 'number', label: 'Nomor' },
    { key: 'start_time', label: 'Mulai' },
    { key: 'end_time', label: 'Selesai' },
    { key: 'actions', label: '', className: 'w-40 text-right' },
];
</script>

<template>
    <Head title="Jam Pelajaran" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            title="Jam Pelajaran"
            description="Kelola jam pelajaran sebagai dasar penyusunan jadwal."
        >
            <template #actions>
                <Button
                    size="sm"
                    as-child
                    class="min-h-11 md:min-h-0"
                    data-test="create-lesson-period"
                >
                    <Link :href="lessonPeriods.create()">
                        <Plus />
                        Tambah
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <MasterSectionNav :tabs="masterTabs" />

        <AlertError
            v-if="serverErrors.length > 0"
            :errors="serverErrors"
            title="Data tidak dapat dihapus"
            class="mb-4"
        />

        <DataTable
            :columns="columns"
            :rows="periods"
            :row-key="(period: LessonPeriodRecord) => period.id"
            empty-title="Belum ada jam pelajaran"
            empty-description="Tambahkan jam pelajaran dengan nomor dan jam mulai agar jadwal mingguan dapat disusun."
        >
            <template #cell="{ row, column }">
                <template v-if="column.key === 'number'">
                    Pelajaran ke-{{ row.number }}
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
                        <Link :href="lessonPeriods.edit.url(row.id)">Ubah</Link>
                    </Button>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="min-h-11 text-destructive hover:text-destructive md:min-h-0"
                                data-test="delete-lesson-period"
                            >
                                Hapus
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="
                                    LessonPeriodController.destroy.form({
                                        lessonPeriod: row.id,
                                    })
                                "
                                :options="{ preserveScroll: true }"
                                class="space-y-6"
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle
                                        >Hapus jam pelajaran?</DialogTitle
                                    >
                                    <DialogDescription>
                                        Jam pelajaran ke-{{ row.number }} ({{
                                            row.start_time
                                        }}-{{ row.end_time }}) akan dihapus dari
                                        daftar.
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
                                        data-test="confirm-delete-lesson-period"
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
