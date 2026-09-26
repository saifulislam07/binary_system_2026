<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { notificationIcon, timeAgo } from '@/lib/notifications';
import type { AppNotification } from '@/lib/notifications';
import { index, read, readAll } from '@/routes/notifications';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

defineProps<{
    notifications: Paginated<AppNotification>;
    unread: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Notifications', href: index() }],
    },
});

function open(item: AppNotification) {
    router.post(read.url(item.id));
}

function markAllRead() {
    router.post(readAll.url(), {}, { preserveScroll: true });
}

function fullDate(iso: string | null): string {
    return iso ? new Date(iso).toLocaleString() : '';
}
</script>

<template>
    <Head title="Notifications" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-semibold">Notifications · নোটিফিকেশন</h1>
            <Button
                v-if="unread > 0"
                variant="outline"
                size="sm"
                data-test="mark-all-read"
                @click="markAllRead"
            >
                Mark all read ({{ unread }})
            </Button>
        </div>

        <ul class="divide-y overflow-hidden rounded-xl border">
            <li v-for="item in notifications.data" :key="item.id">
                <button
                    type="button"
                    class="flex w-full gap-3 px-4 py-3 text-left hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
                    data-test="notification-row"
                    @click="open(item)"
                >
                    <component
                        :is="notificationIcon(item.kind)"
                        class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1">
                        <span
                            class="block"
                            :class="item.read ? '' : 'font-semibold'"
                        >
                            {{ item.title }}
                        </span>
                        <span class="block text-sm text-muted-foreground">
                            {{ item.message }}
                        </span>
                        <time
                            class="block text-xs text-muted-foreground"
                            :datetime="item.created_at ?? undefined"
                            :title="fullDate(item.created_at)"
                        >
                            {{ timeAgo(item.created_at) }}
                        </time>
                    </span>
                    <span
                        v-if="!item.read"
                        class="mt-2 size-2 shrink-0 rounded-full bg-primary"
                        aria-label="Unread"
                    />
                </button>
            </li>
            <li
                v-if="notifications.data.length === 0"
                class="px-4 py-8 text-center text-muted-foreground"
            >
                No notifications yet · এখনো কোনো নোটিফিকেশন নেই
            </li>
        </ul>

        <nav
            v-if="notifications.last_page > 1"
            class="flex items-center justify-between text-sm"
            aria-label="Pagination"
        >
            <Button
                variant="outline"
                size="sm"
                :disabled="!notifications.prev_page_url"
                as-child
            >
                <Link :href="notifications.prev_page_url ?? '#'" preserve-scroll
                    >Previous</Link
                >
            </Button>
            <span class="text-muted-foreground"
                >Page {{ notifications.current_page }} of
                {{ notifications.last_page }} ({{ notifications.total }})</span
            >
            <Button
                variant="outline"
                size="sm"
                :disabled="!notifications.next_page_url"
                as-child
            >
                <Link :href="notifications.next_page_url ?? '#'" preserve-scroll
                    >Next</Link
                >
            </Button>
        </nav>
    </div>
</template>
