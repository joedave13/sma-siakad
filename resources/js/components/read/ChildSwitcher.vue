<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { ChildOption } from '@/types/read';

const props = defineProps<{
    children: ChildOption[];
    currentChildId: number | null;
}>();

const { currentUrl } = useCurrentUrl();

const currentChild = computed(
    () =>
        props.children.find((child) => child.id === props.currentChildId) ??
        props.children[0] ??
        null,
);

const hrefFor = (child: ChildOption): string =>
    `${currentUrl.value}?anak=${child.id}`;
</script>

<template>
    <div v-if="children.length > 0" data-test="child-switcher">
        <DropdownMenu v-if="children.length > 1">
            <DropdownMenuTrigger :as-child="true">
                <Button
                    variant="outline"
                    size="sm"
                    class="min-h-11 gap-2 md:min-h-0"
                    data-test="child-switcher-trigger"
                >
                    <span class="max-w-40 truncate">{{
                        currentChild?.name ?? 'Pilih anak'
                    }}</span>
                    <ChevronDown class="h-4 w-4 shrink-0" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-64">
                <DropdownMenuItem
                    v-for="child in children"
                    :key="child.id"
                    :as-child="true"
                    data-test="child-option"
                >
                    <Link
                        :href="hrefFor(child)"
                        class="flex w-full cursor-pointer items-baseline justify-between gap-2"
                        :preserve-scroll="true"
                        :data-test="`child-option-${child.id}`"
                    >
                        <span>{{ child.name }}</span>
                        <span
                            v-if="child.classroom"
                            class="text-xs text-muted-foreground"
                            >{{ child.classroom }}</span
                        >
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <p
            v-else-if="currentChild !== null"
            class="text-sm text-muted-foreground"
            data-test="child-single"
        >
            Menampilkan
            <span class="font-medium text-foreground">{{
                currentChild.name
            }}</span>
            <template v-if="currentChild.classroom">
                · Kelas {{ currentChild.classroom }}</template
            >
        </p>
    </div>
</template>
