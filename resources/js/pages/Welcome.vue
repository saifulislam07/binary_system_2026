<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeCheck,
    Languages,
    ShieldCheck,
    ShoppingCart,
    Zap,
} from '@lucide/vue';
import { computed, onMounted } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ProductCard from '@/components/shop/ProductCard.vue';
import { Button } from '@/components/ui/button';
import { buyPackageUrl, joinUrl, rememberReferral, tints } from '@/lib/shop';
import type { ProductCardData } from '@/lib/shop';
import { index as shopIndex } from '@/routes/shop';

type CategoryTile = {
    slug: string;
    name: string;
    nameBn: string | null;
    count: number;
    image: string | null;
};

type PackageCard = {
    id: number;
    name: string;
    description: string | null;
    price: string;
    image: string | null;
};

const props = defineProps<{
    categories: CategoryTile[];
    featured: ProductCardData[];
    packages: PackageCard[];
    startingPrice: string | null;
    sponsorCode: string | null;
}>();

const page = usePage();
const signedIn = computed(() => Boolean(page.props.auth.user));
const canRegister = computed(
    () =>
        (page.props.shop as { canRegister?: boolean } | null)?.canRegister ??
        false,
);

onMounted(() => rememberReferral(props.sponsorCode));

const features = [
    {
        icon: BadgeCheck,
        title: 'Genuine products · আসল পণ্য',
        text: 'Real products, sold in clear bundles.',
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
        text: 'Each package is a bundle of our products.',
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
    <Head title="Shop electronics · ইলেকট্রনিক্স" />

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
                    Genuine electronics · আসল ইলেকট্রনিক্স
                </p>
                <h1
                    class="mt-3 text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
                >
                    Quality gadgets at fair prices.
                </h1>
                <p class="mt-3 text-xl text-white/85">
                    মানসম্মত গ্যাজেট, ন্যায্য দামে
                </p>
                <p v-if="startingPrice" class="mt-6 text-white/85">
                    Packages from
                    <strong class="text-2xl text-white">{{
                        startingPrice
                    }}</strong>
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <Link
                        :href="shopIndex()"
                        class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md bg-white px-6 py-2 text-center font-semibold text-[#154a8c] shadow-sm hover:bg-white/90 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#2a78d6] focus-visible:outline-none sm:w-auto"
                    >
                        Shop now · কেনাকাটা করুন
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                    <a
                        href="#packages"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-md border border-white/60 px-6 py-2 text-center font-medium hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none sm:w-auto"
                    >
                        See packages · প্যাকেজ দেখুন
                    </a>
                </div>
            </div>

            <!-- Product collage -->
            <div
                class="relative hidden items-center justify-center p-10 md:flex"
                aria-hidden="true"
            >
                <div class="grid w-full max-w-sm grid-cols-2 gap-4">
                    <div
                        v-for="(product, i) in featured.slice(0, 4)"
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
                    <p class="text-sm font-semibold">{{ feature.title }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ feature.text }}
                    </p>
                </div>
            </li>
        </ul>
    </section>

    <!-- Shop by category -->
    <section
        v-if="categories.length"
        class="mx-auto max-w-7xl px-4 py-6"
        aria-labelledby="categories-title"
    >
        <h2 id="categories-title" class="text-2xl font-semibold">
            Shop by category · ক্যাটাগরি
        </h2>
        <ul
            class="mt-6 grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-6"
        >
            <li v-for="(category, i) in categories" :key="category.slug">
                <Link
                    :href="shopIndex({ query: { category: category.slug } })"
                    class="group block overflow-hidden rounded-xl border bg-card transition-shadow hover:shadow-md focus-visible:ring-2 focus-visible:ring-[#2a78d6] focus-visible:outline-none"
                    data-test="category-tile"
                >
                    <div class="aspect-4/3 overflow-hidden bg-muted">
                        <img
                            v-if="category.image"
                            :src="category.image"
                            alt=""
                            loading="lazy"
                            class="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                        />
                        <div
                            v-else
                            class="flex size-full items-center justify-center bg-linear-to-br"
                            :class="tints[i % tints.length]"
                        >
                            <AppLogoIcon class="size-8" />
                        </div>
                    </div>
                    <div class="p-3">
                        <p class="text-sm font-semibold">{{ category.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            <template v-if="category.nameBn"
                                >{{ category.nameBn }} · </template
                            >{{ category.count }} items
                        </p>
                    </div>
                </Link>
            </li>
        </ul>
    </section>

    <!-- Featured products -->
    <section
        v-if="featured.length"
        class="mx-auto max-w-7xl px-4 py-8"
        aria-labelledby="featured-title"
    >
        <div class="flex flex-wrap items-end justify-between gap-2">
            <h2 id="featured-title" class="text-2xl font-semibold">
                Featured products · জনপ্রিয় পণ্য
            </h2>
            <Link
                :href="shopIndex()"
                class="inline-flex items-center gap-1 text-sm font-medium text-[#2a78d6] hover:underline"
                >View all · সব দেখুন
                <ArrowRight class="size-4" aria-hidden="true"
            /></Link>
        </div>
        <ul class="mt-6 grid grid-cols-2 gap-3 sm:gap-6 lg:grid-cols-4">
            <li v-for="(product, i) in featured" :key="product.id">
                <ProductCard :product="product" :index="i" />
            </li>
        </ul>
    </section>

    <!-- Packages -->
    <section
        v-if="packages.length"
        id="packages"
        class="mx-auto max-w-7xl scroll-mt-32 px-4 py-8"
        aria-labelledby="packages-title"
    >
        <h2 id="packages-title" class="text-2xl font-semibold">
            Value packages · প্যাকেজ
        </h2>
        <p class="mt-1 text-muted-foreground">
            Bundles of our products — choose one and check out in minutes.
        </p>
        <ul class="mt-6 grid grid-cols-2 gap-3 sm:gap-6 lg:grid-cols-4">
            <li
                v-for="(pkg, i) in packages"
                :key="pkg.id"
                data-test="package-card"
            >
                <article
                    class="group flex h-full flex-col overflow-hidden rounded-xl border bg-card transition-shadow hover:shadow-lg"
                >
                    <div class="aspect-4/3 overflow-hidden bg-muted">
                        <img
                            v-if="pkg.image"
                            :src="pkg.image"
                            :alt="pkg.name"
                            loading="lazy"
                            class="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                        />
                        <div
                            v-else
                            class="flex size-full items-center justify-center bg-linear-to-br"
                            :class="tints[i % tints.length]"
                        >
                            <AppLogoIcon class="size-10" />
                        </div>
                    </div>
                    <div class="flex flex-1 flex-col p-3 sm:p-4">
                        <h3 class="font-semibold">{{ pkg.name }}</h3>
                        <p
                            v-if="pkg.description"
                            class="mt-1 line-clamp-2 text-xs text-muted-foreground sm:text-sm"
                        >
                            {{ pkg.description }}
                        </p>
                        <p
                            class="mt-auto pt-3 text-lg font-bold tracking-tight tabular-nums sm:text-2xl"
                        >
                            {{ pkg.price }}
                        </p>
                        <Button
                            v-if="signedIn || canRegister"
                            class="mt-3 w-full"
                            as-child
                        >
                            <Link :href="buyPackageUrl(signedIn, pkg.id)">
                                <ShoppingCart
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                <span class="sm:hidden">Buy · কিনুন</span>
                                <span class="hidden sm:inline"
                                    >Buy now · এখনই কিনুন</span
                                >
                            </Link>
                        </Button>
                    </div>
                </article>
            </li>
        </ul>
    </section>

    <!-- How to order -->
    <section
        id="how"
        class="mt-4 scroll-mt-32 border-y bg-muted/40"
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

    <!-- Payments + call to action -->
    <section class="mx-auto max-w-7xl px-4 py-12">
        <div
            class="flex flex-col items-start justify-between gap-6 rounded-2xl border p-6 sm:p-8 md:flex-row md:items-center"
        >
            <div>
                <h2 class="text-xl font-semibold">
                    Pay your way · আপনার সুবিধামতো পেমেন্ট
                </h2>
                <p class="mt-1 max-w-xl text-sm text-muted-foreground">
                    Every payment is confirmed directly with the gateway before
                    your order completes.
                </p>
            </div>
            <ul
                class="flex flex-wrap gap-2"
                aria-label="Accepted payment methods"
            >
                <li
                    class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium"
                >
                    <span class="size-2.5 rounded-full bg-[#e2136e]" /> bKash ·
                    বিকাশ
                </li>
                <li
                    class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium"
                >
                    <span class="size-2.5 rounded-full bg-[#f6921e]" /> Nagad ·
                    নগদ
                </li>
                <li
                    class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium"
                >
                    <span class="size-2.5 rounded-full bg-[#2a78d6]" /> Visa ·
                    Mastercard · Amex
                </li>
            </ul>
        </div>

        <div
            v-if="!signedIn && canRegister"
            class="mt-8 flex flex-col items-start justify-between gap-4 rounded-2xl bg-slate-900 p-8 text-white sm:flex-row sm:items-center dark:bg-slate-800"
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
</template>
