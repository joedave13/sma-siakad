<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Plus } from '@lucide/vue';
import ClassroomController from '@/actions/App/Http/Controllers/Admin/Master/ClassroomController';
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
import classroomsRoutes from '@/routes/admin/master/classrooms';
import { masterTabs } from './tabs';
import type { ClassroomRecord } from '@/types/master';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Ruang Kelas',
                href: classroomsRoutes.index(),
            },
        ],
    },
});

type Props = {
    classrooms: ClassroomRecord[];
};

defineProps<Props>();

const page = usePage();
const serverErrors = computed(() =>
    Object.values((page.props.errors ?? {}) as Record<string, string>).filter(
        (message): message is string => typeof message === 'string',
    ),
);

const majorLabels: Record<string, string> = {
    ipa: 'IPA',
    ips: 'IPS',
};

const columns = [
    { key: 'academic_year', label: 'Tahun Ajaran' },
    { key: 'name', label: 'Nama' },
    { key: 'grade_level', label: 'Tingkat' },
    { key: 'major', label: 'Jurusan' },
    { key: 'homeroom_teacher', label: 'Wali Kelas' },
    { key: 'actions', label: '', className: 'w-40 text-right' },
];
</script>

<template>
    <Head title="Ruang Kelas" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            title="Ruang Kelas"
            description="Kelola ruang kelas tiap tahun ajaran beserta walinya."
        >
            <template #actions>
                <Button
                    size="sm"
                    as-child
                    class="min-h-11 md:min-h-0"
                    data-test="create-classroom"
                >
                    <Link :href="classroomsRoutes.create()">
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
            :rows="classrooms"
            :row-key="(classroom: ClassroomRecord) => classroom.id"
            empty-title="Belum ada ruang kelas"
            empty-description="Tambahkan ruang kelas per tahun ajaran agar peserta didik dapat ditempatkan."
        >
            <template #cell="{ row, column }">
                <template v-if="column.key === 'academic_year'">
                    {{ row.academic_year?.name ?? '-' }}
                </template>
                <template v-else-if="column.key === 'major'">
                    {{
                        row.major ? (majorLabels[row.major] ?? row.major) : '-'
                    }}
                </template>
                <template v-else-if="column.key === 'homeroom_teacher'">
                    {{ row.homeroom_teacher?.full_name ?? '-' }}
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
                        <Link :href="classroomsRoutes.edit.url(row.id)"
                            >Ubah</Link
                        >
                    </Button>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="min-h-11 text-destructive hover:text-destructive md:min-h-0"
                                data-test="delete-classroom"
                            >
                                Hapus
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="
                                    ClassroomController.destroy.form({
                                        classroom: row.id,
                                    })
                                "
                                :options="{ preserveScroll: true }"
                                class="space-y-6"
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle
                                        >Hapus ruang kelas?</DialogTitle
                                    >
                                    <DialogDescription>
                                        {{ row.name }} pada tahun ajaran
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
                                        data-test="confirm-delete-classroom"
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
