<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Plus } from '@lucide/vue';
import StudentAccountController from '@/actions/App/Http/Controllers/Admin/Accounts/StudentAccountController';
import ResetUserPasswordController from '@/actions/App/Http/Controllers/Admin/ResetUserPasswordController';
import AlertError from '@/components/AlertError.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionTabs from '@/components/SectionTabs.vue';
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
import studentsRoutes from '@/routes/admin/users/students';
import { accountTabs } from './tabs';
import type { StudentAccountRecord } from '@/types/accounts';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Siswa',
                href: studentsRoutes.index(),
            },
        ],
    },
});

type Props = {
    students: StudentAccountRecord[];
};

defineProps<Props>();

const page = usePage();
const serverErrors = computed(() =>
    Object.values((page.props.errors ?? {}) as Record<string, string>).filter(
        (message): message is string => typeof message === 'string',
    ),
);

const columns = [
    { key: 'nisn', label: 'NISN' },
    { key: 'full_name', label: 'Nama' },
    { key: 'is_active', label: 'Akun' },
    { key: 'status', label: 'Status siswa' },
    { key: 'actions', label: '', className: 'w-56 text-right' },
];

const statusLabels: Record<StudentAccountRecord['status'], string> = {
    active: 'Aktif',
    graduated: 'Lulus',
    transferred: 'Pindah',
    dropped: 'Keluar',
};
</script>

<template>
    <Head title="Siswa" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            title="Siswa"
            description="Kelola akun siswa: identitas NISN, status keaktifan, dan akses masuk."
        >
            <template #actions>
                <Button
                    size="sm"
                    as-child
                    class="min-h-11 md:min-h-0"
                    data-test="create-student"
                >
                    <Link :href="studentsRoutes.create()">
                        <Plus />
                        Tambah
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <SectionTabs :tabs="accountTabs" label="Akun pengguna" />

        <AlertError
            v-if="serverErrors.length > 0"
            :errors="serverErrors"
            title="Data tidak dapat dihapus"
            class="mb-4"
        />

        <DataTable
            :columns="columns"
            :rows="students"
            :row-key="(student: StudentAccountRecord) => student.id"
            empty-title="Belum ada akun siswa"
            empty-description="Tambahkan akun siswa satu per satu, atau impor massal lewat tab Impor, agar presensi dan penilaian dapat ditautkan ke peserta didik."
        >
            <template #cell="{ row, column }">
                <Badge
                    v-if="column.key === 'is_active'"
                    :variant="row.is_active ? 'default' : 'secondary'"
                >
                    {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                </Badge>
                <Badge
                    v-else-if="column.key === 'status'"
                    :variant="row.status === 'active' ? 'outline' : 'default'"
                >
                    {{ statusLabels[row.status] }}
                </Badge>
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
                        <Link :href="studentsRoutes.edit.url(row.id)"
                            >Ubah</Link
                        >
                    </Button>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="min-h-11 md:min-h-0"
                                data-test="reset-student"
                            >
                                Ubah sandi
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="
                                    ResetUserPasswordController.store.form({
                                        user: row.user_id,
                                    })
                                "
                                :options="{ preserveScroll: true }"
                                class="space-y-6"
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle
                                        >Atur ulang kata sandi?</DialogTitle
                                    >
                                    <DialogDescription>
                                        Kata sandi akun {{ row.nisn }} ({{
                                            row.full_name
                                        }}) akan diganti acak dan ditampilkan
                                        satu kali. Wajib diganti lagi saat masuk
                                        berikutnya.
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
                                        data-test="confirm-reset-student"
                                    >
                                        Atur ulang
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="min-h-11 text-destructive hover:text-destructive md:min-h-0"
                                data-test="delete-student"
                            >
                                Hapus
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="
                                    StudentAccountController.destroy.form({
                                        student: row.id,
                                    })
                                "
                                :options="{ preserveScroll: true }"
                                class="space-y-6"
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>Hapus siswa?</DialogTitle>
                                    <DialogDescription>
                                        {{ row.full_name }} ({{ row.nisn }})
                                        akan dihapus dari daftar siswa dan akun
                                        masuknya dinonaktifkan.
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
                                        data-test="confirm-delete-student"
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
