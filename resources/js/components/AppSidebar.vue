<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    ClipboardCheck,
    ClipboardList,
    Database,
    FileText,
    GraduationCap,
    LayoutGrid,
    PenLine,
    ScrollText,
    School,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { LucideIcon } from '@lucide/vue';

const navIcons: Record<string, LucideIcon> = {
    dashboard: LayoutGrid,
    master: Database,
    accounts: Users,
    schedule: CalendarDays,
    'report-publish': FileText,
    promotion: GraduationCap,
    'school-settings': School,
    'audit-log': ScrollText,
    attendance: ClipboardCheck,
    grades: PenLine,
    homeroom: ClipboardList,
    'report-card': FileText,
};

const page = usePage();
const navGroups = computed(() =>
    page.props.nav.map((group) => ({
        ...group,
        items: group.items.map((item) => ({
            ...item,
            icon: navIcons[item.key],
        })),
    })),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :groups="navGroups" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
