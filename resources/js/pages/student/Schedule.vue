<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import ScheduleView from '@/components/read/ScheduleView.vue';
import studentScheduleRoutes from '@/routes/student/schedule';
import type { SchedulePayload } from '@/types/read';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Jadwal', href: studentScheduleRoutes.index() }],
    },
});

defineProps<{ schedule: SchedulePayload }>();
</script>

<template>
    <Head title="Jadwal Pelajaran" />

    <PageHeader
        title="Jadwal Pelajaran"
        description="Jadwal pelajaran mingguan Anda pada semester berjalan."
    />

    <ScheduleView :schedule="schedule" />
</template>
