<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import AcademicYearController from '@/actions/App/Http/Controllers/Admin/Master/AcademicYearController';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import academicYears from '@/routes/admin/master/academic-years';
import type { AcademicYearRecord } from '@/types/master';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Tahun Ajaran',
                href: academicYears.index(),
            },
        ],
    },
});

type Props = {
    year: AcademicYearRecord | null;
};

defineProps<Props>();
</script>

<template>
    <Head :title="year ? 'Ubah Tahun Ajaran' : 'Tambah Tahun Ajaran'" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            :title="year ? 'Ubah Tahun Ajaran' : 'Tambah Tahun Ajaran'"
        />

        <div class="max-w-2xl">
            <Form
                v-bind="
                    year
                        ? AcademicYearController.update.form({
                              academicYear: year.id,
                          })
                        : AcademicYearController.store.form()
                "
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Nama</Label>
                    <Input
                        id="name"
                        name="name"
                        :default-value="year?.name"
                        placeholder="2026/2027"
                        required
                        data-test="academic-year-name"
                    />
                    <InputError class="mt-2" :message="errors.name" />
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="start_date">Tanggal mulai</Label>
                        <Input
                            id="start_date"
                            type="date"
                            name="start_date"
                            :default-value="year?.start_date"
                            required
                            data-test="academic-year-start-date"
                        />
                        <InputError class="mt-2" :message="errors.start_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="end_date">Tanggal selesai</Label>
                        <Input
                            id="end_date"
                            type="date"
                            name="end_date"
                            :default-value="year?.end_date"
                            required
                            data-test="academic-year-end-date"
                        />
                        <InputError class="mt-2" :message="errors.end_date" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center gap-2">
                        <input
                            id="is_active"
                            type="checkbox"
                            name="is_active"
                            value="1"
                            :checked="year?.is_active"
                            class="size-4 rounded border-input accent-primary"
                            data-test="academic-year-active"
                        />
                        <Label for="is_active">Tahun ajaran aktif</Label>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Hanya satu tahun ajaran yang dapat aktif. Mengaktifkan
                        tahun ini otomatis menonaktifkan yang lain.
                    </p>
                    <InputError class="mt-2" :message="errors.is_active" />
                </div>

                <div class="flex items-center gap-4">
                    <Button
                        :disabled="processing"
                        class="min-h-11 md:min-h-0"
                        data-test="save-academic-year"
                    >
                        Simpan
                    </Button>
                    <Button
                        variant="outline"
                        as-child
                        class="min-h-11 md:min-h-0"
                    >
                        <Link :href="academicYears.index()">Batal</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </div>
</template>
