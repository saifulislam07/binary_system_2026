<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    CreditCard,
    PackageCheck,
    ShoppingCart,
    Tag,
    UserPlus,
} from '@lucide/vue';
import { computed, onMounted } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ProductCard from '@/components/shop/ProductCard.vue';
import {
    buyPackageUrl,
    joinUrl,
    localName,
    rememberReferral,
    tints,
} from '@/lib/shop';
import type { ProductCardData } from '@/lib/shop';
import { index as shopIndex, show as productShow } from '@/routes/shop';

type CategoryTile = {
    slug: string;
    name: string;
    nameBn: string | null;
    count: number;
    image: string | null;
};

type BrandTile = {
    slug: string;
    name: string;
    count: number;
    logo: string | null;
};

type PackageCard = {
    id: number;
    name: string;
    description: string | null;
    price: string;
    image: string | null;
    items: { name: string; quantity: number }[];
};

const props = defineProps<{
    categories: CategoryTile[];
    featured: ProductCardData[];
    deals: ProductCardData[];
    brands: BrandTile[];
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

const hero = computed(() => props.featured[0] ?? null);
const heroSide = computed(() => props.featured.slice(1, 3));
const productCount = computed(() =>
    props.categories.reduce((sum, c) => sum + c.count, 0),
);

const steps = [
    {
        icon: PackageCheck,
        title: 'Choose a package',
        text: 'Each package is a bundle of our products.',
    },
    {
        icon: UserPlus,
        title: 'Create your account',
        text: 'Sign up in two minutes with a referral ID.',
    },
    {
        icon: CreditCard,
        title: 'Pay securely',
        text: 'bKash, Nagad or card — your order is confirmed at once.',
    },
];
</script>

<template>
    <Head :title="$t('Shop electronics')" />

    <!-- Hero -->
    <section class="mx-auto max-w-7xl px-4 pt-6">
        <div
            class="relative isolate overflow-hidden rounded-3xl bg-[#0b1a33] text-white"
        >
            <div
                class="absolute inset-0 -z-10 bg-[radial-gradient(900px_420px_at_85%_-10%,rgba(57,135,229,0.55),transparent_60%),radial-gradient(700px_380px_at_-10%_110%,rgba(42,120,214,0.45),transparent_60%)]"
                aria-hidden="true"
            />
            <div
                class="absolute inset-0 -z-10 bg-[linear-gradient(rgba(255,255,255,0.05)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.05)_1px,transparent_1px)] mask-[radial-gradient(ellipse_at_center,black_30%,transparent_75%)] bg-size-[44px_44px]"
                aria-hidden="true"
            />

            <div class="grid items-center gap-10 p-8 sm:p-12 lg:grid-cols-2">
                <div>
                    <p
                        v-if="sponsorCode"
                        class="mb-6 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-sm ring-1 ring-white/20"
                        data-test="sponsor-invite"
                    >
                        {{ $t('Referred by') }}
                        <strong>{{ sponsorCode }}</strong>
                    </p>
                    <p
                        v-else
                        class="mb-6 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-sm ring-1 ring-white/20"
                    >
                        <span
                            class="size-2 rounded-full bg-emerald-400"
                            aria-hidden="true"
                        />
                        {{ $t('Genuine electronics') }}
                    </p>
                    <h1
                        class="text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl"
                    >
                        {{ $t('Quality gadgets') }}{{ ' '
                        }}<br class="hidden sm:block" />
                        <span
                            class="bg-linear-to-r from-[#8cc0ff] to-white bg-clip-text text-transparent"
                            >{{ $t('at fair prices.') }}</span
                        >
                    </h1>
                    <p class="mt-4 text-lg text-white/80">
                        {{ $t('Genuine products, sold in clear bundles.') }}
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link
                            :href="shopIndex()"
                            class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-white px-6 font-semibold text-[#0b1a33] shadow-lg shadow-black/20 transition hover:bg-white/90 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#0b1a33] focus-visible:outline-none sm:w-auto"
                        >
                            {{ $t('Shop now') }}
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </Link>
                        <a
                            href="#packages"
                            class="inline-flex min-h-12 w-full items-center justify-center rounded-xl px-6 font-medium ring-1 ring-white/30 transition hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none sm:w-auto"
                        >
                            {{ $t('See packages') }}
                        </a>
                    </div>

                    <dl
                        class="mt-10 grid max-w-md grid-cols-3 gap-4 border-t border-white/15 pt-6"
                    >
                        <div v-if="startingPrice">
                            <dt class="text-xs text-white/60">
                                {{ $t('Packages from') }}
                            </dt>
                            <dd class="mt-1 text-lg font-semibold tabular-nums">
                                {{ startingPrice }}
                            </dd>
                        </div>
                        <div v-if="productCount">
                            <dt class="text-xs text-white/60">
                                {{ $t('Products') }}
                            </dt>
                            <dd class="mt-1 text-lg font-semibold tabular-nums">
                                {{ productCount }}
                            </dd>
                        </div>
                        <div v-if="brands.length">
                            <dt class="text-xs text-white/60">
                                {{ $t('Brands') }}
                            </dt>
                            <dd class="mt-1 text-lg font-semibold tabular-nums">
                                {{ brands.length }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Showcase -->
                <div
                    v-if="hero"
                    class="relative mx-auto hidden w-full max-w-md lg:block"
                >
                    <Link
                        :href="productShow(hero.slug)"
                        class="group block overflow-hidden rounded-2xl bg-white text-slate-900 shadow-2xl ring-1 shadow-black/40 ring-white/10"
                    >
                        <div class="relative aspect-4/3 overflow-hidden">
                            <img
                                v-if="hero.image"
                                :src="hero.image"
                                :alt="hero.name"
                                class="size-full object-cover transition duration-500 group-hover:scale-105"
                            />
                            <span
                                v-if="hero.discount"
                                class="absolute top-3 left-3 rounded-full bg-deal px-2.5 py-1 text-xs font-bold text-white"
                                >−{{ hero.discount }}%</span
                            >
                        </div>
                        <div class="flex items-end justify-between gap-4 p-5">
                            <div class="min-w-0">
                                <p
                                    class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                                >
                                    {{ hero.brand ?? hero.category }}
                                </p>
                                <p class="mt-1 truncate text-lg font-semibold">
                                    {{ hero.name }}
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p
                                    v-if="hero.compareAt"
                                    class="text-xs text-slate-400 tabular-nums line-through"
                                >
                                    {{ hero.compareAt }}
                                </p>
                                <p class="text-xl font-bold tabular-nums">
                                    {{ hero.price }}
                                </p>
                            </div>
                        </div>
                    </Link>

                    <Link
                        v-for="(item, i) in heroSide"
                        :key="item.id"
                        :href="productShow(item.slug)"
                        class="absolute flex w-60 items-center gap-3 rounded-xl bg-white/95 p-2.5 text-slate-900 shadow-xl ring-1 ring-black/5 backdrop-blur transition hover:-translate-y-0.5"
                        :class="
                            i === 0
                                ? '-top-4 -left-16 xl:-left-24'
                                : '-right-8 bottom-28 xl:-right-14'
                        "
                    >
                        <img
                            v-if="item.image"
                            :src="item.image"
                            alt=""
                            class="size-14 shrink-0 rounded-lg object-cover"
                        />
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium">{{
                                item.name
                            }}</span>
                            <span
                                class="block text-sm font-bold text-brand tabular-nums"
                                >{{ item.price }}</span
                            >
                        </span>
                    </Link>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories -->
    <section
        v-if="categories.length"
        class="mx-auto max-w-7xl px-4 pt-14"
        aria-labelledby="categories-title"
    >
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-brand">
                    {{ $t('Browse') }}
                </p>
                <h2
                    id="categories-title"
                    class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl"
                >
                    {{ $t('Shop by category') }}
                </h2>
            </div>
        </div>
        <ul
            class="mt-8 grid grid-cols-3 gap-x-3 gap-y-6 sm:grid-cols-4 lg:grid-cols-6"
        >
            <li v-for="(category, i) in categories" :key="category.slug">
                <Link
                    :href="shopIndex({ query: { category: category.slug } })"
                    class="group flex flex-col items-center text-center focus-visible:outline-none"
                    data-test="category-tile"
                >
                    <span
                        class="relative block aspect-square w-full max-w-36 overflow-hidden rounded-full bg-surface ring-1 ring-border transition group-hover:ring-4 group-hover:ring-brand/30 group-focus-visible:ring-4 group-focus-visible:ring-brand"
                    >
                        <img
                            v-if="category.image"
                            :src="category.image"
                            alt=""
                            loading="lazy"
                            class="size-full object-cover transition-transform duration-500 group-hover:scale-110"
                        />
                        <span
                            v-else
                            class="flex size-full items-center justify-center bg-linear-to-br"
                            :class="tints[i % tints.length]"
                        >
                            <AppLogoIcon class="size-8" />
                        </span>
                    </span>
                    <span
                        class="mt-3 text-sm font-semibold group-hover:text-brand"
                        >{{ localName(category) }}</span
                    >
                    <span class="text-xs text-muted-foreground">{{
                        $tc(':count item', ':count items', category.count)
                    }}</span>
                </Link>
            </li>
        </ul>
    </section>

    <!-- Deals -->
    <section
        v-if="deals.length"
        class="mx-auto max-w-7xl px-4 pt-16"
        aria-labelledby="deals-title"
    >
        <div
            class="rounded-3xl bg-linear-to-br from-rose-50 to-orange-50 p-5 ring-1 ring-rose-100 sm:p-8 dark:from-rose-950/40 dark:to-orange-950/30 dark:ring-rose-900/40"
        >
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p
                        class="inline-flex items-center gap-1.5 text-sm font-semibold text-deal"
                    >
                        <Tag class="size-4" aria-hidden="true" />
                        {{ $t('Price drops') }}
                    </p>
                    <h2
                        id="deals-title"
                        class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl"
                    >
                        {{ $t('Deals') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ $t('Real savings against the previous price.') }}
                    </p>
                </div>
                <Link
                    :href="shopIndex({ query: { deals: 1 } })"
                    class="inline-flex items-center gap-1 text-sm font-semibold text-deal hover:underline"
                    >{{ $t('All deals') }}
                    <ArrowRight class="size-4" aria-hidden="true"
                /></Link>
            </div>
            <ul class="mt-6 grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
                <li v-for="(product, i) in deals" :key="product.id">
                    <ProductCard :product="product" :index="i" />
                </li>
            </ul>
        </div>
    </section>

    <!-- Featured products -->
    <section
        v-if="featured.length"
        class="mx-auto max-w-7xl px-4 pt-16"
        aria-labelledby="featured-title"
    >
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-sm font-semibold text-brand">
                    {{ $t('Hand-picked') }}
                </p>
                <h2
                    id="featured-title"
                    class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl"
                >
                    {{ $t('Featured products') }}
                </h2>
            </div>
            <Link
                :href="shopIndex()"
                class="inline-flex items-center gap-1 text-sm font-semibold text-brand hover:underline"
                >{{ $t('View all') }}
                <ArrowRight class="size-4" aria-hidden="true"
            /></Link>
        </div>
        <ul class="mt-8 grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
            <li v-for="(product, i) in featured" :key="product.id">
                <ProductCard :product="product" :index="i" />
            </li>
        </ul>
    </section>

    <!-- Brands -->
    <section
        v-if="brands.length"
        class="mx-auto max-w-7xl px-4 pt-16"
        aria-labelledby="brands-title"
    >
        <h2
            id="brands-title"
            class="text-center text-sm font-semibold tracking-wider text-muted-foreground uppercase"
        >
            {{ $t('Shop by brand') }}
        </h2>
        <ul class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <li v-for="brand in brands" :key="brand.slug">
                <Link
                    :href="shopIndex({ query: { brand: brand.slug } })"
                    class="group flex h-20 flex-col items-center justify-center rounded-2xl border bg-card px-4 transition hover:border-brand/40 hover:shadow-md focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none"
                    data-test="brand-tile"
                >
                    <img
                        v-if="brand.logo"
                        :src="brand.logo"
                        :alt="brand.name"
                        loading="lazy"
                        class="max-h-9 max-w-full object-contain opacity-80 grayscale transition group-hover:opacity-100 group-hover:grayscale-0"
                    />
                    <span
                        v-else
                        class="text-lg font-bold tracking-tight text-foreground/70 transition group-hover:text-brand"
                        >{{ brand.name }}</span
                    >
                    <span class="mt-0.5 text-[11px] text-muted-foreground">{{
                        $tc(':count item', ':count items', brand.count)
                    }}</span>
                </Link>
            </li>
        </ul>
    </section>

    <!-- Packages -->
    <section
        v-if="packages.length"
        id="packages"
        class="mx-auto max-w-7xl scroll-mt-32 px-4 pt-16"
        aria-labelledby="packages-title"
    >
        <div class="max-w-2xl">
            <p class="text-sm font-semibold text-brand">
                {{ $t('Bundles') }}
            </p>
            <h2
                id="packages-title"
                class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl"
            >
                {{ $t('Value packages') }}
            </h2>
            <p class="mt-2 text-muted-foreground">
                {{
                    $t(
                        'Bundles of our products — choose one and check out in minutes.',
                    )
                }}
            </p>
        </div>
        <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <li
                v-for="(pkg, i) in packages"
                :key="pkg.id"
                data-test="package-card"
            >
                <article
                    class="group flex h-full flex-col overflow-hidden rounded-2xl border bg-card transition duration-300 hover:border-brand/40 hover:shadow-xl hover:shadow-slate-900/8"
                >
                    <div class="relative aspect-video overflow-hidden bg-muted">
                        <img
                            v-if="pkg.image"
                            :src="pkg.image"
                            :alt="pkg.name"
                            loading="lazy"
                            class="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                        />
                        <div
                            v-else
                            class="flex size-full items-center justify-center bg-linear-to-br"
                            :class="tints[i % tints.length]"
                        >
                            <AppLogoIcon class="size-10" />
                        </div>
                        <span
                            class="absolute top-3 left-3 rounded-full bg-white/90 px-2.5 py-1 text-xs font-semibold text-slate-900 shadow-sm backdrop-blur"
                            >{{ pkg.name }}</span
                        >
                    </div>
                    <div class="flex flex-1 flex-col p-5">
                        <p
                            class="text-3xl font-bold tracking-tight tabular-nums"
                        >
                            {{ pkg.price }}
                        </p>
                        <p
                            v-if="pkg.description"
                            class="mt-1 line-clamp-2 text-sm text-muted-foreground"
                        >
                            {{ pkg.description }}
                        </p>
                        <div v-if="pkg.items.length" class="mt-4 border-t pt-4">
                            <p
                                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                {{ $t("What's inside") }}
                            </p>
                            <ul class="mt-2.5 space-y-2 text-sm">
                                <li
                                    v-for="item in pkg.items"
                                    :key="item.name"
                                    class="flex gap-2"
                                >
                                    <Check
                                        class="mt-0.5 size-4 shrink-0 text-brand"
                                        aria-hidden="true"
                                    />
                                    <span
                                        >{{ item.name
                                        }}<span
                                            v-if="item.quantity > 1"
                                            class="text-muted-foreground"
                                        >
                                            × {{ item.quantity }}</span
                                        ></span
                                    >
                                </li>
                            </ul>
                        </div>
                        <div class="mt-auto pt-5">
                            <Link
                                v-if="signedIn || canRegister"
                                :href="buyPackageUrl(signedIn, pkg.id)"
                                class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-foreground text-sm font-semibold text-background transition group-hover:bg-brand group-hover:text-white hover:opacity-95"
                            >
                                <ShoppingCart
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                {{ $t('Buy now') }}
                            </Link>
                        </div>
                    </div>
                </article>
            </li>
        </ul>
    </section>

    <!-- How to order -->
    <section
        id="how"
        class="mx-auto max-w-7xl scroll-mt-32 px-4 pt-16"
        aria-labelledby="how-title"
    >
        <div class="rounded-3xl border bg-surface p-6 sm:p-10">
            <h2
                id="how-title"
                class="text-2xl font-semibold tracking-tight sm:text-3xl"
            >
                {{ $t('How to order') }}
            </h2>
            <ol class="mt-8 grid gap-8 md:grid-cols-3">
                <li
                    v-for="(step, i) in steps"
                    :key="step.title"
                    class="relative"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-12 items-center justify-center rounded-2xl bg-brand text-white shadow-lg shadow-brand/25"
                        >
                            <component
                                :is="step.icon"
                                class="size-5"
                                aria-hidden="true"
                            />
                        </span>
                        <span
                            class="text-sm font-semibold text-muted-foreground"
                            >{{ $t('Step :number', { number: i + 1 }) }}</span
                        >
                    </div>
                    <h3 class="mt-4 font-semibold">{{ $t(step.title) }}</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ $t(step.text) }}
                    </p>
                </li>
            </ol>
        </div>
    </section>

    <!-- Call to action -->
    <section class="mx-auto max-w-7xl px-4 py-16">
        <div
            v-if="!signedIn && canRegister"
            class="relative isolate flex flex-col items-start justify-between gap-6 overflow-hidden rounded-3xl bg-brand p-8 text-white sm:flex-row sm:items-center sm:p-10"
        >
            <div
                class="absolute -top-24 -right-24 -z-10 size-72 rounded-full bg-white/10"
                aria-hidden="true"
            />
            <div
                class="absolute -bottom-32 left-1/3 -z-10 size-72 rounded-full bg-white/5"
                aria-hidden="true"
            />
            <div>
                <p class="text-2xl font-semibold tracking-tight">
                    {{ $t('Ready to start?') }}
                </p>
                <p class="mt-1 text-white/80">
                    {{ $t('Create your account in two minutes.') }}
                </p>
            </div>
            <Link
                :href="joinUrl()"
                class="inline-flex h-12 items-center gap-2 rounded-xl bg-white px-6 font-semibold text-brand shadow-lg shadow-black/10 transition hover:bg-white/90 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand focus-visible:outline-none"
            >
                {{ $t('Create account') }}
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
        </div>
    </section>
</template>
