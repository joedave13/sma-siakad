<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AttendanceView from '@/components/read/AttendanceView.vue';
import PageHeader from '@/components/PageHeader.vue';
import studentAttendanceRoutes from '@/routes/student/attendance';
import type { AttendancePayload } from '@/types/read';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Presensi', href: studentAttendanceRoutes.index() },
        ],
    },
});

defineProps<{ attendance: AttendancePayload }>();
</script>

<template>
    <Head title="Presensi" />

    <PageHeader
        title="Presensi"
        description="Riwayat kehadiran harian dan per jam pelajaran Anda."
    />

    <AttendanceView :attendance="attendance" />
</template>
