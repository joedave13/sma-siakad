<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AccountImportController from '@/actions/App/Http/Controllers/Admin/Accounts/AccountImportController';
import AlertError from '@/components/AlertError.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionTabs from '@/components/SectionTabs.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { accountTabs } from './tabs';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Impor Akun',
                href: AccountImportController.create(),
            },
        ],
    },
});

type ImportType = 'student' | 'teacher' | 'parent';

const page = usePage();

const importType = ref<ImportType>('student');

const importErrors = computed(
    () => page.flash.importErrors ?? ([] as string[]),
);

const typeLabels: Record<ImportType, string> = {
    student: 'Siswa',
    teacher: 'Guru',
    parent: 'Orang Tua',
};

const typeDocs: Record<
    ImportType,
    { required: string; optional: string; notes: string[] }
> = {
    student: {
        required: 'nisn, nama, jenis_kelamin',
        optional: 'nis, tanggal_lahir, tempat_lahir, alamat, status',
        notes: [
            'nisn: 10 digit angka, unik, menjadi nama pengguna.',
            'jenis_kelamin: Laki-laki atau Perempuan (huruf awal L atau P juga diterima).',
            'tanggal_lahir: format YYYY-MM-DD atau DD/MM/YYYY.',
            'status: active, graduated, transferred, atau dropped. Jika kolom dihapus, statusnya Aktif.',
        ],
    },
    teacher: {
        required: 'nip, nama',
        optional: 'telepon',
        notes: [
            'nip: 18 digit angka (NIP) atau 16 digit (NUPTK), unik, menjadi nama pengguna.',
        ],
    },
    parent: {
        required: 'telepon, nama',
        optional: '',
        notes: [
            'telepon: diawali 628 lalu 8 sampai 12 digit angka, unik, menjadi nama pengguna.',
        ],
    },
};

const sharedNotes = [
    'Baris 1 adalah judul kolom, ditulis huruf kecil tanpa spasi.',
    'Di Excel, format kolom nip, nisn, dan telepon sebagai Teks agar angka tidak berubah.',
    'Nilai di dalam berkas harus unik, dan tidak boleh sudah terdaftar di aplikasi.',
    'Satu baris bermasalah membatalkan seluruh impor, tidak ada data yang ditambahkan.',
];
</script>

<template>
    <Head title="Impor Akun" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            title="Impor Akun"
            description="Unggah berkas CSV atau XLSX berisi data siswa, guru, atau orang tua dalam satu kali impor."
        />

        <SectionTabs :tabs="accountTabs" label="Akun pengguna" />

        <AlertError
            v-if="importErrors.length > 0"
            :errors="importErrors"
            title="Impor dibatalkan, tidak ada data yang ditambahkan"
            class="mb-6"
        />

        <div class="max-w-2xl space-y-8">
            <Form
                v-bind="AccountImportController.store.form()"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="type">Jenis akun</Label>
                    <select
                        id="type"
                        name="type"
                        v-model="importType"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        required
                        data-test="import-type"
                    >
                        <option value="student">Siswa</option>
                        <option value="teacher">Guru</option>
                        <option value="parent">Orang Tua</option>
                    </select>
                    <InputError class="mt-2" :message="errors.type" />
                </div>

                <div class="grid gap-2">
                    <Label for="file">Berkas</Label>
                    <input
                        id="file"
                        type="file"
                        name="file"
                        accept=".csv,.xlsx"
                        required
                        class="block w-full text-sm text-muted-foreground file:mr-4 file:rounded-md file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
                        data-test="import-file"
                    />
                    <p class="text-sm text-muted-foreground">
                        Format CSV atau XLSX, ukuran maksimal 2 MB.
                    </p>
                    <InputError class="mt-2" :message="errors.file" />
                </div>

                <Button
                    :disabled="processing"
                    class="min-h-11 md:min-h-0"
                    data-test="submit-import"
                >
                    Impor
                </Button>
            </Form>

            <div class="space-y-4">
                <Heading
                    :title="`Format berkas: ${typeLabels[importType]}`"
                    description="Pastikan judul kolom sesuai sebelum mengunggah."
                    variant="small"
                />

                <div class="space-y-3 rounded-lg border border-border p-4">
                    <p class="text-sm">
                        <span class="font-medium">Kolom wajib:</span>
                        {{ typeDocs[importType].required }}
                    </p>
                    <p v-if="typeDocs[importType].optional" class="text-sm">
                        <span class="font-medium">Kolom opsional:</span>
                        {{ typeDocs[importType].optional }}
                    </p>
                    <ul
                        class="list-inside list-disc space-y-1 text-sm text-muted-foreground"
                    >
                        <li
                            v-for="note in typeDocs[importType].notes"
                            :key="note"
                        >
                            {{ note }}
                        </li>
                        <li v-for="note in sharedNotes" :key="note">
                            {{ note }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
