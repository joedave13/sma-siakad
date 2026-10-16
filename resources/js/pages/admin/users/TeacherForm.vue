<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import TeacherAccountController from '@/actions/App/Http/Controllers/Admin/Accounts/TeacherAccountController';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import teachersRoutes from '@/routes/admin/users/teachers';
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
    teacher: TeacherAccountRecord | null;
};

defineProps<Props>();
</script>

<template>
    <Head :title="teacher ? 'Ubah Guru' : 'Tambah Guru'" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            :title="teacher ? 'Ubah Guru' : 'Tambah Guru'"
            description="NIP atau NUPTK dipakai sebagai nama pengguna akun masuk guru."
        />

        <div class="max-w-2xl">
            <Form
                v-bind="
                    teacher
                        ? TeacherAccountController.update.form({
                              teacher: teacher.id,
                          })
                        : TeacherAccountController.store.form()
                "
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Nama lengkap</Label>
                    <Input
                        id="name"
                        name="name"
                        :default-value="teacher?.full_name"
                        placeholder="Ahmad Dani"
                        required
                        data-test="teacher-name"
                    />
                    <InputError class="mt-2" :message="errors.name" />
                </div>

                <div class="grid items-start gap-6 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="nip">NIP / NUPTK</Label>
                        <Input
                            id="nip"
                            name="nip"
                            :default-value="teacher?.nip"
                            inputmode="numeric"
                            maxlength="18"
                            placeholder="18 digit NIP atau 16 digit NUPTK"
                            required
                            data-test="teacher-nip"
                        />
                        <p class="text-sm text-muted-foreground">
                            18 digit angka (NIP) atau 16 digit (NUPTK), menjadi
                            nama pengguna.
                        </p>
                        <InputError class="mt-2" :message="errors.nip" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="phone">Telepon</Label>
                        <Input
                            id="phone"
                            name="phone"
                            :default-value="teacher?.phone ?? ''"
                            maxlength="30"
                            placeholder="Opsional"
                            data-test="teacher-phone"
                        />
                        <InputError class="mt-2" :message="errors.phone" />
                    </div>
                </div>

                <div v-if="teacher" class="grid gap-2">
                    <div class="flex items-center gap-2">
                        <input
                            id="is_active"
                            type="checkbox"
                            name="is_active"
                            value="1"
                            :checked="teacher.is_active"
                            class="size-4 rounded border-input accent-primary"
                            data-test="teacher-active"
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
                        data-test="save-teacher"
                    >
                        Simpan
                    </Button>
                    <Button
                        variant="outline"
                        as-child
                        class="min-h-11 md:min-h-0"
                    >
                        <Link :href="teachersRoutes.index()">Batal</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </div>
</template>
