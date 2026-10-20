<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import notificationsRoutes from '@/routes/notifications';
import type { NotificationListItem, NotificationMeta } from '@/types/read';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Notifikasi', href: notificationsRoutes.index() },
        ],
    },
});

defineProps<{
    items: NotificationListItem[];
    meta: NotificationMeta;
}>();

const page = usePage();

const unreadCount = computed(
    () => page.props.notifications?.unread_count ?? 0,
);

const visit = (url: string): void => {
    router.get(url, {}, { preserveScroll: true });
};

const markRead = (id: string): void => {
    router.patch(
        notificationsRoutes.markRead.url({ notification: id }),
        {},
        { preserveScroll: true },
    );
};

const markAllRead = (): void => {
    router.patch(notificationsRoutes.markAllRead.url(), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Notifikasi" />

    <PageHeader title="Notifikasi">
        <template v-if="unreadCount > 0" #actions>
            <Button
                size="sm"
                class="min-h-11 md:min-h-0"
                data-test="notifications-mark-all"
                @click="markAllRead"
            >
                Tandai semua sudah dibaca
            </Button>
        </template>
    </PageHeader>

    <div class="space-y-4">
        <ul
            v-if="items.length > 0"
            class="overflow-hidden rounded-lg border"
            data-test="notifications-list"
        >
            <li
                v-for="item in items"
                :key="item.id"
                class="flex flex-wrap items-start justify-between gap-3 border-b border-border/60 px-4 py-3.5 last:border-0"
                :class="item.read ? '' : 'bg-muted/40'"
                :data-test="`notification-${item.id}`"
            >
                <div class="min-w-0 space-y-0.5">
                    <p
                        v-if="item.title !== ''"
                        class="text-sm"
                        :class="item.read ? '' : 'font-semibold'"
                    >
                        {{ item.title }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ item.message }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ item.created_at }}
                    </p>
                </div>
                <div
                    v-if="!item.read"
                    class="flex shrink-0 items-center gap-2"
                >
                    <Badge
                        variant="secondary"
                        data-test="notification-unread-badge"
                        >Belum dibaca</Badge
                    >
                    <Button
                        variant="ghost"
                        size="sm"
                        class="min-h-11 text-muted-foreground md:min-h-0"
                        data-test="notification-mark-read"
                        @click="markRead(item.id)"
                    >
                        Tandai sudah dibaca
                    </Button>
                </div>
            </li>
        </ul>

        <div v-else class="rounded-lg border p-6" data-test="notifications-empty">
            <EmptyState
                title="Belum ada notifikasi"
                description="Notifikasi presensi harian anak Anda akan muncul di sini setiap hari sekolah."
            />
        </div>

        <div
            v-if="items.length > 0"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-sm text-muted-foreground">
                Halaman {{ meta.current_page }} dari {{ meta.last_page }} ({{
                    meta.total
                }}
                notifikasi)
            </p>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="meta.prev_page_url === null"
                    data-test="notifications-prev"
                    @click="meta.prev_page_url && visit(meta.prev_page_url)"
                >
                    Sebelumnya
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="meta.next_page_url === null"
                    data-test="notifications-next"
                    @click="meta.next_page_url && visit(meta.next_page_url)"
                >
                    Berikutnya
                </Button>
            </div>
        </div>
    </div>
</template>
