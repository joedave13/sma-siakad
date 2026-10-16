<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import LessonPeriodController from '@/actions/App/Http/Controllers/Admin/Master/LessonPeriodController';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import lessonPeriods from '@/routes/admin/master/lesson-periods';
import type { LessonPeriodRecord } from '@/types/master';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Jam Pelajaran',
                href: lessonPeriods.index(),
            },
        ],
    },
});

type Props = {
    period: LessonPeriodRecord | null;
};

defineProps<Props>();

const timeValue = (value?: string | null): string | undefined =>
    value?.slice(0, 5) ?? undefined;
</script>

<template>
    <Head :title="period ? 'Ubah Jam Pelajaran' : 'Tambah Jam Pelajaran'" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            :title="period ? 'Ubah Jam Pelajaran' : 'Tambah Jam Pelajaran'"
        />

        <div class="max-w-2xl">
            <Form
                v-bind="
                    period
                        ? LessonPeriodController.update.form({
                              lessonPeriod: period.id,
                          })
                        : LessonPeriodController.store.form()
                "
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="number">Nomor pelajaran</Label>
                    <Input
                        id="number"
                        type="number"
                        name="number"
                        min="1"
                        max="50"
                        :default-value="period?.number"
                        required
                        data-test="lesson-period-number"
                    />
                    <InputError class="mt-2" :message="errors.number" />
                </div>

                <div class="grid items-start gap-6 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="start_time">Jam mulai</Label>
                        <Input
                            id="start_time"
                            type="time"
                            name="start_time"
                            :default-value="timeValue(period?.start_time)"
                            required
                            data-test="lesson-period-start-time"
                        />
                        <InputError class="mt-2" :message="errors.start_time" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="end_time">Jam selesai</Label>
                        <Input
                            id="end_time"
                            type="time"
                            name="end_time"
                            :default-value="timeValue(period?.end_time)"
                            required
                            data-test="lesson-period-end-time"
                        />
                        <InputError class="mt-2" :message="errors.end_time" />
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <Button
                        :disabled="processing"
                        class="min-h-11 md:min-h-0"
                        data-test="save-lesson-period"
                    >
                        Simpan
                    </Button>
                    <Button
                        variant="outline"
                        as-child
                        class="min-h-11 md:min-h-0"
                    >
                        <Link :href="lessonPeriods.index()">Batal</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </div>
</template>
