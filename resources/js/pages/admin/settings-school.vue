<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onUnmounted, watch, ref } from 'vue';
import SchoolProfileController from '@/actions/App/Http/Controllers/Admin/SchoolProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import schoolSettings from '@/routes/admin/school-settings';
import type { SchoolProfileRecord } from '@/types/settings';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pengaturan Sekolah',
                href: schoolSettings.edit(),
            },
        ],
    },
});

type Props = {
    profile: SchoolProfileRecord | null;
};

const props = defineProps<Props>();

const form = useForm({
    name: props.profile?.name ?? '',
    npsn: props.profile?.npsn ?? '',
    address: props.profile?.address ?? '',
    city: props.profile?.city ?? '',
    postal_code: props.profile?.postal_code ?? '',
    phone: props.profile?.phone ?? '',
    email: props.profile?.email ?? '',
    headmaster_name: props.profile?.headmaster_name ?? '',
    headmaster_nip: props.profile?.headmaster_nip ?? '',
    logo: null as File | null,
    headmaster_signature: null as File | null,
});

const submit = (): void => {
    form.put(SchoolProfileController.update.url(), { forceFormData: true });
};

const onFileChange = (
    field: 'logo' | 'headmaster_signature',
    event: Event,
): void => {
    const input = event.target as HTMLInputElement;
    form[field] = input.files?.[0] ?? null;
    form.clearErrors(field);
};

const objectUrls = ref<{ logo: string | null; signature: string | null }>({
    logo: null,
    signature: null,
});

watch(
    () => form.logo,
    (file) => {
        if (objectUrls.value.logo !== null) {
            URL.revokeObjectURL(objectUrls.value.logo);
        }
        objectUrls.value.logo =
            file === null ? null : URL.createObjectURL(file);
    },
);

watch(
    () => form.headmaster_signature,
    (file) => {
        if (objectUrls.value.signature !== null) {
            URL.revokeObjectURL(objectUrls.value.signature);
        }
        objectUrls.value.signature =
            file === null ? null : URL.createObjectURL(file);
    },
);

onUnmounted(() => {
    if (objectUrls.value.logo !== null) {
        URL.revokeObjectURL(objectUrls.value.logo);
    }
    if (objectUrls.value.signature !== null) {
        URL.revokeObjectURL(objectUrls.value.signature);
    }
});

const displayLogo = computed(
    () => objectUrls.value.logo ?? props.profile?.logo_url,
);
const displaySignature = computed(
    () => objectUrls.value.signature ?? props.profile?.signature_url,
);

const contactLine = computed(() =>
    [form.phone, form.email].filter(Boolean).join(' | '),
);

const todayLabel = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
}).format(new Date());
</script>

