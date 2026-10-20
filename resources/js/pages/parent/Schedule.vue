<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import ChildSwitcher from '@/components/read/ChildSwitcher.vue';
import NoChildrenNotice from '@/components/read/NoChildrenNotice.vue';
import ScheduleView from '@/components/read/ScheduleView.vue';
import guardianScheduleRoutes from '@/routes/guardian/schedule';
import type { ChildOption, SchedulePayload } from '@/types/read';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Jadwal Anak', href: guardianScheduleRoutes.index() },
        ],
    },
});

defineProps<{
    schedule: SchedulePayload | null;
    children: ChildOption[];
    currentChildId: number | null;
}>();
</script>

<template>
    <Head title="Jadwal Anak" />

    <PageHeader
        title="Jadwal Anak"
        description="Jadwal pelajaran mingguan anak Anda pada semester berjalan."
    >
        <template #actions>
            <ChildSwitcher
                :children="children"
                :current-child-id="currentChildId"
            />
        </template>
    </PageHeader>

    <NoChildrenNotice v-if="schedule === null" />

    <ScheduleView v-else :schedule="schedule" />
</template>
