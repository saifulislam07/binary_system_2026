<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgePercent,
    GitFork,
    Info,
    Trophy,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import { t } from '@/lib/i18n';
import { home, register } from '@/routes';

type Rates = {
    referral: string;
    binary: string;
    dailyCap: string | null;
    weeklyCap: string | null;
    monthlyCap: string | null;
    overCap: 'void' | 'carry_forward';
    carryForward: boolean;
    minWithdrawal: string;
};

type Section = { id: number; title: string; body: string };
type PackageRow = {
    id: number;
    name: string;
    price: string;
    bv: string;
    referralBonus: string | null;
};
type RankRow = {
    name: string;
    personalSales: string | null;
    teamBv: string | null;
    activeTeam: number | null;
    bonus: string | null;
};
type BonusRuleRow = {
    id: number;
    type: string;
    name: string;
    threshold: string;
    amount: string;
};

const props = defineProps<{
    sections: Section[];
    rates: Rates;
    packages: PackageRow[];
    ranks: RankRow[];
    bonusRules: BonusRuleRow[];
}>();

const caps = computed(() =>
    [
        props.rates.dailyCap &&
            t(':amount a day', { amount: props.rates.dailyCap }),
        props.rates.weeklyCap &&
            t(':amount a week', { amount: props.rates.weeklyCap }),
        props.rates.monthlyCap &&
            t(':amount a month', { amount: props.rates.monthlyCap }),
    ]
        .filter(Boolean)
        .join(', '),
);

const facts = computed(() => [
    {
        icon: BadgePercent,
        label: 'Referral bonus',
        value: props.rates.referral,
        text: 'of a qualifying package bought by someone you sponsor',
    },
    {
        icon: GitFork,
        label: 'Binary commission',
        value: props.rates.binary,
        text: 'of the volume matched between your left and right sides',
    },
    {
        icon: Wallet,
        label: 'Minimum withdrawal',
        value: props.rates.minWithdrawal,
        text: 'to bKash, Nagad or a bank account',
    },
]);
</script>

