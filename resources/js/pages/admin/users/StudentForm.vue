<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import StudentAccountController from '@/actions/App/Http/Controllers/Admin/Accounts/StudentAccountController';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import studentsRoutes from '@/routes/admin/users/students';
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
    student: StudentAccountRecord | null;
};

defineProps<Props>();

const genders = [
    { value: 'male', label: 'Laki-laki' },
    { value: 'female', label: 'Perempuan' },
];

const statuses = [
    { value: 'active', label: 'Aktif' },
    { value: 'graduated', label: 'Lulus' },
    { value: 'transferred', label: 'Pindah' },
    { value: 'dropped', label: 'Keluar' },
];
</script>

<template>
    <Head :title="student ? 'Ubah Siswa' : 'Tambah Siswa'" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            :title="student ? 'Ubah Siswa' : 'Tambah Siswa'"
            description="NISN dipakai sebagai nama pengguna akun masuk siswa."
        />

        <div class="max-w-2xl">
            <Form
                v-bind="
                    student
                        ? StudentAccountController.update.form({
                              student: student.id,
                          })
                        : StudentAccountController.store.form()
                "
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Nama lengkap</Label>
                    <Input
                        id="name"
                        name="name"
                        :default-value="student?.full_name"
                        placeholder="Siti Nurhaliza"
                        required
                        data-test="student-name"
                    />
                    <InputError class="mt-2" :message="errors.name" />
                </div>

                <div class="grid items-start gap-6 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="nisn">NISN</Label>
                        <Input
                            id="nisn"
                            name="nisn"
                            :default-value="student?.nisn"
                            inputmode="numeric"
                            maxlength="10"
                            placeholder="10 digit angka"
                            required
                            data-test="student-nisn"
                        />
                        <p class="text-sm text-muted-foreground">
                            10 digit angka, menjadi nama pengguna.
                        </p>
                        <InputError class="mt-2" :message="errors.nisn" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="nis">NIS</Label>
                        <Input
                            id="nis"
                            name="nis"
                            :default-value="student?.nis ?? ''"
                            maxlength="20"
                            placeholder="Opsional"
                            data-test="student-nis"
                        />
                        <InputError class="mt-2" :message="errors.nis" />
                    </div>
                </div>

                <div class="grid items-start gap-6 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="gender">Jenis kelamin</Label>
                        <select
                            id="gender"
                            name="gender"
                            :value="student?.gender ?? ''"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            required
                            data-test="student-gender"
                        >
                            <option value="" disabled>
                                Pilih jenis kelamin
                            </option>
                            <option
                                v-for="gender in genders"
                                :key="gender.value"
                                :value="gender.value"
                            >
                                {{ gender.label }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="errors.gender" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="status">Status siswa</Label>
                        <select
                            id="status"
                            name="status"
                            :value="student?.status ?? 'active'"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            required
                            data-test="student-status"
                        >
                            <option
                                v-for="status in statuses"
                                :key="status.value"
                                :value="status.value"
                            >
                                {{ status.label }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="errors.status" />
                    </div>
                </div>

                <div class="grid items-start gap-6 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="birth_place">Tempat lahir</Label>
                        <Input
                            id="birth_place"
                            name="birth_place"
                            :default-value="student?.birth_place ?? ''"
                            placeholder="Opsional"
                            data-test="student-birth-place"
                        />
                        <InputError
                            class="mt-2"
                            :message="errors.birth_place"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="birth_date">Tanggal lahir</Label>
                        <Input
                            id="birth_date"
                            type="date"
                            name="birth_date"
                            :default-value="student?.birth_date ?? ''"
                            data-test="student-birth-date"
                        />
                        <InputError class="mt-2" :message="errors.birth_date" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="address">Alamat</Label>
                    <Input
                        id="address"
                        name="address"
                        :default-value="student?.address ?? ''"
                        placeholder="Opsional"
                        data-test="student-address"
                    />
                    <InputError class="mt-2" :message="errors.address" />
                </div>

                <div v-if="student" class="grid gap-2">
                    <div class="flex items-center gap-2">
                        <input
                            id="is_active"
                            type="checkbox"
                            name="is_active"
                            value="1"
                            :checked="student.is_active"
                            class="size-4 rounded border-input accent-primary"
                            data-test="student-active"
                        />
                        <Label for="is_active">Akun masuk aktif</Label>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Akun yang dinonaktifkan tidak dapat masuk ke aplikasi.
                    </p>
                    <InputError class="mt-2" :message="errors.is_active" />
                </div>

                <div class="flex items-center gap-4">
                    <Button
                        :disabled="processing"
                        class="min-h-11 md:min-h-0"
                        data-test="save-student"
                    >
                        Simpan
                    </Button>
                    <Button
                        variant="outline"
                        as-child
                        class="min-h-11 md:min-h-0"
                    >
                        <Link :href="studentsRoutes.index()">Batal</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </div>
</template>
