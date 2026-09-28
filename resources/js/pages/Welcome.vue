<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Network,
    ShieldCheck,
    ShoppingBag,
    UserPlus,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

type PackageCard = {
    id: number;
    name: string;
    description: string | null;
    price: string;
    bv: string;
    qualifying: boolean;
};

const props = defineProps<{
    packages: PackageCard[];
    sponsorCode: string | null;
    canRegister: boolean;
}>();

const page = usePage();
const signedIn = computed(() => Boolean(page.props.auth.user));
const joinUrl = computed(() =>
    register.url(
        props.sponsorCode ? { query: { ref: props.sponsorCode } } : undefined,
    ),
);

const steps = [
    {
        icon: UserPlus,
        title: 'Register with a sponsor ID · স্পনসরের আইডি দিয়ে নিবন্ধন',
        text: 'Sign up with the member ID of the person who invited you, and choose the left or right side of their team.',
    },
    {
        icon: ShoppingBag,
        title: 'Activate with a package · প্যাকেজ কিনে সক্রিয় হোন',
        text: 'Pay by bKash, Nagad or card. You get your own member ID and your place in the tree straight away.',
    },
    {
        icon: Network,
        title: 'Grow both sides of your team · দুই পাশে দল গড়ুন',
        text: 'Genuine package sales in your team add business volume (BV) to your left and right legs; matched volume earns commission.',
    },
];

const promises = [
    {
        icon: ShieldCheck,
        title: 'Payments confirmed with the gateway · যাচাইকৃত পেমেন্ট',
        text: 'Every payment is verified directly with bKash, Nagad or SSLCommerz before anything is credited.',
    },
    {
        icon: Wallet,
        title: 'Every taka on your ledger · প্রতিটি টাকার হিসাব',
        text: 'Your wallet shows each commission, bonus and withdrawal — the balance is always the sum of its entries.',
    },
    {
        icon: BadgeCheck,
        title: 'Verified withdrawals · নিরাপদ উত্তোলন',
        text: 'Withdraw to bKash, Nagad or your bank after identity (KYC) checks, reviewed by our team.',
    },
];
</script>

