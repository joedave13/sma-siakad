<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import RosterSheet from '@/components/attendance/RosterSheet.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import attendanceRoutes from '@/routes/teacher/attendance';
import type { AttendanceSheetProps } from '@/types/attendance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Presensi',
                href: attendanceRoutes.index(),
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
        <PageHeader :title="title" :description="description">
            <template #actions>
                <Button
                    as-child
                    variant="outline"
                    size="sm"
                    class="min-h-11 md:min-h-0"
                >
                    <Link
                        :href="
                            attendanceRoutes.index.url({
                                query: { date: props.date },
                            })
                        "
                    >
                        Kembali ke daftar
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <RosterSheet
            :date="props.date"
            :slot="props.slot"
            :roster="props.roster"
            :session="props.session"
            :status-options="props.status_options"
            :submit-url="attendanceRoutes.store.url(props.slot.schedule_id)"
        />
    </div>
</template>
