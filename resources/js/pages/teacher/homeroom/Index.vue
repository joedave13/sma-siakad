<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import homeroomRoutes from '@/routes/teacher/homeroom';
import type { HomeroomIndexProps } from '@/types/grades';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Wali Kelas',
                href: homeroomRoutes.index(),
            },
        ],
    },
});

type Props = HomeroomIndexProps;

defineProps<Props>();
</script>

<template>
    <Head title="Wali Kelas" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            title="Wali Kelas"
            description="Daftar kelas yang Anda dampingi beserta status kesiapan rapornya."
        />

        <div
            v-if="profileMissing"
            data-test="homeroom-profile-missing"
            class="rounded-md border p-6"
        >
            <EmptyState
                title="Profil guru tidak ditemukan"
                description="Akun Anda belum terhubung ke profil guru. Hubungi admin untuk menghubungkan akun Anda ke data guru."
            />
        </div>

        <div
            v-else-if="noActiveYear"
            data-test="homeroom-no-year"
            class="rounded-md border p-6"
        >
            <EmptyState
                title="Belum ada tahun ajaran aktif"
                description="Admin perlu mengaktifkan tahun ajaran terlebih dahulu agar daftar kelas wali kelas dapat ditampilkan."
            />
        </div>

        <div
            v-else-if="noActiveSemester"
            data-test="homeroom-no-semester"
            class="rounded-md border p-6"
        >
            <EmptyState
                title="Belum ada semester aktif"
                description="Admin perlu mengaktifkan semester terlebih dahulu untuk melihat kesiapan rapor kelas."
            />
        </div>

        <div
            v-else-if="classrooms.length === 0"
            data-test="homeroom-empty"
            class="rounded-md border p-6"
        >
            <EmptyState
                title="Belum ada kelas wali kelas"
                description="Akun Anda belum ditugaskan sebagai wali kelas mana pun. Hubungi admin untuk penugasan wali kelas."
            />
        </div>

        <ul v-else class="space-y-3" data-test="homeroom-classrooms">
            <li
                v-for="classroom in classrooms"
                :key="classroom.id"
                class="flex flex-col gap-3 rounded-md border p-4 sm:flex-row sm:items-center sm:justify-between"
                :data-test="`homeroom-class-${classroom.id}`"
            >
                <div class="min-w-0">
                    <p class="text-sm font-medium">
                        Kelas {{ classroom.grade_level }} ·
                        {{ classroom.name }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ classroom.student_count }} siswa
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Badge
                        :variant="classroom.ready ? 'secondary' : 'outline'"
                        :data-test="`homeroom-ready-state-${classroom.id}`"
                    >
                        {{
                            classroom.ready
                                ? 'Ditandai siap'
                                : 'Belum ditandai siap'
                        }}
                    </Badge>
                    <Button
                        as-child
                        size="sm"
                        class="min-h-11 md:min-h-0"
                        :data-test="`homeroom-open-${classroom.id}`"
                    >
                        <Link :href="homeroomRoutes.show.url(classroom.id)">
                            Buka
                        </Link>
                    </Button>
                </div>
            </li>
        </ul>
    </div>
</template>
