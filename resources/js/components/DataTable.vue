<script setup lang="ts" generic="T extends Record<string, unknown>">
import EmptyState from '@/components/EmptyState.vue';

type Column = {
    key: string;
    label: string;
    className?: string;
};

type Props = {
    columns: Column[];
    rows: T[];
    rowKey: (row: T) => string | number;
    emptyTitle: string;
    emptyDescription: string;
};

defineProps<Props>();

const displayValue = (row: T, key: string): string => {
    const value = row[key];

    return value === null || value === undefined ? '' : String(value);
};
</script>

<template>
    <div class="w-full overflow-x-auto rounded-lg border border-border">
        <table class="w-full text-sm">
            <thead>
                <tr
                    class="border-b border-border bg-muted/40 text-left text-muted-foreground"
                >
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        scope="col"
                        class="px-4 py-2.5 font-medium"
                        :class="column.className"
                    >
                        {{ column.label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in rows"
                    :key="rowKey(row)"
                    class="border-b border-border/60 last:border-0"
                >
                    <td
                        v-for="column in columns"
                        :key="column.key"
                        class="px-4 py-2.5 align-top"
                        :class="column.className"
                    >
                        <slot name="cell" :row="row" :column="column">{{
                            displayValue(row, column.key)
                        }}</slot>
                    </td>
                </tr>
                <tr v-if="rows.length === 0">
                    <td :colspan="columns.length" class="px-4 py-6">
                        <EmptyState
                            :title="emptyTitle"
                            :description="emptyDescription"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
