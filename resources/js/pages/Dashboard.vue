<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { dashboard } from '@/routes';
import { index as checkout } from '@/routes/checkout';

defineProps<{
    member: {
        code: string | null;
        status: string;
        package: string | null;
    } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <div
            v-if="member?.status === 'pending'"
            class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100"
            data-test="pending-banner"
        >
            Your account is pending. Complete payment for the
            {{ member.package ?? 'selected' }} package to activate it and get
            your member ID.
            <br />
            আপনার অ্যাকাউন্টটি অপেক্ষমাণ। সক্রিয় করতে প্যাকেজের মূল্য পরিশোধ
            করুন।
            <Link
                :href="checkout()"
                class="mt-2 block font-semibold underline"
                data-test="pay-now"
                >Pay now · এখনই পেমেন্ট করুন</Link
            >
        </div>
        <div
            v-else-if="member?.code"
            class="text-sm text-muted-foreground"
            data-test="member-code"
        >
            Member ID: <span class="font-medium">{{ member.code }}</span>
        </div>
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
        </div>
        <div
            class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border"
        >
            <PlaceholderPattern />
        </div>
    </div>
</template>
