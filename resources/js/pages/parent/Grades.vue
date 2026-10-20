<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import ChildSwitcher from '@/components/read/ChildSwitcher.vue';
import GradesView from '@/components/read/GradesView.vue';
import NoChildrenNotice from '@/components/read/NoChildrenNotice.vue';
import guardianGradesRoutes from '@/routes/guardian/grades';
import type { ChildOption, GradesPayload } from '@/types/read';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Nilai Anak', href: guardianGradesRoutes.index() },
        ],
    },
});

defineProps<{
    grades: GradesPayload | null;
    children: ChildOption[];
    currentChildId: number | null;
}>();
</script>

<template>
    <Head title="Nilai Anak" />

    <PageHeader
        title="Nilai Anak"
        description="Nilai akhir per mata pelajaran anak Anda pada semester berjalan."
    >
        <template #actions>
            <ChildSwitcher
                :children="children"
                :current-child-id="currentChildId"
            />
        </template>
    </PageHeader>

    <NoChildrenNotice v-if="grades === null" />

    <GradesView v-else :grades="grades" />
</template>
