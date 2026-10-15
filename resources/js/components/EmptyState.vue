<script setup lang="ts">
type Props = {
    description: string;
    title?: string;
};

defineProps<Props>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-1 py-4 text-center"
    >
        <p v-if="title" class="text-sm font-medium">{{ title }}</p>
        <p class="max-w-prose text-sm text-muted-foreground">
            {{ description }}
        </p>
        <div v-if="$slots.actions" class="mt-2">
            <slot name="actions" />
        </div>
    </div>
</template>
