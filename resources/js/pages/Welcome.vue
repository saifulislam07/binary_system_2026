<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeCheck,
    Clock,
    Languages,
    Mail,
    MapPin,
    Phone,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    UserRound,
    Zap,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, membership, register } from '@/routes';
import { index as checkout } from '@/routes/checkout';

type Product = {
    id: number;
    name: string;
    description: string | null;
    price: string;
    image: string | null;
};

type Contact = {
    phone?: string;
    email?: string;
    address?: string;
    hours?: string;
};

const props = defineProps<{
    packages: Product[];
    startingPrice: string | null;
    sponsorCode: string | null;
    canRegister: boolean;
    contact: Contact;
}>();

const page = usePage();
const signedIn = computed(() => Boolean(page.props.auth.user));

function joinUrl(packageId?: number): string {
    const query: Record<string, string | number> = {};

    if (props.sponsorCode) {
        query.ref = props.sponsorCode;
    }

    if (packageId) {
        query.package = packageId;
    }

    return register.url(Object.keys(query).length ? { query } : undefined);
}

/** "Buy now": members go straight to checkout, visitors sign up first. */
function buyUrl(product: Product): string {
    return signedIn.value
        ? checkout.url({ query: { package: product.id } })
        : joinUrl(product.id);
}

// Decorative tints for products without a photo yet.
const tints = [
    'from-sky-100 to-blue-200 text-blue-700 dark:from-sky-950 dark:to-blue-900 dark:text-blue-200',
    'from-emerald-100 to-teal-200 text-teal-700 dark:from-emerald-950 dark:to-teal-900 dark:text-teal-200',
    'from-amber-100 to-orange-200 text-orange-700 dark:from-amber-950 dark:to-orange-900 dark:text-orange-200',
    'from-violet-100 to-fuchsia-200 text-fuchsia-700 dark:from-violet-950 dark:to-fuchsia-900 dark:text-fuchsia-200',
];

const features = [
    {
        icon: BadgeCheck,
        title: 'Genuine products · আসল পণ্য',
        text: 'Every package is a real product bundle.',
    },
    {
        icon: ShieldCheck,
        title: 'Secure checkout · নিরাপদ পেমেন্ট',
        text: 'Payments are confirmed with the gateway.',
    },
    {
        icon: Zap,
        title: 'Instant confirmation · সঙ্গে সঙ্গে নিশ্চিত',
        text: 'Your order is confirmed the moment you pay.',
    },
    {
        icon: Languages,
        title: 'বাংলা ও English',
        text: 'Shop and manage your account in either.',
    },
];

const steps = [
    {
        title: 'Choose a package · প্যাকেজ বাছুন',
        text: 'Pick the product bundle that suits you.',
    },
    {
        title: 'Create your account · অ্যাকাউন্ট খুলুন',
        text: 'Sign up in two minutes with a referral ID.',
    },
    {
        title: 'Pay securely · নিরাপদে পেমেন্ট',
        text: 'bKash, Nagad or card — your order is confirmed at once.',
    },
];
</script>

