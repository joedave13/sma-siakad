<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import NoChildrenNotice from '@/components/read/NoChildrenNotice.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import guardianAttendanceRoutes from '@/routes/guardian/attendance';
import type { AbsenceRow, LatestReportCardPayload } from '@/types/dashboard';

type Props = {
    latestReportCard: LatestReportCardPayload | null;
    recentAbsences: AbsenceRow[];
    childCount: number;
    currentChildName: string | null;
};

defineProps<Props>();

const absenceVariant = (statusLabel: string) =>
    statusLabel === 'Alpa' ? 'destructive' : 'outline';
</script>

<template>
    <NoChildrenNotice v-if="childCount === 0" />
    <div v-else class="grid gap-4 md:grid-cols-2">
        <Card data-test="parent-latest-report">
            <CardHeader>
                <CardTitle class="text-base">Rapor Anak Terbaru</CardTitle>
                <CardDescription v-if="currentChildName">
                    Data untuk {{ currentChildName }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <template v-if="latestReportCard">
                    <p class="text-sm font-medium">
                        {{ latestReportCard.term }}
                        {{ latestReportCard.academic_year }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Diterbitkan {{ latestReportCard.published_at }}
                    </p>
                    <Button
                        as-child
                        size="sm"
                        class="mt-4 min-h-11 md:min-h-0"
                        data-test="parent-latest-report-link"
                    >
                        <Link :href="latestReportCard.view_url">
                            Buka Rapor
                        </Link>
                    </Button>
                </template>
                <EmptyState
                    v-else
                    data-test="parent-latest-report-empty"
                    description="Belum ada rapor terbit untuk anak Anda. Rapor tampil setelah nilai semester ini lengkap dan diterbitkan."
                />
            </CardContent>
        </Card>

        <Card data-test="parent-absences">
            <CardHeader>
                <CardTitle class="text-base">Ketidakhadiran Anak</CardTitle>
                <CardDescription v-if="currentChildName">
                    Data untuk {{ currentChildName }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <template v-if="recentAbsences.length > 0">
                    <ul class="space-y-3">
                        <li
                            v-for="(absence, index) in recentAbsences"
                            :key="`${absence.date}-${index}`"
                            class="flex items-center justify-between gap-2"
                            :data-test="`parent-absence-${index}`"
                        >
                            <span class="min-w-0 truncate text-sm font-medium">
                                {{ absence.date }}
                            </span>
                            <Badge
                                :variant="absenceVariant(absence.status_label)"
                            >
                                {{ absence.status_label }}
                            </Badge>
                        </li>
                    </ul>
                    <Button
                        as-child
                        size="sm"
                        class="mt-4 min-h-11 md:min-h-0"
                        data-test="parent-absences-link"
                    >
                        <Link :href="guardianAttendanceRoutes.index()">
                            Lihat Presensi
                        </Link>
                    </Button>
                </template>
                <EmptyState
                    v-else
                    data-test="parent-absences-empty"
                    description="Belum ada catatan ketidakhadiran anak."
                />
            </CardContent>
        </Card>
    </div>
</template>
