<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import ReportCardsView from '@/components/read/ReportCardsView.vue';
import studentReportCardRoutes from '@/routes/student/report-cards';
import type { ReportCardsPayload } from '@/types/read';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Rapor', href: studentReportCardRoutes.index() },
        ],
    },
});

defineProps<{ reportCards: ReportCardsPayload }>();
</script>

<template>
    <Head title="Rapor" />

    <PageHeader
        title="Rapor"
        description="Rapor semester yang sudah diterbitkan, siap diunduh."
    />

    <ReportCardsView :report-cards="reportCards" />
</template>
