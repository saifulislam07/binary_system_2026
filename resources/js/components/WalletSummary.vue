<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { index as wallet } from '@/routes/wallet';

export type WalletSummaryData = {
    available: string;
    period: string;
    referral: string;
    binary: string;
    binaryCycle: string | null;
    lifetime: string;
};

withDefaults(
    defineProps<{
        summary: WalletSummaryData;
        showLink?: boolean;
    }>(),
    { showLink: false },
);
</script>

<template>
    <section
        class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
        :aria-label="$t('Wallet summary')"
    >
        <div class="rounded-xl border p-4" data-test="wallet-available">
            <p class="text-sm text-muted-foreground">
                {{ $t('Available balance') }}
            </p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">
                {{ summary.available }}
            </p>
            <Link
                v-if="showLink"
                :href="wallet()"
                class="mt-2 inline-block text-sm underline underline-offset-4"
                >{{ $t('View wallet') }}</Link
            >
        </div>
        <div class="rounded-xl border p-4" data-test="wallet-referral">
            <p class="text-sm text-muted-foreground">
                {{ $t('Referral income') }} · {{ summary.period }}
            </p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">
                {{ summary.referral }}
            </p>
        </div>
        <div class="rounded-xl border p-4" data-test="wallet-binary">
            <p class="text-sm text-muted-foreground">
                {{ $t('Binary income · last cycle') }}
            </p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">
                {{ summary.binary }}
            </p>
            <p v-if="summary.binaryCycle" class="text-xs text-muted-foreground">
                {{ summary.binaryCycle }}
            </p>
        </div>
        <div class="rounded-xl border p-4" data-test="wallet-lifetime">
            <p class="text-sm text-muted-foreground">
                {{ $t('Total lifetime income') }}
            </p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">
                {{ summary.lifetime }}
            </p>
        </div>
    </section>
</template>
