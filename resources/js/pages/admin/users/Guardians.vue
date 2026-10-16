<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Plus } from '@lucide/vue';
import GuardianAccountController from '@/actions/App/Http/Controllers/Admin/Accounts/GuardianAccountController';
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
import guardiansRoutes from '@/routes/admin/users/guardians';
import { accountTabs } from './tabs';
import type { GuardianAccountRecord } from '@/types/accounts';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Orang Tua',
                href: guardiansRoutes.index(),
            },
        ],
    },
});

type Props = {
    guardians: GuardianAccountRecord[];
};

defineProps<Props>();

const page = usePage();
const serverErrors = computed(() =>
    Object.values((page.props.errors ?? {}) as Record<string, string>).filter(
        (message): message is string => typeof message === 'string',
    ),
);

const columns = [
    { key: 'telepon', label: 'Telepon' },
    { key: 'full_name', label: 'Nama' },
    { key: 'children', label: 'Anak' },
    { key: 'is_active', label: 'Akun' },
    { key: 'actions', label: '', className: 'w-56 text-right' },
];
</script>

<template>
    <Head title="Orang Tua" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            title="Orang Tua"
            description="Kelola akun orang tua: nomor telepon sebagai nama pengguna, tautan anak, dan akses masuk."
        >
            <template #actions>
                <Button
                    size="sm"
                    as-child
                    class="min-h-11 md:min-h-0"
                    data-test="create-guardian"
                >
                    <Link :href="guardiansRoutes.create()">
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
            :rows="guardians"
            :row-key="(guardian: GuardianAccountRecord) => guardian.id"
            empty-title="Belum ada akun orang tua"
            empty-description="Tambahkan akun orang tua lalu tautkan anaknya lewat halaman Ubah, atau impor massal lewat tab Impor."
        >
            <template #cell="{ row, column }">
                <span v-if="column.key === 'children'">
                    {{
                        row.children.length > 0
                            ? `${row.children.length} anak`
                            : 'Belum tertaut'
                    }}
                </span>
                <Badge
                    v-else-if="column.key === 'is_active'"
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
                        <Link :href="guardiansRoutes.edit.url(row.id)"
                            >Ubah</Link
                        >
                    </Button>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="min-h-11 md:min-h-0"
                                data-test="reset-guardian"
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
                                        Kata sandi akun {{ row.telepon }} ({{
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
                                        data-test="confirm-reset-guardian"
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
                                data-test="delete-guardian"
                            >
                                Hapus
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="
                                    GuardianAccountController.destroy.form({
                                        guardian: row.id,
                                    })
                                "
                                :options="{ preserveScroll: true }"
                                class="space-y-6"
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>Hapus orang tua?</DialogTitle>
                                    <DialogDescription>
                                        {{ row.full_name }} ({{ row.telepon }})
                                        akan dihapus dari daftar orang tua dan
                                        akun masuknya dinonaktifkan. Orang tua
                                        yang masih memiliki anak tertaut tidak
                                        dapat dihapus.
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
                                        data-test="confirm-delete-guardian"
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
