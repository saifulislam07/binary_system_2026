<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Check,
    ChevronLeft,
    ChevronRight,
    Languages,
    Package,
    ShieldCheck,
    ShoppingCart,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ProductCard from '@/components/shop/ProductCard.vue';
import { buyPackageUrl } from '@/lib/shop';
import type { ProductCardData } from '@/lib/shop';
import { home } from '@/routes';
import { index as shopIndex } from '@/routes/shop';

type Product = ProductCardData & {
    sku: string;
    description: string | null;
    summary: string;
    highlights: string[];
    categorySlug: string | null;
    brandLogo: string | null;
    gallery: { id: number; large: string; thumb: string }[];
};

type PackageOffer = {
    id: number;
    name: string;
    price: string;
    quantity: number;
};

const props = defineProps<{
    product: Product;
    packages: PackageOffer[];
    related: ProductCardData[];
}>();

const page = usePage();
const signedIn = computed(() => Boolean(page.props.auth.user));
const canRegister = computed(
    () =>
        (page.props.shop as { canRegister?: boolean } | null)?.canRegister ??
        false,
);

const active = ref(0);
const count = computed(() => props.product.gallery.length);
const shown = computed(() => props.product.gallery[active.value] ?? null);

function go(step: number) {
    if (count.value > 1) {
        active.value = (active.value + step + count.value) % count.value;
    }
}

// Swipe between photos on touch screens.
let touchX: number | null = null;

function onTouchEnd(event: TouchEvent) {
    if (touchX === null) {
        return;
    }

    const dx = event.changedTouches[0].clientX - touchX;
    touchX = null;

    if (Math.abs(dx) > 40) {
        go(dx < 0 ? 1 : -1);
    }
}

const assurances = [
    { icon: ShieldCheck, text: 'Payment confirmed with the gateway' },
    { icon: BadgeCheck, text: 'Genuine product' },
    { icon: Languages, text: 'Shop in Bangla & English' },
];
</script>

