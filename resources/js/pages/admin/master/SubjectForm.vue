<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import SubjectController from '@/actions/App/Http/Controllers/Admin/Master/SubjectController';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import subjects from '@/routes/admin/master/subjects';
import type { SubjectRecord } from '@/types/master';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Mata Pelajaran',
                href: subjects.index(),
            },
        ],
    },
});

type Props = {
    subject: SubjectRecord | null;
};

defineProps<Props>();
</script>

<template>
    <Head :title="subject ? 'Ubah Mata Pelajaran' : 'Tambah Mata Pelajaran'" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            :title="subject ? 'Ubah Mata Pelajaran' : 'Tambah Mata Pelajaran'"
        />

        <div class="max-w-2xl">
            <Form
                v-bind="
                    subject
                        ? SubjectController.update.form({
                              subject: subject.id,
                          })
                        : SubjectController.store.form()
                "
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid items-start gap-6 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="code">Kode</Label>
                        <Input
                            id="code"
                            name="code"
                            :default-value="subject?.code"
                            placeholder="MTK"
                            required
                            data-test="subject-code"
                        />
                        <InputError class="mt-2" :message="errors.code" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="kkm">KKM</Label>
                        <Input
                            id="kkm"
                            type="number"
                            name="kkm"
                            min="0"
                            max="100"
                            :default-value="subject?.kkm"
                            required
                            data-test="subject-kkm"
                        />
                        <InputError class="mt-2" :message="errors.kkm" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="name">Nama</Label>
                    <Input
                        id="name"
                        name="name"
                        :default-value="subject?.name"
                        placeholder="Matematika"
                        required
                        data-test="subject-name"
                    />
                    <InputError class="mt-2" :message="errors.name" />
                </div>

                <div class="flex items-center gap-4">
                    <Button
                        :disabled="processing"
                        class="min-h-11 md:min-h-0"
                        data-test="save-subject"
                    >
                        Simpan
                    </Button>
                    <Button
                        variant="outline"
                        as-child
                        class="min-h-11 md:min-h-0"
                    >
                        <Link :href="subjects.index()">Batal</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </div>
</template>
