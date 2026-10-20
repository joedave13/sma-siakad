<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import type { SchedulePayload } from '@/types/read';

defineProps<{ schedule: SchedulePayload }>();
</script>

<template>
    <div class="space-y-4">
        <div
            v-if="schedule.no_active_year"
            class="rounded-lg border p-6"
            data-test="schedule-no-active-year"
        >
            <EmptyState
                title="Tahun ajaran belum aktif"
                description="Admin perlu menetapkan tahun ajaran aktif terlebih dahulu agar jadwal pelajaran dapat ditampilkan."
            />
        </div>

        <div
            v-else-if="schedule.no_active_semester"
            class="rounded-lg border p-6"
            data-test="schedule-no-active-semester"
        >
            <EmptyState
                title="Semester belum aktif"
                description="Admin perlu mengaktifkan semester berjalan agar jadwal pelajaran dapat ditampilkan."
            />
        </div>

        <div
            v-else-if="schedule.not_enrolled"
            class="rounded-lg border p-6"
            data-test="schedule-not-enrolled"
        >
            <EmptyState
                title="Anda belum terdaftar di kelas mana pun"
                description="Wali kelas perlu menempatkan Anda ke rombongan belajar agar jadwal tampil. Hubungi admin bila penempatan belum dilakukan."
            />
        </div>

        <template v-else-if="schedule.days.length > 0">
            <p
                v-if="schedule.classroom"
                class="text-sm text-muted-foreground"
                data-test="schedule-classroom"
            >
                Kelas
                <span class="font-medium text-foreground">{{
                    schedule.classroom.name
                }}</span>
            </p>

            <div class="space-y-6" data-test="schedule-days">
                <section
                    v-for="day in schedule.days"
                    :key="day.day"
                    class="overflow-hidden rounded-lg border"
                    :data-test="`schedule-day-${day.day}`"
                >
                    <div
                        class="flex items-baseline justify-between border-b bg-muted/40 px-4 py-2.5"
                    >
                        <h2 class="text-sm font-semibold">{{ day.label }}</h2>
                        <span class="text-xs text-muted-foreground"
                            >{{ day.periods.length }} JP</span
                        >
                    </div>
                    <ul>
                        <li
                            v-for="period in day.periods"
                            :key="period.period_number"
                            class="flex flex-wrap items-baseline gap-x-4 gap-y-1 border-b border-border/60 px-4 py-3 last:border-0"
                            :data-test="`schedule-period-${day.day}-${period.period_number}`"
                        >
                            <span
                                class="w-8 shrink-0 text-sm font-medium tabular-nums text-muted-foreground"
                                >{{ period.period_number }}</span
                            >
                            <span
                                class="w-32 shrink-0 text-sm tabular-nums text-muted-foreground"
                                >{{ period.start_time }}–{{
                                    period.end_time
                                }}</span
                            >
                            <span class="grow text-sm font-medium">{{
                                period.subject
                            }}</span>
                            <span class="grow text-sm text-muted-foreground">{{
                                period.teacher
                            }}</span>
                        </li>
                    </ul>
                </section>
            </div>
        </template>

        <div v-else class="rounded-lg border p-6" data-test="schedule-empty">
            <EmptyState
                title="Belum ada jadwal pelajaran"
                description="Jadwal untuk semester ini belum disusun. Hubungi wali kelas atau admin agar jadwal Anda segera diisi."
            />
        </div>
    </div>
</template>