<template>
    <Head title="Pengaturan Sekolah" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            title="Pengaturan Sekolah"
            description="Identitas sekolah yang tampil di kop rapor dan dokumen resmi lainnya."
        />

        <div class="grid items-start gap-8 xl:grid-cols-2">
            <form
                class="max-w-2xl space-y-8"
                novalidate
                data-test="settings-form"
                @submit.prevent="submit"
            >
                <div class="space-y-4">
                    <Heading
                        title="Identitas Sekolah"
                        description="Data utama yang dicetak pada bagian kepala dokumen."
                        variant="small"
                    />

                    <div class="grid gap-2">
                        <Label for="name">
                            Nama sekolah
                            <span class="text-destructive" aria-hidden="true"
                                >*</span
                            >
                            <span class="sr-only">wajib diisi</span>
                        </Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="SMA Negeri 1 Contoh"
                            data-test="school-name"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="grid items-start gap-6 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="npsn">NPSN</Label>
                            <Input
                                id="npsn"
                                v-model="form.npsn"
                                placeholder="20123456"
                                data-test="school-npsn"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.npsn"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="postal_code">Kode pos</Label>
                            <Input
                                id="postal_code"
                                v-model="form.postal_code"
                                placeholder="40115"
                                data-test="school-postal"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.postal_code"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="address">
                            Alamat
                            <span class="text-destructive" aria-hidden="true"
                                >*</span
                            >
                            <span class="sr-only">wajib diisi</span>
                        </Label>
                        <Input
                            id="address"
                            v-model="form.address"
                            placeholder="Jalan Pendidikan No. 1"
                            data-test="school-address"
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.address"
                        />
                    </div>

                    <div class="grid items-start gap-6 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="city">
                                Kota
                                <span
                                    class="text-destructive"
                                    aria-hidden="true"
                                    >*</span
                                >
                                <span class="sr-only">wajib diisi</span>
                            </Label>
                            <Input
                                id="city"
                                v-model="form.city"
                                placeholder="Bandung"
                                data-test="school-city"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.city"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="phone">Telepon</Label>
                            <Input
                                id="phone"
                                v-model="form.phone"
                                placeholder="0221234567"
                                data-test="school-phone"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.phone"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            v-model="form.email"
                            placeholder="sekolah@example.sch.id"
                            data-test="school-email"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>
                </div>

                <div class="space-y-4">
                    <Heading
                        title="Kepala Sekolah"
                        description="Tampil pada blok tanda tangan rapor."
                        variant="small"
                    />

                    <div class="grid items-start gap-6 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="headmaster_name">
                                Nama kepala sekolah
                                <span
                                    class="text-destructive"
                                    aria-hidden="true"
                                    >*</span
                                >
                                <span class="sr-only">wajib diisi</span>
                            </Label>
                            <Input
                                id="headmaster_name"
                                v-model="form.headmaster_name"
                                placeholder="Budi Santoso"
                                data-test="headmaster-name"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.headmaster_name"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="headmaster_nip">
                                NIP kepala sekolah
                                <span
                                    class="text-destructive"
                                    aria-hidden="true"
                                    >*</span
                                >
                                <span class="sr-only">wajib diisi</span>
                            </Label>
                            <Input
                                id="headmaster_nip"
                                v-model="form.headmaster_nip"
                                placeholder="196001011985031001"
                                data-test="headmaster-nip"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.headmaster_nip"
                            />
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <Heading
                        title="Logo & Tanda Tangan"
                        description="Berkas yang diunggah diolah ulang di server sebelum disimpan."
                        variant="small"
                    />

                    <div class="grid gap-2">
                        <Label for="logo">
                            Logo
                            <span
                                v-if="!profile?.logo_url"
                                class="text-destructive"
                                aria-hidden="true"
                                >*</span
                            >
                            <span v-if="!profile?.logo_url" class="sr-only"
                                >wajib diisi</span
                            >
                        </Label>
                        <input
                            id="logo"
                            type="file"
                            accept=".png,.jpg,.jpeg,image/png,image/jpeg"
                            class="block w-full text-sm text-muted-foreground file:mr-4 file:rounded-md file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
                            data-test="logo-input"
                            @change="onFileChange('logo', $event)"
                        />
                        <p class="text-sm text-muted-foreground">
                            Format PNG atau JPG, maksimal 2 MB, minimal 200×200
                            piksel, otomatis dikecilkan menjadi maksimal 400
                            piksel.
                        </p>
                        <InputError class="mt-2" :message="form.errors.logo" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="headmaster_signature"
                            >Tanda tangan kepala sekolah</Label
                        >
                        <input
                            id="headmaster_signature"
                            type="file"
                            accept=".png,image/png"
                            class="block w-full text-sm text-muted-foreground file:mr-4 file:rounded-md file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
                            data-test="signature-input"
                            @change="
                                onFileChange('headmaster_signature', $event)
                            "
                        />
                        <p class="text-sm text-muted-foreground">
                            PNG transparan, maksimal 1 MB. Opsional, tampil di
                            blok tanda tangan kepala sekolah.
                        </p>
                        <InputError
                            class="mt-2"
                            :message="form.errors.headmaster_signature"
                        />
                    </div>

                    <div
                        v-if="displayLogo || displaySignature"
                        class="flex flex-wrap items-center gap-4 rounded-lg border border-border p-4"
                    >
                        <div v-if="displayLogo" class="flex items-center gap-3">
                            <img
                                :src="displayLogo"
                                alt="Logo sekolah saat ini"
                                class="h-14 w-14 object-contain"
                                data-test="current-logo"
                            />
                            <span class="text-sm text-muted-foreground"
                                >Logo saat ini</span
                            >
                        </div>
                        <div
                            v-if="displaySignature"
                            class="flex items-center gap-3"
                        >
                            <img
                                :src="displaySignature"
                                alt="Tanda tangan saat ini"
                                class="h-12 object-contain"
                                data-test="current-signature"
                            />
                            <span class="text-sm text-muted-foreground"
                                >Tanda tangan saat ini</span
                            >
                        </div>
                    </div>
                </div>

                <Button
                    type="submit"
                    :disabled="form.processing"
                    class="min-h-11 md:min-h-0"
                    data-test="save-settings"
                >
                    Simpan
                </Button>
            </form>

            <aside class="space-y-3 xl:sticky xl:top-4">
                <Heading
                    title="Pratinjau Rapor"
                    description="Kop rapor dan blok tanda tangan sesuai data yang diisi."
                    variant="small"
                />

                <div
                    class="overflow-hidden rounded-lg border border-border bg-white text-black shadow-xs"
                >
                    <div class="p-5 text-[11px] leading-snug">
                        <div
                            class="flex items-center gap-3 border-b-2 border-black pb-2"
                            data-test="preview-header"
                        >
                            <img
                                v-if="displayLogo"
                                :src="displayLogo"
                                alt="Logo"
                                class="h-[72px] w-[72px] shrink-0 object-contain"
                                data-test="preview-logo"
                            />
                            <div
                                v-else
                                class="flex h-[72px] w-[72px] shrink-0 items-center justify-center rounded border border-dashed border-black/30 text-[9px] text-black/50"
                            >
                                Logo
                            </div>
                            <div class="min-w-0">
                                <div class="text-[15px] font-bold">
                                    {{ form.name || 'Nama sekolah' }}
                                </div>
                                <div>
                                    {{ form.address || 'Alamat sekolah' }}
                                </div>
                                <div v-if="contactLine">
                                    {{ contactLine }}
                                </div>
                                <div v-if="form.npsn">
                                    NPSN: {{ form.npsn }}
                                </div>
                            </div>
                        </div>
                        <div
                            class="mt-2 text-center text-[13px] font-bold tracking-wide"
                        >
                            LAPORAN HASIL BELAJAR
                        </div>
                    </div>

                    <div
                        class="grid grid-cols-3 gap-2 px-5 pb-5 text-center text-[11px]"
                        data-test="preview-signature-block"
                    >
                        <div>
                            <div>Orang Tua/Wali</div>
                            <div class="h-14"></div>
                            <div>( &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; )</div>
                        </div>
                        <div>
                            <div>Wali Kelas</div>
                            <div class="h-14"></div>
                            <div>( ........................ )</div>
                        </div>
                        <div>
                            <div>
                                {{ form.city || 'Kota' }},
                                {{ todayLabel }}
                            </div>
                            <div class="mt-1">Kepala Sekolah</div>
                            <div class="flex h-14 items-end justify-center">
                                <img
                                    v-if="displaySignature"
                                    :src="displaySignature"
                                    alt="Tanda tangan"
                                    class="max-h-12 max-w-[140px] object-contain"
                                    data-test="preview-signature"
                                />
                            </div>
                            <div class="font-bold">
                                {{
                                    form.headmaster_name ||
                                    'Nama kepala sekolah'
                                }}
                            </div>
                            <div>NIP. {{ form.headmaster_nip || '-' }}</div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
