<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { index as referral } from '@/routes/referral';

const props = defineProps<{
    code: string;
    url: string;
    shareLinks: { whatsapp: string; facebook: string };
    referrals: { total: number; active: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Referral link', href: referral() }],
    },
});

const copied = ref(false);

async function copy() {
    try {
        await navigator.clipboard.writeText(props.url);
    } catch {
        // Fallback for browsers without the async clipboard API.
        const input = document.getElementById(
            'referral-url',
        ) as HTMLInputElement | null;
        input?.select();
        document.execCommand('copy');
    }

    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}
</script>

<template>
    <Head title="Referral link" />

    <div class="flex max-w-2xl flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Referral link · রেফারেল লিংক</h1>
            <p class="text-sm text-muted-foreground">
                Share this link. People who register with it join with you as
                their sponsor ({{ code }}).
            </p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row">
            <input
                id="referral-url"
                :value="url"
                readonly
                class="h-9 w-full rounded-md border bg-transparent px-3 text-sm"
                aria-label="Your referral link"
                data-test="referral-url"
                @focus="($event.target as HTMLInputElement).select()"
            />
            <Button type="button" data-test="copy" @click="copy">
                {{ copied ? 'Copied ✓' : 'Copy · কপি' }}
            </Button>
        </div>

        <div class="flex flex-wrap gap-2">
            <Button as-child variant="outline">
                <a
                    :href="shareLinks.whatsapp"
                    target="_blank"
                    rel="noopener noreferrer"
                    data-test="share-whatsapp"
                    >Share on WhatsApp</a
                >
            </Button>
            <Button as-child variant="outline">
                <a
                    :href="shareLinks.facebook"
                    target="_blank"
                    rel="noopener noreferrer"
                    data-test="share-facebook"
                    >Share on Facebook</a
                >
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border p-4">
                <p class="text-sm text-muted-foreground">
                    People you sponsored · মোট রেফারেল
                </p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ referrals.total }}
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-sm text-muted-foreground">
                    Of them active · সক্রিয়
                </p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ referrals.active }}
                </p>
            </div>
        </div>
    </div>
</template>
