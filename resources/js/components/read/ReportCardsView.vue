<script setup lang="ts">
import { Button } from '@/components/ui/button';
import EmptyState from '@/components/EmptyState.vue';
import type { ReportCardsPayload } from '@/types/read';

defineProps<{ reportCards: ReportCardsPayload }>();
</script>

<template>
    <div>
        <ul
            v-if="reportCards.rows.length > 0"
            class="overflow-hidden rounded-lg border"
            data-test="report-cards"
        >
            <li
                v-for="row in reportCards.rows"
                :key="row.semester_id"
                class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 px-4 py-3.5 last:border-0"
                :data-test="`report-card-${row.semester_id}`"
            >
                <div class="min-w-0">
                    <p class="text-sm font-medium">
                        Semester {{ row.term }} · {{ row.academic_year }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Diterbitkan {{ row.published_at }}
                    </p>
                </div>
                <Button
                    as-child
                    size="sm"
                    class="min-h-11 md:min-h-0"
                    data-test="report-card-download"
                >
                    <a :href="row.download_url">Unduh PDF</a>
                </Button>
            </li>
        </ul>

        <div v-else class="rounded-lg border p-6" data-test="report-cards-empty">
            <EmptyState
                title="Belum ada rapor yang diterbitkan"
                description="Rapor tampil di sini setelah admin menerbitkan rapor semester terkait. Pantau halaman ini secara berkala."
            />
        </div>
    </div>
</template>
