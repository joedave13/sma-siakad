<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminDashboard from '@/components/dashboard/AdminDashboard.vue';
import ParentDashboard from '@/components/dashboard/ParentDashboard.vue';
import StudentDashboard from '@/components/dashboard/StudentDashboard.vue';
import TeacherDashboard from '@/components/dashboard/TeacherDashboard.vue';
import PageHeader from '@/components/PageHeader.vue';
import { dashboard } from '@/routes';
import type { UserRole } from '@/types';
import type { MissingSession } from '@/types/attendance';
import type {
    AbsenceRow,
    BelowKkmPayload,
    LatestReportCardPayload,
    PendingSubmission,
    TodayPeriod,
    UnpublishedReportCardsPayload,
} from '@/types/dashboard';

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

type Props = {
    missingSessions: MissingSession[] | null;
    belowKkm: BelowKkmPayload | null;
    unpublishedReportCards: UnpublishedReportCardsPayload | null;
    todayPeriods: TodayPeriod[] | null;
    pendingSubmissions: PendingSubmission[] | null;
    latestReportCard: LatestReportCardPayload | null;
    recentAbsences: AbsenceRow[] | null;
    childCount: number | null;
    currentChildName: string | null;
};

const props = defineProps<Props>();

const roleDescriptions: Record<UserRole, string> = {
    admin: 'Pantau presensi, nilai, dan penerbitan rapor.',
    teacher: 'Jadwal, presensi, dan nilai kelas Anda.',
    student: 'Jadwal, presensi, nilai, dan rapor Anda.',
    parent: 'Presensi, nilai, dan rapor anak Anda.',
};

const page = usePage();
const role = computed<UserRole>(() => page.props.auth.user?.role ?? 'student');
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader title="Dashboard" :description="roleDescriptions[role]" />

        <AdminDashboard
            v-if="
                role === 'admin' &&
                props.missingSessions !== null &&
                props.belowKkm !== null &&
                props.unpublishedReportCards !== null
            "
            :missing-sessions="props.missingSessions"
            :below-kkm="props.belowKkm"
            :unpublished-report-cards="props.unpublishedReportCards"
        />
        <TeacherDashboard
            v-else-if="
                role === 'teacher' &&
                props.todayPeriods !== null &&
                props.pendingSubmissions !== null
            "
            :today-periods="props.todayPeriods"
            :pending-submissions="props.pendingSubmissions"
        />
        <StudentDashboard
            v-else-if="role === 'student'"
            :latest-report-card="props.latestReportCard"
        />
        <ParentDashboard
            v-else-if="role === 'parent'"
            :latest-report-card="props.latestReportCard"
            :recent-absences="props.recentAbsences ?? []"
            :child-count="props.childCount ?? 0"
            :current-child-name="props.currentChildName"
        />
    </div>
</template>
