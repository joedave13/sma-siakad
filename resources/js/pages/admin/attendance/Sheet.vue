<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import RosterSheet from '@/components/attendance/RosterSheet.vue';
import PageHeader from '@/components/PageHeader.vue';
import adminAttendanceRoutes from '@/routes/admin/attendance';
import { dashboard } from '@/routes';
import type { AttendanceSheetProps } from '@/types/attendance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

type Props = AttendanceSheetProps;

const props = defineProps<Props>();

const title = `Presensi Jam ke-${props.slot.period_number}`;
const description = `${props.slot.subject} · ${props.slot.classroom} · ${props.slot.start_time}–${props.slot.end_time} · ${props.date}`;
</script>

<template>
    <Head :title="title" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader :title="title" :description="description" />

        <RosterSheet
            :date="props.date"
            :slot="props.slot"
            :roster="props.roster"
            :session="props.session"
            :status-options="props.status_options"
            :submit-url="
                adminAttendanceRoutes.store.url(props.slot.schedule_id)
            "
        />
    </div>
</template>
