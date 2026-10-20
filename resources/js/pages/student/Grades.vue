<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import GradesView from '@/components/read/GradesView.vue';
import PageHeader from '@/components/PageHeader.vue';
import studentGradesRoutes from '@/routes/student/grades';
import type { GradesPayload } from '@/types/read';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Nilai', href: studentGradesRoutes.index() }],
    },
});

defineProps<{ grades: GradesPayload }>();
</script>

<template>
    <Head title="Nilai" />

    <PageHeader
        title="Nilai"
        description="Nilai akhir per mata pelajaran pada semester berjalan."
    />

    <GradesView :grades="grades" />
</template>
