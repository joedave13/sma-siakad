<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { LatestReportCardPayload } from '@/types/dashboard';

defineProps<{
    latestReportCard: LatestReportCardPayload | null;
}>();
</script>

<template>
    <div class="grid max-w-2xl gap-4">
        <Card data-test="latest-report-card">
            <CardHeader>
                <CardTitle class="text-base">Rapor Terbaru</CardTitle>
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
                        data-test="latest-report-link"
                    >
                        <Link :href="latestReportCard.view_url">
                            Buka Rapor
                        </Link>
                    </Button>
                </template>
                <EmptyState
                    v-else
                    data-test="latest-report-empty"
                    description="Belum ada rapor terbit. Rapor tampil setelah nilai semester ini lengkap dan diterbitkan."
                />
            </CardContent>
        </Card>
    </div>
</template>
