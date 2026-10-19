<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import gradesRoutes from '@/routes/teacher/grades';
import type { GradesIndexProps } from '@/types/grades';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Nilai',
                href: gradesRoutes.index(),
            },
        ],
    },
});

type Props = GradesIndexProps;

defineProps<Props>();
</script>

<template>
    <Head title="Nilai" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            title="Nilai"
            description="Daftar tugas mengajar beserta akses pengisian nilai pada semester berjalan."
        />

        <div
            v-if="profileMissing"
            data-test="grades-profile-missing"
            class="rounded-md border p-6"
        >
            <EmptyState
                title="Profil guru tidak ditemukan"
                description="Akun Anda belum terhubung ke profil guru. Hubungi admin untuk menghubungkan akun Anda ke data guru."
            />
        </div>

        <div
            v-else-if="semester === null"
            data-test="grades-no-semester"
            class="rounded-md border p-6"
        >
            <EmptyState
                title="Belum ada semester aktif"
                description="Admin perlu mengaktifkan semester terlebih dahulu agar nilai dapat diinput."
            />
        </div>

        <div
            v-else-if="assignments.length === 0"
            data-test="grades-empty"
            class="rounded-md border p-6"
        >
            <EmptyState
                title="Belum ada tugas mengajar"
                description="Anda belum memiliki tugas mengajar pada semester ini. Hubungi admin untuk penugasan mengajar."
            />
        </div>

        <template v-else>
            <p
                class="mb-4 text-sm text-muted-foreground"
                data-test="grades-semester"
            >
                {{ semester.name }} · {{ semester.academic_year }} ·
                {{ assignments.length }} tugas mengajar
            </p>

            <ul class="space-y-3" data-test="grades-assignments">
                <li
                    v-for="assignment in assignments"
                    :key="assignment.id"
                    class="flex flex-col gap-3 rounded-md border p-4 sm:flex-row sm:items-center sm:justify-between"
                    :data-test="`grades-assignment-${assignment.id}`"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-medium">
                            {{ assignment.subject }} ·
                            {{ assignment.classroom }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ assignment.student_count }} siswa
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <Badge variant="outline"
                            >KKM {{ assignment.kkm }}</Badge
                        >
                        <Button
                            as-child
                            size="sm"
                            class="min-h-11 md:min-h-0"
                            :data-test="`grades-open-${assignment.id}`"
                        >
                            <Link :href="gradesRoutes.show.url(assignment.id)">
                                Isi Nilai
                            </Link>
                        </Button>
                    </div>
                </li>
            </ul>
        </template>
    </div>
</template>
