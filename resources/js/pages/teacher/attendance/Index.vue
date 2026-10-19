<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import attendanceRoutes from '@/routes/teacher/attendance';
import type { AttendanceSlotWithState } from '@/types/attendance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Presensi',
                href: attendanceRoutes.index(),
            },
        ],
    },
});

type Props = {
    date: string;
    slots: AttendanceSlotWithState[];
    profileMissing: boolean;
};

const props = defineProps<Props>();

const stateLabels: Record<AttendanceSlotWithState['state'], string> = {
    none: 'Belum diisi',
    submitted: 'Sudah dikirim',
    locked: 'Terkunci',
};

function onDateChange(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    if (value === '') {
        return;
    }

    router.get(
        attendanceRoutes.index(),
        { date: value },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <Head title="Presensi" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            title="Presensi"
            description="Daftar jam pelajaran mengajar hari ini beserta status pengisian presensinya."
        />

        <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end">
            <div class="grid w-full max-w-xs gap-2">
                <Label for="attendance-date">Tanggal</Label>
                <Input
                    id="attendance-date"
                    data-test="attendance-date"
                    type="date"
                    :value="props.date"
                    @change="onDateChange"
                />
            </div>
        </div>

        <div
            v-if="profileMissing"
            data-test="attendance-profile-missing"
            class="rounded-md border p-6"
        >
            <EmptyState
                title="Profil guru tidak ditemukan"
                description="Akun Anda belum terhubung ke profil guru. Hubungi admin untuk menghubungkan akun Anda ke data guru."
            />
        </div>

        <div
            v-else-if="props.slots.length === 0"
            data-test="attendance-empty"
            class="rounded-md border p-6"
        >
            <EmptyState
                title="Tidak ada jam pelajaran"
                description="Tidak ada jadwal mengajar pada tanggal ini. Ubah tanggal untuk melihat hari lain."
            />
        </div>

        <ul v-else class="space-y-3" data-test="attendance-periods">
            <li
                v-for="slot in props.slots"
                :key="slot.schedule_id"
                class="flex flex-col gap-3 rounded-md border p-4 sm:flex-row sm:items-center sm:justify-between"
                :data-test="`attendance-slot-${slot.schedule_id}`"
            >
                <div class="min-w-0">
                    <p class="text-sm font-medium">
                        Jam ke-{{ slot.period_number }} ({{
                            slot.start_time
                        }}–{{ slot.end_time }})
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ slot.subject }} · {{ slot.classroom }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Badge
                        :variant="
                            slot.state === 'submitted'
                                ? 'default'
                                : slot.state === 'locked'
                                  ? 'outline'
                                  : 'secondary'
                        "
                        :data-test="`attendance-state-${slot.schedule_id}`"
                    >
                        {{ stateLabels[slot.state] }}
                    </Badge>
                    <Button
                        as-child
                        size="sm"
                        class="min-h-11 md:min-h-0"
                        :variant="slot.state === 'none' ? 'default' : 'outline'"
                        :data-test="`attendance-open-${slot.schedule_id}`"
                    >
                        <Link
                            :href="
                                attendanceRoutes.show.url(slot.schedule_id, {
                                    query: { date: props.date },
                                })
                            "
                        >
                            {{
                                slot.state === 'submitted'
                                    ? 'Lihat Presensi'
                                    : slot.state === 'locked'
                                      ? 'Lihat'
                                      : 'Isi Presensi'
                            }}
                        </Link>
                    </Button>
                </div>
            </li>
        </ul>
    </div>
</template>