<template>
    <Head title="Shop · দোকান" />

    <div class="min-h-screen bg-background text-foreground">
        <!-- Announcement bar -->
        <div class="bg-[#2a78d6] text-white">
            <p class="mx-auto max-w-7xl px-4 py-2 text-center text-sm">
                Secure checkout with bKash, Nagad &amp; card · বিকাশ, নগদ ও
                কার্ডে নিরাপদ পেমেন্ট
            </p>
        </div>

        <!-- Header -->
        <header
            class="sticky top-0 z-30 border-b bg-background/95 backdrop-blur supports-backdrop-filter:bg-background/80"
        >
            <div
                class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4"
            >
                <Link
                    href="/"
                    class="flex items-center gap-2 font-semibold"
                    aria-label="Home"
                >
                    <span
                        class="flex size-9 items-center justify-center rounded-lg bg-[#2a78d6] text-white"
                    >
                        <AppLogoIcon class="size-5" />
                    </span>
                    <span class="hidden sm:inline">{{ page.props.name }}</span>
                </Link>

                <nav
                    class="hidden items-center gap-6 text-sm md:flex"
                    aria-label="Sections"
                >
                    <a href="#shop" class="hover:text-[#2a78d6]"
                        >Shop · দোকান</a
                    >
                    <a href="#how" class="hover:text-[#2a78d6]"
                        >How to order · অর্ডার</a
                    >
                    <a href="#payments" class="hover:text-[#2a78d6]"
                        >Payments · পেমেন্ট</a
                    >
                </nav>

                <div class="flex items-center gap-1">
                    <Button variant="ghost" as-child>
                        <Link
                            :href="signedIn ? dashboard() : login()"
                            data-test="account-link"
                        >
                            <UserRound class="size-5" aria-hidden="true" />
                            <span class="hidden sm:inline">{{
                                signedIn
                                    ? 'My account · আমার অ্যাকাউন্ট'
                                    : 'Log in · লগইন'
                            }}</span>
                            <span class="sr-only sm:hidden">{{
                                signedIn ? 'My account' : 'Log in'
                            }}</span>
                        </Link>
                    </Button>
                    <Button as-child>
                        <a href="#shop">
                            <ShoppingBag class="size-5" aria-hidden="true" />
                            Shop now · কিনুন
                        </a>
                    </Button>
                </div>
            </div>
        </header>

        <main>
            <!-- Hero banner -->
            <section class="mx-auto max-w-7xl px-4 pt-6">
                <div
                    class="grid overflow-hidden rounded-2xl bg-linear-to-br from-[#2a78d6] to-[#154a8c] text-white md:grid-cols-2"
                >
                    <div class="p-8 sm:p-12">
                        <p
                            v-if="sponsorCode"
                            class="mb-5 inline-flex rounded-full bg-white/15 px-3 py-1 text-sm"
                            data-test="sponsor-invite"
                        >
                            Referred by · রেফারেল
                            <strong class="ml-1">{{ sponsorCode }}</strong>
                        </p>
                        <p
                            class="text-sm font-medium tracking-wide text-white/80 uppercase"
                        >
                            Genuine products · আসল পণ্য
                        </p>
                        <h1
                            class="mt-3 text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
                        >
                            Quality products at fair prices.
                        </h1>
                        <p class="mt-3 text-xl text-white/85">
                            মানসম্মত পণ্য, ন্যায্য দামে
                        </p>
                        <p v-if="startingPrice" class="mt-6 text-white/85">
                            Packages from
                            <strong class="text-2xl text-white">{{
                                startingPrice
                            }}</strong>
                        </p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <a
                                href="#shop"
                                class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md bg-white px-6 py-2 text-center font-semibold text-[#154a8c] shadow-sm hover:bg-white/90 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#2a78d6] focus-visible:outline-none sm:w-auto"
                            >
                                Shop packages · প্যাকেজ দেখুন
                                <ArrowRight class="size-4" aria-hidden="true" />
                            </a>
                            <Link
                                v-if="!signedIn && canRegister"
                                :href="joinUrl()"
                                class="inline-flex min-h-11 w-full items-center justify-center rounded-md border border-white/60 px-6 py-2 text-center font-medium hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none sm:w-auto"
                            >
                                Create account · অ্যাকাউন্ট খুলুন
                            </Link>
                        </div>
                    </div>

                    <!-- Product collage -->
                    <div
                        class="relative hidden items-center justify-center p-10 md:flex"
                        aria-hidden="true"
                    >
                        <div class="grid w-full max-w-sm grid-cols-2 gap-4">
                            <div
                                v-for="(product, i) in packages.slice(0, 4)"
                                :key="product.id"
                                class="overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-black/5"
                                :class="i % 2 === 1 ? 'translate-y-6' : ''"
                            >
                                <img
                                    v-if="product.image"
                                    :src="product.image"
                                    alt=""
                                    class="aspect-4/3 w-full object-cover"
                                />
                                <div
                                    v-else
                                    class="flex aspect-4/3 items-center justify-center bg-linear-to-br"
                                    :class="tints[i % tints.length]"
                                >
                                    <AppLogoIcon class="size-10" />
                                </div>
                                <div class="px-3 py-2 text-sm text-slate-900">
                                    <p class="truncate font-medium">
                                        {{ product.name }}
                                    </p>
                                    <p class="font-semibold tabular-nums">
                                        {{ product.price }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Feature strip -->
            <section class="mx-auto max-w-7xl px-4 py-8">
                <ul
                    class="grid grid-cols-1 divide-y rounded-xl border sm:grid-cols-2 sm:divide-x lg:grid-cols-4 lg:divide-y-0"
                >
                    <li
                        v-for="feature in features"
                        :key="feature.title"
                        class="flex items-start gap-3 p-4 sm:p-5"
                    >
                        <component
                            :is="feature.icon"
                            class="mt-0.5 size-6 shrink-0 text-[#2a78d6]"
                            aria-hidden="true"
                        />
                        <div>
                            <p class="text-sm font-semibold">
                                {{ feature.title }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ feature.text }}
                            </p>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- Shop -->
            <section
                id="shop"
                class="mx-auto max-w-7xl scroll-mt-20 px-4 py-8"
                aria-labelledby="shop-title"
            >
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2 id="shop-title" class="text-2xl font-semibold">
                            Shop packages · প্যাকেজ কিনুন
                        </h2>
                        <p class="mt-1 text-muted-foreground">
                            Choose a bundle and check out in minutes.
                        </p>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ packages.length }} products · টি পণ্য
                    </p>
                </div>

                <ul
                    v-if="packages.length > 0"
                    class="mt-6 grid grid-cols-2 gap-3 sm:gap-6 lg:grid-cols-4"
                >
                    <li
                        v-for="(product, i) in packages"
                        :key="product.id"
                        data-test="package-card"
                    >
                        <article
                            class="group flex h-full flex-col overflow-hidden rounded-xl border bg-card transition-shadow hover:shadow-lg"
                        >
                            <div
                                class="relative aspect-4/3 overflow-hidden bg-muted"
                            >
                                <img
                                    v-if="product.image"
                                    :src="product.image"
                                    :alt="product.name"
                                    loading="lazy"
                                    class="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                                />
                                <div
                                    v-else
                                    class="flex size-full flex-col items-center justify-center gap-2 bg-linear-to-br"
                                    :class="tints[i % tints.length]"
                                >
                                    <AppLogoIcon class="size-8 sm:size-12" />
                                    <span class="text-sm font-semibold">{{
                                        product.name
                                    }}</span>
                                </div>
                            </div>
                            <div class="flex flex-1 flex-col p-3 sm:p-4">
                                <h3 class="font-semibold">
                                    {{ product.name }}
                                </h3>
                                <p
                                    v-if="product.description"
                                    class="mt-1 line-clamp-2 text-xs text-muted-foreground sm:text-sm"
                                >
                                    {{ product.description }}
                                </p>
                                <p
                                    class="mt-auto pt-3 text-lg font-bold tracking-tight tabular-nums sm:pt-4 sm:text-2xl"
                                >
                                    {{ product.price }}
                                </p>
                                <Button
                                    v-if="signedIn || canRegister"
                                    class="mt-3 w-full"
                                    as-child
                                >
                                    <Link :href="buyUrl(product)">
                                        <ShoppingCart
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        <span class="sm:hidden"
                                            >Buy · কিনুন</span
                                        >
                                        <span class="hidden sm:inline"
                                            >Buy now · এখনই কিনুন</span
                                        >
                                    </Link>
                                </Button>
                            </div>
                        </article>
                    </li>
                </ul>
                <p
                    v-else
                    class="mt-6 rounded-xl border p-8 text-center text-muted-foreground"
                >
                    New packages are coming soon · শীঘ্রই নতুন প্যাকেজ আসছে
                </p>
            </section>

            <!-- How it works -->
            <section
                id="how"
                class="scroll-mt-20 border-y bg-muted/40"
                aria-labelledby="how-title"
            >
                <div class="mx-auto max-w-7xl px-4 py-12">
                    <h2 id="how-title" class="text-2xl font-semibold">
                        How to order · কীভাবে অর্ডার করবেন
                    </h2>
                    <ol class="mt-8 grid gap-6 md:grid-cols-3">
                        <li
                            v-for="(step, i) in steps"
                            :key="step.title"
                            class="flex gap-4"
                        >
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#2a78d6] font-semibold text-white"
                                >{{ i + 1 }}</span
                            >
                            <div>
                                <h3 class="font-semibold">{{ step.title }}</h3>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ step.text }}
                                </p>
                            </div>
                        </li>
                    </ol>
                </div>
            </section>

            <!-- Payments -->
            <section
                id="payments"
                class="mx-auto max-w-7xl scroll-mt-20 px-4 py-12"
                aria-labelledby="payments-title"
            >
                <div
                    class="flex flex-col items-start justify-between gap-6 rounded-2xl border p-6 sm:p-8 md:flex-row md:items-center"
                >
                    <div>
                        <h2 id="payments-title" class="text-xl font-semibold">
                            Pay your way · আপনার সুবিধামতো পেমেন্ট
                        </h2>
                        <p class="mt-1 max-w-xl text-sm text-muted-foreground">
                            Every payment is confirmed directly with the gateway
                            before your order completes, and every taka lands on
                            your wallet ledger.
                        </p>
                    </div>
                    <ul
                        class="flex flex-wrap gap-2"
                        aria-label="Accepted payment methods"
                    >
                        <li
                            class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium"
                        >
                            <span class="size-2.5 rounded-full bg-[#e2136e]" />
                            bKash · বিকাশ
                        </li>
                        <li
                            class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium"
                        >
                            <span class="size-2.5 rounded-full bg-[#f6921e]" />
                            Nagad · নগদ
                        </li>
                        <li
                            class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium"
                        >
                            <span class="size-2.5 rounded-full bg-[#2a78d6]" />
                            Visa · Mastercard · Amex
                        </li>
                    </ul>
                </div>
            </section>

            <!-- Call to action -->
            <section
                v-if="!signedIn && canRegister"
                class="mx-auto max-w-7xl px-4 pb-12"
            >
                <div
                    class="flex flex-col items-start justify-between gap-4 rounded-2xl bg-slate-900 p-8 text-white sm:flex-row sm:items-center dark:bg-slate-800"
                >
                    <div>
                        <p class="text-xl font-semibold">
                            Ready to start? · শুরু করতে প্রস্তুত?
                        </p>
                        <p class="mt-1 text-white/75">
                            Create your account in two minutes.
                        </p>
                    </div>
                    <Link
                        :href="joinUrl()"
                        class="inline-flex h-11 items-center gap-2 rounded-md bg-white px-6 font-semibold text-slate-900 hover:bg-white/90 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                    >
                        Create account · অ্যাকাউন্ট খুলুন
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </div>
            </section>
        </main>

        <footer class="bg-slate-950 text-slate-300">
            <div
                class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div class="lg:col-span-1">
                    <div
                        class="flex items-center gap-2 font-semibold text-white"
                    >
                        <span
                            class="flex size-8 items-center justify-center rounded-md bg-[#2a78d6]"
                        >
                            <AppLogoIcon class="size-5" />
                        </span>
                        {{ page.props.name }}
                    </div>
                    <p class="mt-4 text-sm text-slate-400">
                        Genuine products at fair prices.
                    </p>
                </div>

                <nav aria-label="Shop">
                    <p class="font-semibold text-white">Shop · দোকান</p>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li v-for="product in packages" :key="product.id">
                            <Link
                                :href="buyUrl(product)"
                                class="hover:text-white"
                                >{{ product.name }} — {{ product.price }}</Link
                            >
                        </li>
                    </ul>
                </nav>

                <nav aria-label="Account">
                    <p class="font-semibold text-white">Account · অ্যাকাউন্ট</p>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li v-if="signedIn">
                            <Link :href="dashboard()" class="hover:text-white"
                                >My dashboard · ড্যাশবোর্ড</Link
                            >
                        </li>
                        <template v-else>
                            <li>
                                <Link :href="login()" class="hover:text-white"
                                    >Log in · লগইন</Link
                                >
                            </li>
                            <li v-if="canRegister">
                                <Link :href="joinUrl()" class="hover:text-white"
                                    >Create account · অ্যাকাউন্ট খুলুন</Link
                                >
                            </li>
                        </template>
                        <li>
                            <Link
                                :href="membership()"
                                class="hover:text-white"
                                data-test="membership-link"
                                >Membership &amp; earnings · সদস্যপদ ও আয়</Link
                            >
                        </li>
                        <li>
                            <a href="#how" class="hover:text-white"
                                >How to order · কীভাবে অর্ডার করবেন</a
                            >
                        </li>
                    </ul>
                </nav>

                <div v-if="Object.keys(contact).length" data-test="contact">
                    <p class="font-semibold text-white">Contact · যোগাযোগ</p>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li v-if="contact.phone" class="flex gap-2">
                            <Phone
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <a
                                :href="`tel:${contact.phone}`"
                                class="hover:text-white"
                                >{{ contact.phone }}</a
                            >
                        </li>
                        <li v-if="contact.email" class="flex gap-2">
                            <Mail
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <a
                                :href="`mailto:${contact.email}`"
                                class="hover:text-white"
                                >{{ contact.email }}</a
                            >
                        </li>
                        <li v-if="contact.address" class="flex gap-2">
                            <MapPin
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span>{{ contact.address }}</span>
                        </li>
                        <li v-if="contact.hours" class="flex gap-2">
                            <Clock
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span>{{ contact.hours }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-slate-800">
                <div
                    class="mx-auto max-w-7xl space-y-2 px-4 py-6 text-xs text-slate-400"
                >
                    <p>
                        © {{ new Date().getFullYear() }} {{ page.props.name }} ·
                        Prices in BDT · দাম টাকায়
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
