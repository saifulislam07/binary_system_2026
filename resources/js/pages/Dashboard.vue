<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import IncomeChart from '@/components/IncomeChart.vue';
import type { IncomePoint } from '@/components/IncomeChart.vue';
import RankProgress from '@/components/RankProgress.vue';
import type { RankData } from '@/components/RankProgress.vue';
import WalletSummary from '@/components/WalletSummary.vue';
import type { WalletSummaryData } from '@/components/WalletSummary.vue';
import { dashboard } from '@/routes';
import { index as checkout } from '@/routes/checkout';
import { index as team } from '@/routes/team';

type LegCount = { total: number; active: number };

defineProps<{
    member: {
        code: string | null;
        status: string;
        package: string | null;
    } | null;
    walletSummary: WalletSummaryData | null;
    overview: {
        totalIncome: string;
        available: string;
        personalSales: string;
        leftTeamBv: string;
        rightTeamBv: string;
        teamSize: number;
        activeTeam: number;
        leftTeam: LegCount;
        rightTeam: LegCount;
        chart: IncomePoint[];
    } | null;
    rank: RankData | null;
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

    <div class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-4">
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
            <span v-if="member.package"> · {{ member.package }} package</span>
        </div>

        <WalletSummary
            v-if="walletSummary"
            :summary="walletSummary"
            show-link
        />

        <template v-if="overview">
            <section
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
                aria-label="Team overview"
            >
                <div class="rounded-xl border p-4" data-test="personal-sales">
                    <p class="text-sm text-muted-foreground">
                        Personal sales · ব্যক্তিগত বিক্রয়
                    </p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ overview.personalSales }}
                    </p>
                </div>
                <div class="rounded-xl border p-4" data-test="left-team">
                    <p class="text-sm text-muted-foreground">
                        Left team sales · বাম দল
                    </p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ overview.leftTeamBv }} BV
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ overview.leftTeam.total }} members ·
                        {{ overview.leftTeam.active }} active
                    </p>
                </div>
                <div class="rounded-xl border p-4" data-test="right-team">
                    <p class="text-sm text-muted-foreground">
                        Right team sales · ডান দল
                    </p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ overview.rightTeamBv }} BV
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ overview.rightTeam.total }} members ·
                        {{ overview.rightTeam.active }} active
                    </p>
                </div>
                <div class="rounded-xl border p-4" data-test="team-size">
                    <p class="text-sm text-muted-foreground">
                        Team size · দলের সদস্য
                    </p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ overview.teamSize }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ overview.activeTeam }} active ·
                        <Link
                            :href="team()"
                            class="underline underline-offset-4"
                            >View tree</Link
                        >
                    </p>
                </div>
            </section>

            <div class="grid min-w-0 gap-4 lg:grid-cols-3">
                <div class="min-w-0 lg:col-span-2">
                    <IncomeChart
                        :points="overview.chart"
                        title="Net income, last 6 cycles · আয়"
                    />
                </div>
                <RankProgress v-if="rank" :rank="rank" />
            </div>
        </template>
    </div>
</template>
