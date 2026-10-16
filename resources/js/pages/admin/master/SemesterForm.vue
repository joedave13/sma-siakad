<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import SemesterController from '@/actions/App/Http/Controllers/Admin/Master/SemesterController';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import semesters from '@/routes/admin/master/semesters';
import type { MasterOption, SemesterRecord } from '@/types/master';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Semester',
                href: semesters.index(),
            },
        ],
    },
});

type Props = {
    academicYears: MasterOption[];
    semester: SemesterRecord | null;
};

defineProps<Props>();

const terms = [
    { value: 'ganjil', label: 'Ganjil' },
    { value: 'genap', label: 'Genap' },
];
</script>

<template>
    <Head :title="semester ? 'Ubah Semester' : 'Tambah Semester'" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader :title="semester ? 'Ubah Semester' : 'Tambah Semester'" />

        <div class="max-w-2xl">
            <Form
                v-bind="
                    semester
                        ? SemesterController.update.form({
                              semester: semester.id,
                          })
                        : SemesterController.store.form()
                "
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="academic_year_id">Tahun ajaran</Label>
                    <select
                        id="academic_year_id"
                        name="academic_year_id"
                        :value="semester?.academic_year_id"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        required
                        data-test="semester-academic-year"
                    >
                        <option
                            v-for="year in academicYears"
                            :key="year.id"
                            :value="year.id"
                        >
                            {{ year.name }}
                        </option>
                    </select>
                    <InputError
                        class="mt-2"
                        :message="errors.academic_year_id"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="name">Nama</Label>
                    <select
                        id="name"
                        name="name"
                        :value="semester?.name"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        required
                        data-test="semester-name"
                    >
                        <option
                            v-for="term in terms"
                            :key="term.value"
                            :value="term.value"
                        >
                            {{ term.label }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="errors.name" />
                </div>

                <div class="grid items-start gap-6 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="start_date">Tanggal mulai</Label>
                        <Input
                            id="start_date"
                            type="date"
                            name="start_date"
                            :default-value="semester?.start_date ?? undefined"
                            data-test="semester-start-date"
                        />
                        <InputError class="mt-2" :message="errors.start_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="end_date">Tanggal selesai</Label>
                        <Input
                            id="end_date"
                            type="date"
                            name="end_date"
                            :default-value="semester?.end_date ?? undefined"
                            data-test="semester-end-date"
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
                            :checked="semester?.is_active"
                            class="size-4 rounded border-input accent-primary"
                            data-test="semester-active"
                        />
                        <Label for="is_active">Semester aktif</Label>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Hanya satu semester yang dapat aktif. Mengaktifkan
                        semester ini otomatis menonaktifkan yang lain.
                    </p>
                    <InputError class="mt-2" :message="errors.is_active" />
                </div>

                <div class="flex items-center gap-4">
                    <Button
                        :disabled="processing"
                        class="min-h-11 md:min-h-0"
                        data-test="save-semester"
                    >
                        Simpan
                    </Button>
                    <Button
                        variant="outline"
                        as-child
                        class="min-h-11 md:min-h-0"
                    >
                        <Link :href="semesters.index()">Batal</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </div>
</template>
