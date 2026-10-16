<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Plus } from '@lucide/vue';
import TeacherAccountController from '@/actions/App/Http/Controllers/Admin/Accounts/TeacherAccountController';
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
import teachersRoutes from '@/routes/admin/users/teachers';
import { accountTabs } from './tabs';
import type { TeacherAccountRecord } from '@/types/accounts';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Guru',
                href: teachersRoutes.index(),
            },
        ],
    },
});

type Props = {
    teachers: TeacherAccountRecord[];
};

defineProps<Props>();

const page = usePage();
const serverErrors = computed(() =>
    Object.values((page.props.errors ?? {}) as Record<string, string>).filter(
        (message): message is string => typeof message === 'string',
    ),
);

const columns = [
    { key: 'nip', label: 'NIP / NUPTK' },
    { key: 'full_name', label: 'Nama' },
    { key: 'phone', label: 'Telepon' },
    { key: 'is_active', label: 'Akun' },
    { key: 'actions', label: '', className: 'w-56 text-right' },
];
</script>

<template>
    <Head title="Guru" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            title="Guru"
            description="Kelola akun guru: identitas NIP atau NUPTK, kontak, dan akses masuk."
        >
            <template #actions>
                <Button
                    size="sm"
                    as-child
                    class="min-h-11 md:min-h-0"
                    data-test="create-teacher"
                >
                    <Link :href="teachersRoutes.create()">
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
            :rows="teachers"
            :row-key="(teacher: TeacherAccountRecord) => teacher.id"
            empty-title="Belum ada akun guru"
            empty-description="Tambahkan akun guru agar penugasan mengajar dan wali kelas dapat ditautkan, atau impor massal lewat tab Impor."
        >
            <template #cell="{ row, column }">
                <Badge
                    v-if="column.key === 'is_active'"
                    :variant="row.is_active ? 'default' : 'secondary'"
                >
                    {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
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
                        <Link :href="teachersRoutes.edit.url(row.id)"
                            >Ubah</Link
                        >
                    </Button>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="min-h-11 md:min-h-0"
                                data-test="reset-teacher"
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
                                        Kata sandi akun {{ row.nip }} ({{
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
                                        data-test="confirm-reset-teacher"
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
                                data-test="delete-teacher"
                            >
                                Hapus
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="
                                    TeacherAccountController.destroy.form({
                                        teacher: row.id,
                                    })
                                "
                                :options="{ preserveScroll: true }"
                                class="space-y-6"
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>Hapus guru?</DialogTitle>
                                    <DialogDescription>
                                        {{ row.full_name }} ({{ row.nip }}) akan
                                        dihapus dari daftar guru dan akun
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
                                        data-test="confirm-delete-teacher"
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