<template>
    <Head title="Welcome · স্বাগতম" />

    <div class="min-h-screen bg-background text-foreground">
        <header class="border-b">
            <div
                class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4"
            >
                <Link
                    href="/"
                    class="flex items-center gap-2 font-semibold"
                    aria-label="Home"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-md bg-[#2a78d6] text-white"
                    >
                        <AppLogoIcon class="size-5" />
                    </span>
                    <span>{{ page.props.name }}</span>
                </Link>
                <nav class="flex items-center gap-2" aria-label="Account">
                    <Button v-if="signedIn" as-child>
                        <Link :href="dashboard()">Dashboard · ড্যাশবোর্ড</Link>
                    </Button>
                    <template v-else>
                        <!-- On phones the hero's own log-in button is enough. -->
                        <Button
                            variant="ghost"
                            class="hidden sm:inline-flex"
                            as-child
                        >
                            <Link :href="login()">Log in · লগইন</Link>
                        </Button>
                        <Button v-if="canRegister" as-child>
                            <Link :href="joinUrl">Join · যোগ দিন</Link>
                        </Button>
                    </template>
                </nav>
            </div>
        </header>

        <main>
            <section class="mx-auto max-w-6xl px-4 py-16 sm:py-24">
                <p
                    v-if="sponsorCode"
                    class="mb-4 inline-flex rounded-full border px-3 py-1 text-sm"
                    data-test="sponsor-invite"
                >
                    Invited by sponsor · স্পনসর
                    <strong class="ml-1">{{ sponsorCode }}</strong>
                </p>
                <h1
                    class="max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
                >
                    Build a business on real products, together with your team
                </h1>
                <p class="mt-3 text-xl text-muted-foreground sm:text-2xl">
                    আসল পণ্যে, নিজের দলের সাথে গড়ে তুলুন আপনার ব্যবসা
                </p>
                <p class="mt-6 max-w-2xl text-lg text-muted-foreground">
                    Buy a package, share your sponsor ID, and earn from the
                    genuine sales your team makes — paid into your wallet and
                    withdrawn to bKash, Nagad or your bank.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <Button v-if="signedIn" size="lg" as-child>
                        <Link :href="dashboard()"
                            >Go to your dashboard · ড্যাশবোর্ডে যান</Link
                        >
                    </Button>
                    <template v-else>
                        <Button v-if="canRegister" size="lg" as-child>
                            <Link :href="joinUrl">Join now · এখনই যোগ দিন</Link>
                        </Button>
                        <Button size="lg" variant="outline" as-child>
                            <Link :href="login()"
                                >I'm already a member · লগইন</Link
                            >
                        </Button>
                    </template>
                </div>
            </section>

            <section class="border-t bg-muted/30" aria-labelledby="how">
                <div class="mx-auto max-w-6xl px-4 py-16">
                    <h2 id="how" class="text-2xl font-semibold">
                        How it works · কীভাবে কাজ করে
                    </h2>
                    <ol class="mt-8 grid gap-6 md:grid-cols-3">
                        <li
                            v-for="(step, i) in steps"
                            :key="step.title"
                            class="rounded-xl border bg-card p-6"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#2a78d6]/10 text-[#2a78d6]"
                                >
                                    <component
                                        :is="step.icon"
                                        class="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span class="text-sm text-muted-foreground"
                                    >Step {{ i + 1 }} · ধাপ {{ i + 1 }}</span
                                >
                            </div>
                            <h3 class="mt-4 font-semibold">{{ step.title }}</h3>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ step.text }}
                            </p>
                        </li>
                    </ol>
                </div>
            </section>

            <section
                v-if="packages.length > 0"
                class="border-t"
                aria-labelledby="packages"
            >
                <div class="mx-auto max-w-6xl px-4 py-16">
                    <h2 id="packages" class="text-2xl font-semibold">
                        Packages · প্যাকেজ
                    </h2>
                    <p class="mt-2 text-muted-foreground">
                        One package activates your membership. BV is the
                        business volume it adds to your upline's team.
                    </p>
                    <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <li
                            v-for="pkg in packages"
                            :key="pkg.id"
                            class="flex flex-col rounded-xl border bg-card p-6"
                            data-test="package-card"
                        >
                            <h3 class="font-semibold">{{ pkg.name }}</h3>
                            <p
                                class="mt-3 text-3xl font-semibold tracking-tight tabular-nums"
                            >
                                {{ pkg.price }}
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ pkg.bv }} BV
                            </p>
                            <p
                                v-if="pkg.description"
                                class="mt-4 text-sm text-muted-foreground"
                            >
                                {{ pkg.description }}
                            </p>
                            <div class="mt-auto pt-6">
                                <Button
                                    v-if="!signedIn && canRegister"
                                    variant="outline"
                                    class="w-full"
                                    as-child
                                >
                                    <Link :href="joinUrl"
                                        >Join with {{ pkg.name }}</Link
                                    >
                                </Button>
                            </div>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="border-t bg-muted/30" aria-labelledby="trust">
                <div class="mx-auto max-w-6xl px-4 py-16">
                    <h2 id="trust" class="sr-only">Our promises</h2>
                    <ul class="grid gap-6 md:grid-cols-3">
                        <li
                            v-for="promise in promises"
                            :key="promise.title"
                            class="flex gap-4"
                        >
                            <component
                                :is="promise.icon"
                                class="mt-0.5 size-6 shrink-0 text-[#2a78d6]"
                                aria-hidden="true"
                            />
                            <div>
                                <h3 class="font-semibold">
                                    {{ promise.title }}
                                </h3>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ promise.text }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </div>
            </section>
        </main>

        <footer class="border-t">
            <div
                class="mx-auto max-w-6xl space-y-2 px-4 py-8 text-sm text-muted-foreground"
            >
                <p data-test="earnings-disclaimer">
                    Earnings depend on genuine product sales by you and your
                    team. There is no guaranteed income, and nothing is paid for
                    recruiting alone.
                </p>
                <p>
                    আয় নির্ভর করে আপনার ও আপনার দলের প্রকৃত পণ্য বিক্রির উপর।
                    কোনো নিশ্চিত আয় নেই, শুধু সদস্য আনার জন্য কোনো অর্থ দেওয়া
                    হয় না।
                </p>
                <p>© {{ new Date().getFullYear() }} {{ page.props.name }}</p>
            </div>
        </footer>
    </div>
</template>
