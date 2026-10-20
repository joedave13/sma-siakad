<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import EmptyState from '@/components/EmptyState.vue';
import type { AttendancePayload } from '@/types/read';

defineProps<{ attendance: AttendancePayload }>();

const dateFormatter = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

const formatTanggal = (iso: string): string =>
    dateFormatter.format(new Date(`${iso}T00:00:00`));

type BadgeVariant = 'default' | 'secondary' | 'outline' | 'destructive';

const statusVariant = (status: string): BadgeVariant => {
    if (status === 'absent') {
        return 'destructive';
    }

    if (status === 'present') {
        return 'secondary';
    }

    return 'outline';
};
</script>

<template>
    <div class="space-y-4">
        <div
            v-if="attendance.no_active_semester"
            class="rounded-lg border p-6"
            data-test="attendance-no-active-semester"
        >
            <EmptyState
                title="Semester belum aktif"
                description="Admin perlu mengaktifkan semester berjalan agar riwayat presensi dapat ditampilkan."
            />
        </div>

        <div
            v-else-if="attendance.not_enrolled"
            class="rounded-lg border p-6"
            data-test="attendance-not-enrolled"
        >
            <EmptyState
                title="Anda belum terdaftar di kelas mana pun"
                description="Riwayat presensi mengikuti rombongan belajar Anda. Hubungi admin bila penempatan kelas belum dilakukan."
            />
        </div>

        <template v-else>
            <div
                v-if="attendance.summary"
                class="grid grid-cols-2 gap-3 sm:grid-cols-5"
                data-test="attendance-summary"
            >
                <div class="rounded-lg border px-4 py-3">
                    <p class="text-xs text-muted-foreground">Kehadiran</p>
                    <p class="text-xl font-semibold tabular-nums">
                        {{ attendance.summary.percentage }}%
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ attendance.summary.present_days }} dari
                        {{ attendance.summary.school_days }} hari sekolah
                    </p>
                </div>
                <div class="rounded-lg border px-4 py-3">
                    <p class="text-xs text-muted-foreground">Sakit</p>
                    <p class="text-xl font-semibold tabular-nums">
                        {{ attendance.summary.sick }}
                    </p>
                    <p class="text-xs text-muted-foreground">hari</p>
                </div>
                <div class="rounded-lg border px-4 py-3">
                    <p class="text-xs text-muted-foreground">Izin</p>
                    <p class="text-xl font-semibold tabular-nums">
                        {{ attendance.summary.permitted }}
                    </p>
                    <p class="text-xs text-muted-foreground">hari</p>
                </div>
                <div class="rounded-lg border px-4 py-3">
                    <p class="text-xs text-muted-foreground">Alpa</p>
                    <p class="text-xl font-semibold tabular-nums">
                        {{ attendance.summary.absent }}
                    </p>
                    <p class="text-xs text-muted-foreground">hari</p>
                </div>
                <div class="rounded-lg border px-4 py-3">
                    <p class="text-xs text-muted-foreground">Terlambat</p>
                    <p class="text-xl font-semibold tabular-nums">
                        {{ attendance.summary.late }}
                    </p>
                    <p class="text-xs text-muted-foreground">hari</p>
                </div>
            </div>

            <div
                v-if="attendance.days.length > 0"
                class="overflow-hidden rounded-lg border"
                data-test="attendance-days"
            >
                <div
                    v-for="day in attendance.days"
                    :key="day.date"
                    class="border-b border-border/60 last:border-0"
                    :data-test="`attendance-day-${day.date}`"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 bg-muted/40 px-4 py-2.5"
                    >
                        <span class="text-sm font-medium">{{
                            formatTanggal(day.date)
                        }}</span>
                        <div class="flex items-center gap-2">
                            <Badge
                                v-if="day.is_overridden"
                                variant="outline"
                                data-test="attendance-overridden"
                                >Dikoreksi</Badge
                            >
                            <Badge :variant="statusVariant(day.status)">{{
                                day.status_label
                            }}</Badge>
                        </div>
                    </div>
                    <ul v-if="day.periods.length > 0" class="px-4">
                        <li
                            v-for="period in day.periods"
                            :key="period.period_number"
                            class="flex flex-wrap items-baseline gap-x-3 gap-y-1 border-b border-border/40 py-2.5 last:border-0"
                            :data-test="`attendance-period-${day.date}-${period.period_number}`"
                        >
                            <span
                                class="w-8 shrink-0 text-sm text-muted-foreground tabular-nums"
                                >{{ period.period_number }}</span
                            >
                            <span class="grow text-sm">{{
                                period.subject
                            }}</span>
                            <span class="text-sm font-medium">{{
                                period.status_label
                            }}</span>
                            <span
                                v-if="period.note"
                                class="basis-full pl-11 text-xs text-muted-foreground"
                                >{{ period.note }}</span
                            >
                        </li>
                    </ul>
                </div>
            </div>

            <div
                v-else
                class="rounded-lg border p-6"
                data-test="attendance-empty"
            >
                <EmptyState
                    title="Belum ada riwayat presensi"
                    description="Riwayat harian akan muncul setelah wali kelas mengisi presensi Anda pada semester ini."
                />
            </div>
        </template>
    </div>
</template>