<template>
    <Head :title="$t('Membership & earnings')" />

    <!-- Header -->
    <section class="border-b bg-surface">
        <div class="mx-auto max-w-5xl px-4 py-12 sm:py-16">
            <p class="text-sm font-semibold text-brand">
                {{ $t('Membership') }}
            </p>
            <h1
                class="mt-2 text-3xl font-semibold tracking-tight text-balance sm:text-4xl"
            >
                {{ $t('Membership & earnings') }}
            </h1>
            <p class="mt-3 max-w-2xl text-lg text-muted-foreground">
                {{
                    $t(
                        'How the membership works and what members can earn — with the current rates.',
                    )
                }}
            </p>

            <div
                class="mt-8 flex gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-5 text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
                data-test="earnings-disclaimer"
            >
                <Info
                    class="mt-0.5 size-5 shrink-0 text-amber-600"
                    aria-hidden="true"
                />
                <p class="text-sm leading-relaxed">
                    <strong>{{ $t('Please read before you join.') }}</strong>
                    {{
                        $t(
                            'Buying a package makes you a member of a sponsor-based network. Earnings depend on genuine product sales by you and your team; there is no guaranteed income, and nothing is paid for recruiting alone.',
                        )
                    }}
                </p>
            </div>
        </div>
    </section>

    <div class="mx-auto max-w-5xl px-4 py-12">
        <!-- Key numbers -->
        <ul class="grid gap-4 sm:grid-cols-3" data-test="key-rates">
            <li
                v-for="fact in facts"
                :key="fact.label"
                class="rounded-2xl border bg-card p-5"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-xl bg-brand-soft text-brand"
                >
                    <component
                        :is="fact.icon"
                        class="size-5"
                        aria-hidden="true"
                    />
                </span>
                <p class="mt-4 text-sm text-muted-foreground">
                    {{ $t(fact.label) }}
                </p>
                <p
                    class="mt-1 text-3xl font-semibold tracking-tight tabular-nums"
                >
                    {{ fact.value }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ $t(fact.text) }}
                </p>
            </li>
        </ul>

        <!-- Admin-written sections -->
        <section
            v-for="section in sections"
            :key="section.id"
            class="mt-12"
            :aria-labelledby="`section-${section.id}`"
            data-test="membership-section"
        >
            <h2
                :id="`section-${section.id}`"
                class="text-xl font-semibold tracking-tight sm:text-2xl"
            >
                {{ section.title }}
            </h2>
            <!-- Sanitized on the server (App\Support\RichText). -->
            <div class="rich-text mt-4 max-w-3xl" v-html="section.body" />
        </section>

        <!-- Packages -->
        <section
            v-if="packages.length"
            class="mt-12"
            aria-labelledby="packages-title"
        >
            <h2
                id="packages-title"
                class="text-xl font-semibold tracking-tight sm:text-2xl"
            >
                {{ $t('Packages') }}
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
                {{
                    $t(
                        'Each package is a bundle of our products. Its BV is what counts toward team volume.',
                    )
                }}
            </p>
            <div class="mt-5 overflow-x-auto rounded-2xl border">
                <table class="w-full min-w-130 text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">
                                {{ $t('Package') }}
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                {{ $t('Price') }}
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                {{ $t('Business volume (BV)') }}
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                {{ $t('Referral bonus to the sponsor') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="pkg in packages"
                            :key="pkg.id"
                            class="border-t"
                            data-test="package-row"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ pkg.name }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ pkg.price }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ pkg.bv }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ pkg.referralBonus ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Binary commission -->
        <section class="mt-12" aria-labelledby="binary-title">
            <h2
                id="binary-title"
                class="text-xl font-semibold tracking-tight sm:text-2xl"
            >
                {{ $t('Binary commission') }}
            </h2>
            <ul class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <li class="rounded-xl bg-surface p-4">
                    {{
                        $t(
                            ":rate of the volume matched between your left and right sides each cycle (the smaller side's BV).",
                            { rate: rates.binary },
                        )
                    }}
                </li>
                <li class="rounded-xl bg-surface p-4">
                    {{
                        rates.carryForward
                            ? $t(
                                  'Unmatched volume carries over to the next cycle.',
                              )
                            : $t('Unmatched volume does not carry over.')
                    }}
                </li>
                <li v-if="caps" class="rounded-xl bg-surface p-4">
                    {{ $t('Binary commission is capped at :caps.', { caps }) }}
                </li>
                <li v-if="caps" class="rounded-xl bg-surface p-4">
                    {{
                        rates.overCap === 'carry_forward'
                            ? $t(
                                  'Commission above a cap is held and paid in later cycles.',
                              )
                            : $t('Commission above a cap is not paid.')
                    }}
                </li>
            </ul>
        </section>

        <!-- Ranks -->
        <section
            v-if="ranks.length"
            class="mt-12"
            aria-labelledby="ranks-title"
        >
            <h2
                id="ranks-title"
                class="flex items-center gap-2 text-xl font-semibold tracking-tight sm:text-2xl"
            >
                <Trophy class="size-5 text-amber-500" aria-hidden="true" />
                {{ $t('Ranks') }}
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
                {{
                    $t(
                        "Reaching a rank's sales and team requirements pays a one-time rank bonus.",
                    )
                }}
            </p>
            <div class="mt-5 overflow-x-auto rounded-2xl border">
                <table class="w-full min-w-155 text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">
                                {{ $t('Rank') }}
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                {{ $t('Personal sales') }}
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                {{ $t('Team volume (BV)') }}
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                {{ $t('Active team') }}
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                {{ $t('One-time bonus') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="rank in ranks"
                            :key="rank.name"
                            class="border-t"
                            data-test="rank-row"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ $t(rank.name) }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ rank.personalSales ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ rank.teamBv ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ rank.activeTeam ?? '—' }}
                            </td>
                            <td
                                class="px-4 py-3 text-right font-medium tabular-nums"
                            >
                                {{ rank.bonus ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Bonus rules -->
        <section
            v-if="bonusRules.length"
            class="mt-12"
            aria-labelledby="bonuses-title"
        >
            <h2
                id="bonuses-title"
                class="text-xl font-semibold tracking-tight sm:text-2xl"
            >
                {{ $t('Other bonuses') }}
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ $t('Each is paid once, when you first reach it.') }}
            </p>
            <ul class="mt-5 grid gap-3 sm:grid-cols-2">
                <li
                    v-for="rule in bonusRules"
                    :key="rule.id"
                    class="flex items-center justify-between gap-4 rounded-2xl border bg-card p-4"
                    data-test="bonus-rule"
                >
                    <div>
                        <p class="font-medium">{{ rule.name }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                rule.type === 'sales'
                                    ? $t('Personal sales of :amount', {
                                          amount: rule.threshold,
                                      })
                                    : $t(':count active team members', {
                                          count: rule.threshold,
                                      })
                            }}
                        </p>
                    </div>
                    <p class="text-lg font-semibold tabular-nums">
                        {{ rule.amount }}
                    </p>
                </li>
            </ul>
        </section>

        <!-- Withdrawals -->
        <section class="mt-12" aria-labelledby="withdrawals-title">
            <h2
                id="withdrawals-title"
                class="text-xl font-semibold tracking-tight sm:text-2xl"
            >
                {{ $t('Getting paid') }}
            </h2>
            <p class="mt-3 max-w-3xl text-muted-foreground">
                {{
                    $t(
                        'Earnings go into your wallet. Once your identity (NID or passport) is verified, you can withdraw from :amount to bKash, Nagad or a bank account; an admin reviews every withdrawal before it is paid.',
                        { amount: rates.minWithdrawal },
                    )
                }}
            </p>
        </section>

        <div
            class="mt-14 flex flex-col items-start justify-between gap-4 rounded-3xl bg-brand p-8 text-white sm:flex-row sm:items-center"
        >
            <p class="text-xl font-semibold">
                {{ $t('Ready to start?') }}
            </p>
            <div class="flex flex-wrap gap-3">
                <Link
                    :href="register()"
                    class="inline-flex h-11 items-center gap-2 rounded-xl bg-white px-5 font-semibold text-brand hover:bg-white/90"
                    >{{ $t('Create account') }}
                    <ArrowRight class="size-4" aria-hidden="true"
                /></Link>
                <Link
                    :href="home()"
                    class="inline-flex h-11 items-center rounded-xl px-5 font-medium ring-1 ring-white/40 hover:bg-white/10"
                    >{{ $t('Back to shop') }}</Link
                >
            </div>
        </div>
    </div>
</template>
