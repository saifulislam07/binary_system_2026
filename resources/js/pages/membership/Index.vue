<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { home, register } from '@/routes';

type Rates = {
    referral: string;
    binary: string;
    dailyCap: string | null;
    weeklyCap: string | null;
    monthlyCap: string | null;
    carryForward: boolean;
    minWithdrawal: string;
};

defineProps<{
    rates: Rates;
    ranks: string[];
}>();
</script>

<template>
    <Head title="Membership & earnings · সদস্যপদ ও আয়" />

    <div class="mx-auto max-w-3xl px-4 py-12">
        <h1 class="text-3xl font-semibold tracking-tight">
            Membership &amp; earnings
        </h1>
        <p class="mt-1 text-xl text-muted-foreground">সদস্যপদ ও আয়</p>

        <p
            class="mt-8 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
            data-test="earnings-disclaimer"
        >
            <strong>Please read before you join.</strong> Buying a package makes
            you a member of a sponsor-based network. Earnings depend on genuine
            product sales by you and your team; there is no guaranteed income,
            and nothing is paid for recruiting alone.
            <span class="mt-2 block" lang="bn"
                >যোগ দেওয়ার আগে পড়ুন। প্যাকেজ কিনলে আপনি একটি স্পনসর-ভিত্তিক
                নেটওয়ার্কের সদস্য হন। আয় নির্ভর করে আপনার ও আপনার দলের প্রকৃত
                পণ্য বিক্রির উপর; কোনো নিশ্চিত আয় নেই, শুধু সদস্য আনার জন্য
                কোনো অর্থ দেওয়া হয় না।</span
            >
        </p>

        <section class="mt-10 space-y-3">
            <h2 class="text-xl font-semibold">
                How membership works · সদস্যপদ কীভাবে কাজ করে
            </h2>
            <ul class="list-disc space-y-2 pl-5 text-muted-foreground">
                <li>
                    You join with the member ID of the person who invited you
                    (your <strong>sponsor</strong>), and buy one package. Paying
                    activates your account and gives you your own member ID.
                </li>
                <li>
                    Members are arranged in a <strong>binary tree</strong>: each
                    member has one left and one right position. If the side you
                    choose under your sponsor is taken, you are placed in the
                    first free spot further down that side.
                </li>
                <li>
                    Every package carries a
                    <strong>business volume (BV)</strong>. When someone in your
                    team buys a package, its BV is added to your left or right
                    side.
                </li>
            </ul>
        </section>

        <section class="mt-10 space-y-3">
            <h2 class="text-xl font-semibold">
                What members can earn · সদস্যরা কী আয় করতে পারেন
            </h2>
            <ul class="list-disc space-y-2 pl-5 text-muted-foreground">
                <li>
                    <strong>Referral bonus:</strong>
                    {{ rates.referral }} of a qualifying package bought by
                    someone you sponsored.
                </li>
                <li>
                    <strong>Binary commission:</strong>
                    {{ rates.binary }} of the volume matched between your left
                    and right sides each cycle (the smaller side's BV).
                    {{
                        rates.carryForward
                            ? 'Unmatched volume carries over to the next cycle.'
                            : 'Unmatched volume does not carry over.'
                    }}
                </li>
                <li
                    v-if="rates.dailyCap || rates.weeklyCap || rates.monthlyCap"
                >
                    Binary commission is capped at
                    <template v-if="rates.dailyCap"
                        >{{ rates.dailyCap }} a day</template
                    ><template v-if="rates.dailyCap && rates.weeklyCap"
                        >, </template
                    ><template v-if="rates.weeklyCap"
                        >{{ rates.weeklyCap }} a week</template
                    ><template
                        v-if="
                            (rates.dailyCap || rates.weeklyCap) &&
                            rates.monthlyCap
                        "
                        >, </template
                    ><template v-if="rates.monthlyCap"
                        >{{ rates.monthlyCap }} a month</template
                    >.
                </li>
                <li v-if="ranks.length">
                    <strong>Ranks:</strong> {{ ranks.join(' → ') }}. Reaching a
                    rank's sales and team requirements pays a one-time rank
                    bonus.
                </li>
                <li>
                    Earnings go into your wallet. You can withdraw from
                    {{ rates.minWithdrawal }} to bKash, Nagad or a bank account
                    after identity (KYC) checks.
                </li>
                <li>
                    If a sale is refunded, the volume and commission it
                    generated are reversed.
                </li>
            </ul>
        </section>

        <div class="mt-12 flex flex-wrap gap-3">
            <Button as-child>
                <Link :href="register()"
                    >Create account · অ্যাকাউন্ট খুলুন</Link
                >
            </Button>
            <Button variant="outline" as-child>
                <Link :href="home()">Back to shop · দোকানে ফিরুন</Link>
            </Button>
        </div>
    </div>
</template>
