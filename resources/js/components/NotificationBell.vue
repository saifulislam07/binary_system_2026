<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { notificationIcon, timeAgo } from '@/lib/notifications';
import type { AppNotification } from '@/lib/notifications';
import { index, read, readAll, recent } from '@/routes/notifications';

const page = usePage();

const unread = ref(page.props.unreadNotifications ?? 0);
const items = ref<AppNotification[] | null>(null);
const loading = ref(false);
const failed = ref(false);

// Page visits refresh the shared count.
watch(
    () => page.props.unreadNotifications,
    (count) => {
        unread.value = count ?? 0;
    },
);

const badge = computed(() => (unread.value > 9 ? '9+' : String(unread.value)));

async function load(open: boolean) {
    if (!open) {
        return;
    }

    loading.value = true;
    failed.value = false;

    try {
        const response = await fetch(recent.url(), {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data: { unread: number; items: AppNotification[] } =
            await response.json();
        unread.value = data.unread;
        items.value = data.items;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

function openItem(item: AppNotification) {
    router.post(read.url(item.id));
}

function markAllRead() {
    router.post(
        readAll.url(),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                unread.value = 0;
                items.value?.forEach((item) => (item.read = true));
            },
        },
    );
}
</script>

<template>
    <DropdownMenu @update:open="load">
        <DropdownMenuTrigger :as-child="true">
            <Button
                variant="ghost"
                size="icon"
                class="relative"
                :aria-label="
                    unread > 0
                        ? `Notifications, ${unread} unread`
                        : 'Notifications'
                "
            >
                <Bell class="size-5" />
                <span
                    v-if="unread > 0"
                    class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] leading-none font-semibold text-white tabular-nums"
                    aria-hidden="true"
                >
                    {{ badge }}
                </span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            align="end"
            class="w-[min(24rem,calc(100vw-2rem))] p-0"
        >
            <div class="flex items-center justify-between border-b px-3 py-2">
                <p class="text-sm font-medium">Notifications · নোটিফিকেশন</p>
                <button
                    v-if="unread > 0"
                    type="button"
                    class="text-xs text-primary underline-offset-4 hover:underline"
                    @click="markAllRead"
                >
                    Mark all read
                </button>
            </div>

            <div class="max-h-96 overflow-y-auto">
                <p
                    v-if="loading && items === null"
                    class="px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    Loading…
                </p>
                <p
                    v-else-if="failed"
                    class="px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    Could not load notifications.
                </p>
                <p
                    v-else-if="items !== null && items.length === 0"
                    class="px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    No notifications yet · এখনো কোনো নোটিফিকেশন নেই
                </p>
                <ul v-else-if="items !== null" class="divide-y">
                    <li v-for="item in items" :key="item.id">
                        <button
                            type="button"
                            class="flex w-full gap-3 px-3 py-2.5 text-left hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
                            @click="openItem(item)"
                        >
                            <component
                                :is="notificationIcon(item.kind)"
                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block text-sm"
                                    :class="item.read ? '' : 'font-semibold'"
                                >
                                    {{ item.title }}
                                </span>
                                <span
                                    class="line-clamp-2 block text-xs text-muted-foreground"
                                >
                                    {{ item.message }}
                                </span>
                                <span
                                    class="block text-[11px] text-muted-foreground"
                                >
                                    {{ timeAgo(item.created_at) }}
                                </span>
                            </span>
                            <span
                                v-if="!item.read"
                                class="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
                                aria-label="Unread"
                            />
                        </button>
                    </li>
                </ul>
            </div>

            <div class="border-t px-3 py-2 text-center">
                <Link
                    :href="index()"
                    class="text-xs text-primary underline-offset-4 hover:underline"
                >
                    View all · সব দেখুন
                </Link>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
