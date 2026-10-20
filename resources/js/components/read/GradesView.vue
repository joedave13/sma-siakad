<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import type { GradesPayload, GradesRow } from '@/types/read';

defineProps<{ grades: GradesPayload }>();

const columns = [
    { key: 'subject', label: 'Mata Pelajaran' },
    { key: 'score', label: 'Nilai', className: 'tabular-nums' },
    { key: 'predicate', label: 'Predikat' },
    { key: 'kkm', label: 'KKM', className: 'tabular-nums' },
    { key: 'status', label: 'Status' },
];
</script>

<template>
    <div class="space-y-4">
        <div
            v-if="grades.no_active_semester"
            class="rounded-lg border p-6"
            data-test="grades-no-active-semester"
        >
            <EmptyState
                title="Semester belum aktif"
                description="Admin perlu mengaktifkan semester berjalan agar nilai Anda dapat ditampilkan."
            />
        </div>

        <div
            v-else-if="grades.not_enrolled"
            class="rounded-lg border p-6"
            data-test="grades-not-enrolled"
        >
            <EmptyState
                title="Anda belum terdaftar di kelas mana pun"
                description="Nilai mengikuti rombongan belajar Anda. Hubungi admin bila penempatan kelas belum dilakukan."
            />
        </div>

        <template v-else>
            <p
                v-if="grades.semester && grades.classroom"
                class="text-sm text-muted-foreground"
                data-test="grades-context"
            >
                Semester {{ grades.semester.name }} · T.A.
                {{ grades.semester.academic_year }} · Kelas
                {{ grades.classroom.name }}
            </p>

            <DataTable
                :columns="columns"
                :rows="grades.rows"
                :row-key="(row: GradesRow) => row.subject"
                empty-title="Belum ada nilai"
                empty-description="Nilai Anda akan tampil setelah guru mengisi komponen penilaian pada semester ini."
            >
                <template #cell="{ row, column }">
                    <template v-if="column.key === 'subject'">
                        <div class="space-y-0.5">
                            <span class="font-medium">{{
                                (row as GradesRow).subject
                            }}</span>
                            <p
                                v-if="(row as GradesRow).missing.length > 0"
                                class="text-xs text-muted-foreground"
                                data-test="grades-missing"
                            >
                                Belum lengkap:
                                {{ (row as GradesRow).missing.join(', ') }}
                            </p>
                        </div>
                    </template>

                    <template v-else-if="column.key === 'score'">
                        {{ (row as GradesRow).score ?? '—' }}
                    </template>

                    <template v-else-if="column.key === 'status'">
                        <Badge
                            v-if="(row as GradesRow).score === null"
                            variant="outline"
                            data-test="grades-status-incomplete"
                            >Belum lengkap</Badge
                        >
                        <Badge
                            v-else-if="(row as GradesRow).passed"
                            variant="secondary"
                            data-test="grades-status-passed"
                            >Tuntas</Badge
                        >
                        <Badge
                            v-else
                            variant="destructive"
                            data-test="grades-status-failed"
                            >Di bawah KKM</Badge
                        >
                    </template>
                </template>
            </DataTable>
        </template>
    </div>
</template>
