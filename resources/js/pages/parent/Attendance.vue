<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AttendanceView from '@/components/read/AttendanceView.vue';
import PageHeader from '@/components/PageHeader.vue';
import ChildSwitcher from '@/components/read/ChildSwitcher.vue';
import NoChildrenNotice from '@/components/read/NoChildrenNotice.vue';
import guardianAttendanceRoutes from '@/routes/guardian/attendance';
import type { AttendancePayload, ChildOption } from '@/types/read';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Presensi Anak', href: guardianAttendanceRoutes.index() },
        ],
    },
});

defineProps<{
    attendance: AttendancePayload | null;
    children: ChildOption[];
    currentChildId: number | null;
}>();
</script>

<template>
    <Head title="Presensi Anak" />

    <PageHeader
        title="Presensi Anak"
        description="Riwayat kehadiran harian dan per jam pelajaran anak Anda."
    >
        <template #actions>
            <ChildSwitcher :children="children" :current-child-id="currentChildId" />
        </template>
    </PageHeader>

    <NoChildrenNotice v-if="attendance === null" />

    <AttendanceView v-else :attendance="attendance" />
</template>