<template>
    <Head :title="product.name">
        <meta
            v-if="product.summary"
            head-key="description"
            name="description"
            :content="product.summary"
        />
    </Head>

    <div class="mx-auto max-w-7xl px-4 py-6">
        <nav
            :aria-label="$t('Breadcrumb')"
            class="text-sm text-muted-foreground"
        >
            <ol class="flex flex-wrap items-center gap-1.5">
                <li>
                    <Link :href="home()" class="hover:text-foreground">{{
                        $t('Home')
                    }}</Link>
                </li>
                <li aria-hidden="true"><ChevronRight class="size-3.5" /></li>
                <li>
                    <Link :href="shopIndex()" class="hover:text-foreground">{{
                        $t('Shop')
                    }}</Link>
                </li>
                <template v-if="product.categorySlug && product.category">
                    <li aria-hidden="true">
                        <ChevronRight class="size-3.5" />
                    </li>
                    <li>
                        <Link
                            :href="
                                shopIndex({
                                    query: { category: product.categorySlug },
                                })
                            "
                            class="hover:text-foreground"
                            >{{ product.category }}</Link
                        >
                    </li>
                </template>
                <li aria-hidden="true"><ChevronRight class="size-3.5" /></li>
                <li
                    class="max-w-[16rem] truncate text-foreground"
                    aria-current="page"
                >
                    {{ product.name }}
                </li>
            </ol>
        </nav>

        <div class="mt-6 grid gap-8 lg:grid-cols-[1.1fr_1fr] lg:gap-14">
            <!-- Gallery -->
            <section
                :aria-label="$t('Photos')"
                class="lg:sticky lg:top-36 lg:self-start"
            >
                <div
                    class="group relative overflow-hidden rounded-3xl border bg-white dark:bg-surface"
                    @touchstart.passive="touchX = $event.touches[0].clientX"
                    @touchend="onTouchEnd"
                >
                    <img
                        v-if="shown"
                        :key="shown.id"
                        :src="shown.large"
                        :alt="
                            $t(':name — photo :number of :count', {
                                name: product.name,
                                number: active + 1,
                                count,
                            })
                        "
                        class="aspect-square w-full animate-in object-contain duration-300 fade-in sm:aspect-4/3"
                    />
                    <div
                        v-else
                        class="flex aspect-4/3 items-center justify-center bg-linear-to-br from-sky-100 to-blue-200 text-blue-700 dark:from-sky-950 dark:to-blue-900 dark:text-blue-200"
                    >
                        <AppLogoIcon class="size-16" />
                    </div>
                    <span
                        v-if="product.discount"
                        class="absolute top-4 left-4 rounded-full bg-deal px-3 py-1 text-sm font-bold text-white shadow"
                        >−{{ product.discount }}%</span
                    >
                    <template v-if="count > 1">
                        <button
                            type="button"
                            class="absolute top-1/2 left-3 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-900 shadow-md transition hover:bg-white focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100"
                            :aria-label="$t('Previous photo')"
                            @click="go(-1)"
                        >
                            <ChevronLeft class="size-5" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            class="absolute top-1/2 right-3 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-900 shadow-md transition hover:bg-white focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100"
                            :aria-label="$t('Next photo')"
                            @click="go(1)"
                        >
                            <ChevronRight class="size-5" aria-hidden="true" />
                        </button>
                        <span
                            class="absolute right-4 bottom-4 rounded-full bg-slate-900/70 px-2.5 py-1 text-xs font-medium text-white tabular-nums"
                            >{{ active + 1 }} / {{ count }}</span
                        >
                    </template>
                </div>
                <ul
                    v-if="count > 1"
                    class="mt-3 flex gap-3 overflow-x-auto pb-1"
                    :aria-label="$t('Choose a photo')"
                >
                    <li
                        v-for="(image, i) in product.gallery"
                        :key="image.id"
                        class="shrink-0"
                    >
                        <button
                            type="button"
                            class="block overflow-hidden rounded-xl ring-2 ring-offset-2 ring-offset-background transition focus-visible:outline-none"
                            :class="
                                i === active
                                    ? 'ring-brand'
                                    : 'opacity-70 ring-transparent hover:opacity-100'
                            "
                            :aria-label="$t('Photo :number', { number: i + 1 })"
                            :aria-pressed="i === active"
                            @click="active = i"
                        >
                            <img
                                :src="image.thumb"
                                alt=""
                                class="h-16 w-20 object-cover sm:h-20 sm:w-24"
                            />
                        </button>
                    </li>
                </ul>
            </section>

            <!-- Details -->
            <div>
                <Link
                    v-if="product.brand && product.brandSlug"
                    :href="shopIndex({ query: { brand: product.brandSlug } })"
                    class="inline-flex items-center gap-2 rounded-full border bg-card py-1 pr-3 pl-1 text-sm font-semibold transition hover:border-brand/40 hover:text-brand"
                >
                    <img
                        v-if="product.brandLogo"
                        :src="product.brandLogo"
                        alt=""
                        class="h-6 w-auto rounded-full bg-white object-contain px-1"
                    />
                    <span
                        v-else
                        class="flex size-6 items-center justify-center rounded-full bg-brand-soft text-[10px] font-bold text-brand"
                        aria-hidden="true"
                        >{{ product.brand.slice(0, 2).toUpperCase() }}</span
                    >
                    {{ product.brand }}
                </Link>
                <h1
                    class="mt-3 text-3xl font-semibold tracking-tight text-balance sm:text-4xl"
                >
                    {{ product.name }}
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ $t('SKU :sku', { sku: product.sku }) }}
                    <template v-if="product.category">
                        · {{ product.category }}</template
                    >
                </p>

                <div
                    class="mt-6 flex flex-wrap items-center gap-x-3 gap-y-2 border-y py-5"
                >
                    <span
                        class="text-4xl font-bold tracking-tight tabular-nums"
                        :class="product.discount ? 'text-deal' : ''"
                        >{{ product.price }}</span
                    >
                    <template v-if="product.compareAt">
                        <span
                            class="text-lg text-muted-foreground tabular-nums line-through"
                            >{{ product.compareAt }}</span
                        >
                        <span
                            class="rounded-full bg-deal/10 px-2.5 py-1 text-sm font-semibold text-deal"
                            >{{
                                $t('Save :percent%', {
                                    percent: product.discount ?? 0,
                                })
                            }}</span
                        >
                    </template>
                </div>

                <ul v-if="product.highlights.length" class="mt-6 grid gap-2.5">
                    <li
                        v-for="highlight in product.highlights"
                        :key="highlight"
                        class="flex gap-3"
                    >
                        <span
                            class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand"
                        >
                            <Check class="size-3.5" aria-hidden="true" />
                        </span>
                        <span>{{ highlight }}</span>
                    </li>
                </ul>

                <!-- Where to buy it -->
                <section
                    class="mt-8 overflow-hidden rounded-2xl border bg-card shadow-sm"
                    aria-labelledby="buy-title"
                >
                    <div
                        class="flex items-center gap-3 border-b bg-surface px-5 py-4"
                    >
                        <span
                            class="flex size-9 items-center justify-center rounded-xl bg-brand text-white"
                        >
                            <Package class="size-4" aria-hidden="true" />
                        </span>
                        <div>
                            <h2 id="buy-title" class="font-semibold">
                                {{ $t('Get it in a package') }}
                            </h2>
                            <p
                                v-if="packages.length"
                                class="text-sm text-muted-foreground"
                            >
                                {{
                                    packages.length > 1
                                        ? $t(
                                              'This product comes in the packages below.',
                                          )
                                        : $t(
                                              'This product comes in the package below.',
                                          )
                                }}
                            </p>
                        </div>
                    </div>
                    <ul v-if="packages.length" class="divide-y">
                        <li
                            v-for="pkg in packages"
                            :key="pkg.id"
                            class="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
                            data-test="package-offer"
                        >
                            <div>
                                <p class="font-semibold">
                                    {{
                                        $t(':name package', { name: pkg.name })
                                    }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    <span
                                        class="font-semibold text-foreground tabular-nums"
                                        >{{ pkg.price }}</span
                                    ><template v-if="pkg.quantity > 1">
                                        ·
                                        {{
                                            $t('includes :count', {
                                                count: pkg.quantity,
                                            })
                                        }}</template
                                    >
                                </p>
                            </div>
                            <Link
                                v-if="signedIn || canRegister"
                                :href="buyPackageUrl(signedIn, pkg.id)"
                                class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-5 text-sm font-semibold text-white shadow-md shadow-brand/25 transition hover:bg-brand-strong"
                            >
                                <ShoppingCart
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                {{ $t('Buy') }}
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="px-5 py-4 text-sm text-muted-foreground">
                        {{ $t('Coming soon in a package') }}
                    </p>
                </section>

                <ul class="mt-6 grid gap-3 text-sm sm:grid-cols-3">
                    <li
                        v-for="item in assurances"
                        :key="item.text"
                        class="flex items-center gap-2.5 rounded-xl bg-surface px-3 py-2.5"
                    >
                        <component
                            :is="item.icon"
                            class="size-4 shrink-0 text-brand"
                            aria-hidden="true"
                        />
                        <span class="text-muted-foreground">{{
                            $t(item.text)
                        }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Full description -->
        <section
            v-if="product.description"
            class="mt-14 border-t pt-10"
            aria-labelledby="about-title"
        >
            <h2 id="about-title" class="text-xl font-semibold tracking-tight">
                {{ $t('About this product') }}
            </h2>
            <!-- Sanitized on the server (App\Support\RichText). -->
            <div
                class="rich-text mt-4 max-w-prose"
                v-html="product.description"
            />
        </section>

        <section
            v-if="related.length"
            class="mt-16"
            aria-labelledby="related-title"
        >
            <div
                class="flex flex-wrap items-end justify-between gap-x-4 gap-y-2"
            >
                <h2
                    id="related-title"
                    class="text-xl font-semibold tracking-tight sm:text-2xl"
                >
                    {{ $t('You may also like') }}
                </h2>
                <Link
                    v-if="product.categorySlug"
                    :href="
                        shopIndex({ query: { category: product.categorySlug } })
                    "
                    class="inline-flex items-center gap-1 text-sm font-semibold text-brand hover:underline"
                    >{{
                        $t('More in :category', {
                            category: product.category ?? '',
                        })
                    }}
                    <ChevronRight class="size-4" aria-hidden="true"
                /></Link>
            </div>
            <ul class="mt-6 grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
                <li v-for="(item, i) in related" :key="item.id">
                    <ProductCard :product="item" :index="i" />
                </li>
            </ul>
        </section>
    </div>
</template>
