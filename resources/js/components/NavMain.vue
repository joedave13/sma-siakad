<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { SidebarNavGroup } from '@/types';

defineProps<{
    groups: SidebarNavGroup[];
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup
        v-for="group in groups"
        :key="group.label ?? 'menu'"
        class="px-2 py-0"
    >
        <SidebarGroupLabel v-if="group.label">{{
            group.label
        }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in group.items" :key="item.key">
                <SidebarMenuButton
                    v-if="item.href && !item.coming_soon"
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" v-if="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
                <!-- TODO: replace with a real link as each destination ships (STEPS.md 14-23) -->
                <SidebarMenuButton
                    v-else
                    aria-disabled="true"
                    tabindex="-1"
                    class="cursor-default text-sidebar-foreground/70"
                    :tooltip="`${item.title} (segera hadir)`"
                >
                    <component :is="item.icon" v-if="item.icon" />
                    <span>{{ item.title }}</span>
                    <span class="ml-auto shrink-0 text-xs text-muted-foreground"
                        >Segera hadir</span
                    >
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
