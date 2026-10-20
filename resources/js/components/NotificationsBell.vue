<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed } from 'vue';
import notificationsRoutes from '@/routes/notifications';

const page = usePage();

const notifications = computed(() => page.props.notifications);

const unreadLabel = computed(() => {
    if (notifications.value === null) {
        return '';
    }

    return notifications.value.unread_count > 0
        ? `, ${notifications.value.unread_count} belum dibaca`
        : ', semua sudah dibaca';
});

const ariaLabel = computed(() => `Notifikasi${unreadLabel.value}`);
</script>

<template>
    <Link
        v-if="notifications !== null"
        :href="notificationsRoutes.index()"
        class="relative inline-flex size-11 items-center justify-center rounded-md text-neutral-500 hover:bg-accent hover:text-neutral-900 focus-visible:ring-2 focus-visible:ring-primary"
        data-test="notifications-bell"
        :aria-label="ariaLabel"
    >
        <Bell class="h-5 w-5" aria-hidden="true" />
        <span
            v-if="notifications.unread_count > 0"
            class="absolute top-1.5 right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-none font-semibold text-white dark:bg-destructive/60"
            data-test="notifications-unread-count"
        >
            {{
                notifications.unread_count > 9
                    ? '9+'
                    : notifications.unread_count
            }}
        </span>
    </Link>
</template>
