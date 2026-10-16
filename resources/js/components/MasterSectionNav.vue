<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { MasterTab } from '@/pages/admin/master/tabs';

defineProps<{ tabs: MasterTab[] }>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <nav
        aria-label="Data master"
        class="mb-6 flex flex-wrap gap-1 border-b border-border pb-px"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.title"
            :href="tab.href"
            class="-mb-px inline-flex min-h-11 items-center border-b-2 px-3 py-2 text-sm font-medium transition-colors md:min-h-8"
            :class="
                isCurrentUrl(tab.href, undefined, true)
                    ? 'border-primary text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
        >
            {{ tab.title }}
        </Link>
    </nav>
</template>
