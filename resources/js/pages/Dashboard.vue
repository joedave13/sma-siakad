<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import adminAttendanceRoutes from '@/routes/admin/attendance';
import { dashboard } from '@/routes';
import type { UserRole } from '@/types';
import type { MissingSession } from '@/types/attendance';

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

type DashboardCard = {
    title: string;
    description: string;
};

type Props = {
    missingSessions: MissingSession[] | null;
};

const props = defineProps<Props>();

const roleDescriptions: Record<UserRole, string> = {
    admin: 'Pantau presensi, nilai, dan penerbitan rapor.',
    teacher: 'Jadwal, presensi, dan nilai kelas Anda.',
    student: 'Jadwal, presensi, nilai, dan rapor Anda.',
    parent: 'Presensi, nilai, dan rapor anak Anda.',
};

// TODO: wire these cards to the real per-role queries (STEPS.md step 23).
const cardsByRole: Record<UserRole, DashboardCard[]> = {
    admin: [
        {
            title: 'Nilai di Bawah KKM',
            description: 'Belum ada nilai yang diproses untuk penilaian akhir.',
        },
        {
            title: 'Rapor Belum Terbit',
            description: 'Belum ada rapor yang menunggu penerbitan.',
        },
    ],
    teacher: [
        {
            title: 'Jadwal Mengajar Hari Ini',
            description: 'Belum ada jadwal mengajar hari ini.',
        },
        {
            title: 'Presensi Belum Dikirim',
            description: 'Belum ada sesi presensi yang menunggu pengisian.',
        },
    ],
    student: [
        {
            title: 'Rapor Terbaru',
            description:
                'Belum ada rapor terbit. Rapor tampil setelah nilai semester ini lengkap dan diterbitkan.',
        },
    ],
    parent: [
        {
            title: 'Rapor Anak Terbaru',
            description:
                'Belum ada rapor anak yang diterbitkan. Rapor tampil setelah nilai semester ini lengkap dan diterbitkan.',
        },
        {
            title: 'Ketidakhadiran Anak',
            description: 'Belum ada catatan ketidakhadiran anak.',
        },
    ],
};

const gridClasses: Record<UserRole, string> = {
    admin: 'grid gap-4 md:grid-cols-3',
    teacher: 'grid gap-4 md:grid-cols-2',
    student: 'grid gap-4 max-w-2xl',
    parent: 'grid gap-4 md:grid-cols-2',
};

const page = usePage();
const role = computed<UserRole>(() => page.props.auth.user?.role ?? 'student');
const cards = computed(() => cardsByRole[role.value]);
const missingSessions = computed(() => props.missingSessions ?? []);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader title="Dashboard" :description="roleDescriptions[role]" />

        <div :class="gridClasses[role]">
            <Card v-if="role === 'admin'" data-test="missing-sessions">
                <CardHeader>
                    <CardTitle class="text-base">
                        Sesi Presensi Belum Diisi
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <ul v-if="missingSessions.length > 0" class="space-y-3">
                        <li
                            v-for="session in missingSessions"
                            :key="session.schedule_id"
                            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            :data-test="`missing-session-${session.schedule_id}`"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    Jam ke-{{ session.period_number }} ({{
                                        session.start_time
                                    }}–{{ session.end_time }})
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ session.subject }} ·
                                    {{ session.classroom }} ·
                                    {{ session.teacher }}
                                </p>
                            </div>
                            <Button
                                as-child
                                size="sm"
                                class="min-h-11 md:min-h-0"
                            >
                                <Link
                                    :href="
                                        adminAttendanceRoutes.show.url(
                                            session.schedule_id,
                                            { query: { date: session.date } },
                                        )
                                    "
                                >
                                    Isi presensi
                                </Link>
                            </Button>
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        data-test="missing-sessions-empty"
                        description="Belum ada jadwal hari ini yang belum mengirim presensi."
                    />
                </CardContent>
            </Card>

            <Card v-for="card in cards" :key="card.title">
                <CardHeader>
                    <CardTitle class="text-base">{{ card.title }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <EmptyState :description="card.description" />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
