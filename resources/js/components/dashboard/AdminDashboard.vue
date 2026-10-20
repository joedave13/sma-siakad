<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import adminAttendanceRoutes from '@/routes/admin/attendance';
import promotionRoutes from '@/routes/admin/promotion';
import reportsRoutes from '@/routes/admin/reports';
import type { MissingSession } from '@/types/attendance';
import type {
    BelowKkmPayload,
    UnpublishedReportCardsPayload,
} from '@/types/dashboard';

type Props = {
    missingSessions: MissingSession[];
    belowKkm: BelowKkmPayload;
    unpublishedReportCards: UnpublishedReportCardsPayload;
};

defineProps<Props>();
</script>

<template>
    <div class="grid gap-4 md:grid-cols-3">
        <Card data-test="missing-sessions">
            <CardHeader>
                <CardTitle class="text-base"
                    >Sesi Presensi Belum Diisi</CardTitle
                >
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
                        <Button as-child size="sm" class="min-h-11 md:min-h-0">
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

        <Card data-test="below-kkm">
            <CardHeader>
                <CardTitle class="text-base">Nilai di Bawah KKM</CardTitle>
            </CardHeader>
            <CardContent>
                <template v-if="belowKkm.total > 0">
                    <p class="mb-4 flex items-baseline gap-2">
                        <span
                            class="text-2xl font-semibold"
                            data-test="below-kkm-total"
                            >{{ belowKkm.total }}</span
                        >
                        <span class="text-sm text-muted-foreground"
                            >siswa berada di bawah KKM</span
                        >
                    </p>
                    <ul class="space-y-3">
                        <li
                            v-for="row in belowKkm.rows"
                            :key="row.student_id"
                            class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                            :data-test="`below-kkm-row-${row.student_id}`"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{ row.name }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ row.classroom }}
                                </p>
                            </div>
                            <Badge variant="destructive">
                                {{ row.failing_subjects }} mata pelajaran
                            </Badge>
                        </li>
                    </ul>
                    <p
                        v-if="belowKkm.rows.length < belowKkm.total"
                        class="mt-3 text-sm text-muted-foreground"
                        data-test="below-kkm-more"
                    >
                        …dan {{ belowKkm.total - belowKkm.rows.length }} siswa
                        lainnya
                    </p>
                    <Button
                        as-child
                        size="sm"
                        class="mt-4 min-h-11 md:min-h-0"
                        data-test="below-kkm-link"
                    >
                        <Link :href="promotionRoutes.index()">
                            Lihat Kenaikan Kelas
                        </Link>
                    </Button>
                </template>
                <EmptyState
                    v-else-if="belowKkm.evaluated > 0"
                    data-test="below-kkm-passing"
                    :description="`Semua ${belowKkm.evaluated} nilai akhir yang lengkap sudah memenuhi KKM semester ini.`"
                />
                <EmptyState
                    v-else
                    data-test="below-kkm-empty"
                    description="Belum ada nilai akhir yang lengkap semester ini. Lengkapi penilaian guru untuk membandingkan nilai dengan KKM."
                />
            </CardContent>
        </Card>

        <Card data-test="unpublished-cards">
            <CardHeader>
                <CardTitle class="text-base">Rapor Belum Terbit</CardTitle>
            </CardHeader>
            <CardContent>
                <template v-if="unpublishedReportCards.total > 0">
                    <p class="mb-4 flex items-baseline gap-2">
                        <span
                            class="text-2xl font-semibold"
                            data-test="unpublished-total"
                            >{{ unpublishedReportCards.total }}</span
                        >
                        <span class="text-sm text-muted-foreground"
                            >rapor menunggu penerbitan</span
                        >
                    </p>
                    <ul class="space-y-3">
                        <li
                            v-for="semester in unpublishedReportCards.semesters"
                            :key="semester.semester_id"
                            class="flex items-center justify-between gap-2"
                            :data-test="`unpublished-semester-${semester.semester_id}`"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{ semester.term }}
                                    {{ semester.academic_year }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ semester.count }} rapor
                                </p>
                            </div>
                        </li>
                    </ul>
                    <Button
                        as-child
                        size="sm"
                        class="mt-4 min-h-11 md:min-h-0"
                        data-test="unpublished-link"
                    >
                        <Link :href="reportsRoutes.index()">
                            Terbitkan Rapor
                        </Link>
                    </Button>
                </template>
                <EmptyState
                    v-else-if="unpublishedReportCards.generated_total > 0"
                    data-test="unpublished-empty"
                    :description="`Semua ${unpublishedReportCards.generated_total} rapor yang sudah digenerate sudah diterbitkan.`"
                />
                <EmptyState
                    v-else
                    data-test="unpublished-none"
                    description="Belum ada rapor yang digenerate. Generate rapor melalui halaman Terbitkan Rapor."
                />
            </CardContent>
        </Card>
    </div>
</template>
