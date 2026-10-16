<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import GuardianAccountController from '@/actions/App/Http/Controllers/Admin/Accounts/GuardianAccountController';
import GuardianLinkController from '@/actions/App/Http/Controllers/Admin/Accounts/GuardianLinkController';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import guardiansRoutes from '@/routes/admin/users/guardians';
import studentsRoutes from '@/routes/admin/users/students';
import type { GuardianAccountRecord, StudentOption } from '@/types/accounts';

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
    guardian: GuardianAccountRecord | null;
    availableStudents: StudentOption[];
    totalStudents: number;
};

defineProps<Props>();

const relationships = ['Ayah', 'Ibu', 'Wali'];
</script>

<template>
    <Head :title="guardian ? 'Ubah Orang Tua' : 'Tambah Orang Tua'" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            :title="guardian ? 'Ubah Orang Tua' : 'Tambah Orang Tua'"
            description="Telepon dipakai sebagai nama pengguna akun masuk orang tua."
        />

        <div class="max-w-2xl space-y-8">
            <Form
                v-bind="
                    guardian
                        ? GuardianAccountController.update.form({
                              guardian: guardian.id,
                          })
                        : GuardianAccountController.store.form()
                "
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Nama lengkap</Label>
                    <Input
                        id="name"
                        name="name"
                        :default-value="guardian?.full_name"
                        placeholder="Budi Santoso"
                        required
                        data-test="guardian-name"
                    />
                    <InputError class="mt-2" :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="telepon">Telepon</Label>
                    <Input
                        id="telepon"
                        name="telepon"
                        :default-value="guardian?.telepon"
                        inputmode="numeric"
                        maxlength="15"
                        placeholder="6281234567890"
                        required
                        data-test="guardian-telepon"
                    />
                    <p class="text-sm text-muted-foreground">
                        Diawali 628 lalu 8 sampai 12 digit angka, menjadi nama
                        pengguna.
                    </p>
                    <InputError class="mt-2" :message="errors.telepon" />
                </div>

                <div v-if="guardian" class="grid gap-2">
                    <div class="flex items-center gap-2">
                        <input
                            id="is_active"
                            type="checkbox"
                            name="is_active"
                            value="1"
                            :checked="guardian.is_active"
                            class="size-4 rounded border-input accent-primary"
                            data-test="guardian-active"
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
                        data-test="save-guardian"
                    >
                        Simpan
                    </Button>
                    <Button
                        variant="outline"
                        as-child
                        class="min-h-11 md:min-h-0"
                    >
                        <Link :href="guardiansRoutes.index()">Batal</Link>
                    </Button>
                </div>
            </Form>

            <div v-if="guardian" class="space-y-6">
                <Heading
                    title="Anak tertaut"
                    description="Siswa yang terhubung ke akun orang tua ini. Anak yang tertaut menjadi syarat pemberitahuan absen."
                />

                <div
                    v-if="guardian.children.length > 0"
                    class="divide-y divide-border rounded-lg border border-border"
                >
                    <div
                        v-for="child in guardian.children"
                        :key="child.id"
                        class="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
                    >
                        <div>
                            <p class="text-sm font-medium">
                                {{ child.full_name }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ child.nisn }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge variant="outline">
                                {{ child.relationship }}
                            </Badge>
                            <Dialog>
                                <DialogTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="min-h-11 text-destructive hover:text-destructive md:min-h-0"
                                        data-test="unlink-student"
                                    >
                                        Lepas tautan
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <Form
                                        v-bind="
                                            GuardianLinkController.destroy.form(
                                                {
                                                    guardian: guardian.id,
                                                    student: child.id,
                                                },
                                            )
                                        "
                                        :options="{ preserveScroll: true }"
                                        class="space-y-6"
                                        v-slot="{ processing }"
                                    >
                                        <DialogHeader>
                                            <DialogTitle
                                                >Lepas tautan anak?</DialogTitle
                                            >
                                            <DialogDescription>
                                                {{ child.full_name }} ({{
                                                    child.nisn
                                                }}) tidak lagi tertaut ke
                                                {{ guardian.full_name }}.
                                                Siswanya tetap terdaftar.
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
                                                data-test="confirm-unlink-student"
                                            >
                                                Lepas tautan
                                            </Button>
                                        </DialogFooter>
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="totalStudents === 0"
                    class="rounded-lg border border-border"
                >
                    <EmptyState
                        title="Belum ada siswa terdaftar"
                        description="Tambahkan akun siswa terlebih dahulu, lalu tautkan anaknya dari halaman ini."
                    >
                        <template #actions>
                            <Button
                                size="sm"
                                variant="outline"
                                as-child
                                class="min-h-11 md:min-h-0"
                            >
                                <Link :href="studentsRoutes.index()"
                                    >Buka tab Siswa</Link
                                >
                            </Button>
                        </template>
                    </EmptyState>
                </div>

                <div v-else class="rounded-lg border border-border">
                    <EmptyState
                        title="Belum ada anak tertaut"
                        description="Pilih siswa pada form Tautan anak di bawah untuk menghubungkan akun ini dengan anaknya."
                    />
                </div>

                <div v-if="availableStudents.length > 0" class="space-y-4">
                    <Heading title="Tautan anak" variant="small" />
                    <Form
                        v-bind="
                            GuardianLinkController.store.form({
                                guardian: guardian.id,
                            })
                        "
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid items-start gap-6 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="student_id">Siswa</Label>
                                <select
                                    id="student_id"
                                    name="student_id"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    required
                                    data-test="link-student"
                                >
                                    <option value="" disabled selected>
                                        Pilih siswa
                                    </option>
                                    <option
                                        v-for="student in availableStudents"
                                        :key="student.id"
                                        :value="student.id"
                                    >
                                        {{ student.full_name }} ({{
                                            student.nisn
                                        }})
                                    </option>
                                </select>
                                <InputError
                                    class="mt-2"
                                    :message="errors.student_id"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="relationship">Hubungan</Label>
                                <select
                                    id="relationship"
                                    name="relationship"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    required
                                    data-test="link-relationship"
                                >
                                    <option value="" disabled selected>
                                        Pilih hubungan
                                    </option>
                                    <option
                                        v-for="relationship in relationships"
                                        :key="relationship"
                                        :value="relationship"
                                    >
                                        {{ relationship }}
                                    </option>
                                </select>
                                <InputError
                                    class="mt-2"
                                    :message="errors.relationship"
                                />
                            </div>
                        </div>

                        <Button
                            :disabled="processing"
                            class="min-h-11 md:min-h-0"
                            data-test="save-link"
                        >
                            Tautkan
                        </Button>
                    </Form>
                </div>

                <p
                    v-else-if="
                        guardian.children.length > 0 && totalStudents > 0
                    "
                    class="text-sm text-muted-foreground"
                >
                    Semua siswa sudah tertaut ke orang tua ini.
                </p>
            </div>
        </div>
    </div>
</template>
