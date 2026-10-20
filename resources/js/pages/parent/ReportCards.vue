<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import ChildSwitcher from '@/components/read/ChildSwitcher.vue';
import NoChildrenNotice from '@/components/read/NoChildrenNotice.vue';
import ReportCardsView from '@/components/read/ReportCardsView.vue';
import guardianReportCardRoutes from '@/routes/guardian/report-cards';
import type { ChildOption, ReportCardsPayload } from '@/types/read';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Rapor Anak', href: guardianReportCardRoutes.index() },
        ],
    },
});

defineProps<{
    reportCards: ReportCardsPayload | null;
    children: ChildOption[];
    currentChildId: number | null;
}>();
</script>

<template>
    <Head title="Rapor Anak" />

    <PageHeader
        title="Rapor Anak"
        description="Rapor semester anak Anda yang sudah diterbitkan, siap diunduh."
    >
        <template #actions>
            <ChildSwitcher
                :children="children"
                :current-child-id="currentChildId"
            />
        </template>
    </PageHeader>

    <NoChildrenNotice v-if="reportCards === null" />

    <ReportCardsView v-else :report-cards="reportCards" />
</template>
