<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
};

defineProps<Props>();
</script>

<template>
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ title }}
            </h1>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
