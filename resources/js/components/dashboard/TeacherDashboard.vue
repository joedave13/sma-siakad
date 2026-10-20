<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import attendanceRoutes from '@/routes/teacher/attendance';
import type { PendingSubmission, TodayPeriod } from '@/types/dashboard';

type Props = {
    todayPeriods: TodayPeriod[];
    pendingSubmissions: PendingSubmission[];
};

const props = defineProps<Props>();

const pendingEmptyDescription = computed(() =>
    props.todayPeriods.some((period) => period.submitted)
        ? 'Semua presensi hari ini sudah terkirim.'
        : 'Belum ada sesi presensi yang menunggu pengisian.',
);
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2">
        <Card data-test="today-periods">
            <CardHeader>
                <CardTitle class="text-base"
                    >Jadwal Mengajar Hari Ini</CardTitle
                >
            </CardHeader>
            <CardContent>
                <ul v-if="todayPeriods.length > 0" class="space-y-3">
                    <li
                        v-for="period in todayPeriods"
                        :key="period.schedule_id"
                        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                        :data-test="`today-period-${period.schedule_id}`"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                Jam ke-{{ period.period_number }} ({{
                                    period.start_time
                                }}–{{ period.end_time }})
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ period.subject }} · {{ period.classroom }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 sm:justify-end">
                            <Badge
                                :variant="
                                    period.submitted ? 'secondary' : 'outline'
                                "
                            >
                                {{
                                    period.submitted
                                        ? 'Sudah dikirim'
                                        : 'Belum dikirim'
                                }}
                            </Badge>
                            <Button
                                as-child
                                size="sm"
                                class="min-h-11 md:min-h-0"
                            >
                                <Link
                                    :href="
                                        attendanceRoutes.show.url(
                                            period.schedule_id,
                                        )
                                    "
                                >
                                    Buka
                                </Link>
                            </Button>
                        </div>
                    </li>
                </ul>
                <EmptyState
                    v-else
                    data-test="today-periods-empty"
                    description="Tidak ada jadwal mengajar hari ini."
                />
            </CardContent>
        </Card>

        <Card data-test="pending-submissions">
            <CardHeader>
                <CardTitle class="text-base">
                    Presensi Belum Dikirim
                </CardTitle>
            </CardHeader>
            <CardContent>
                <ul v-if="pendingSubmissions.length > 0" class="space-y-3">
                    <li
                        v-for="pending in pendingSubmissions"
                        :key="`${pending.schedule_id}-${pending.date}`"
                        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                        :data-test="`pending-submission-${pending.schedule_id}-${pending.date}`"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ pending.date_label }} · Jam ke-{{
                                    pending.period_number
                                }}
                                ({{ pending.start_time }}–{{
                                    pending.end_time
                                }})
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ pending.subject }} · {{ pending.classroom }}
                            </p>
                        </div>
                        <Button as-child size="sm" class="min-h-11 md:min-h-0">
                            <Link
                                :href="
                                    attendanceRoutes.show.url(
                                        pending.schedule_id,
                                        { query: { date: pending.date } },
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
                    data-test="pending-submissions-empty"
                    :description="pendingEmptyDescription"
                />
            </CardContent>
        </Card>
    </div>
</template>
