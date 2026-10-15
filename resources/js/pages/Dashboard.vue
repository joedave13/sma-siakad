<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { UserRole } from '@/types';

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
            title: 'Sesi Presensi Belum Diisi',
            description:
                'Belum ada sesi presensi yang dikirim hari ini. Data muncul setelah guru mengirim presensi kelas.',
        },
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
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader title="Dashboard" :description="roleDescriptions[role]" />

        <div :class="gridClasses[role]">
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
