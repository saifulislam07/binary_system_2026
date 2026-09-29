<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    PackageSearch,
    SlidersHorizontal,
    Tag,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ProductCard from '@/components/shop/ProductCard.vue';
import type { ProductCardData } from '@/lib/shop';
import { home } from '@/routes';
import { index as shopIndex } from '@/routes/shop';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: { url: string | null; label: string; active: boolean }[];
};

type Category = {
    slug: string;
    name: string;
    nameBn: string | null;
    count: number;
};

type Brand = {
    slug: string;
    name: string;
    count: number;
    logo: string | null;
};

const props = defineProps<{
    products: Paginated<ProductCardData>;
    categories: Category[];
    brands: Brand[];
    current: {
        slug: string;
        name: string;
        nameBn: string | null;
        description: string | null;
    } | null;
    currentBrand: {
        slug: string;
        name: string;
        description: string | null;
        logo: string | null;
    } | null;
    filters: { q: string; sort: string; deals: boolean };
    unknownCategory: boolean;
}>();

const sorts = [
    { value: 'featured', label: 'Featured · জনপ্রিয়' },
    { value: 'price_asc', label: 'Price: low to high · কম দাম আগে' },
    { value: 'price_desc', label: 'Price: high to low · বেশি দাম আগে' },
    { value: 'newest', label: 'Newest · নতুন' },
];

const filtersOpen = ref(false);

const heading = computed(() => {
    if (props.current) {
        return (
            props.current.name +
            (props.current.nameBn ? ` · ${props.current.nameBn}` : '')
        );
    }

    if (props.currentBrand) {
        return props.currentBrand.name;
    }

    if (props.filters.q) {
        return `Results for “${props.filters.q}”`;
    }

    return props.filters.deals ? 'Deals · অফার' : 'All products · সব পণ্য';
});

type Query = Record<string, string | null>;

function state(): Query {
    return {
        category: props.current?.slug ?? null,
        brand: props.currentBrand?.slug ?? null,
        q: props.filters.q || null,
        sort: props.filters.sort === 'featured' ? null : props.filters.sort,
        deals: props.filters.deals ? '1' : null,
    };
}

/** URL for the current listing with some filters changed. */
function urlWith(changes: Query): string {
    const query: Record<string, string> = {};

    for (const [key, value] of Object.entries({ ...state(), ...changes })) {
        if (value) {
            query[key] = value;
        }
    }

    return shopIndex.url(Object.keys(query).length ? { query } : undefined);
}

function visit(changes: Query) {
    router.get(urlWith(changes), {}, { preserveScroll: true });
}

const chips = computed(() => {
    const list: { label: string; href: string }[] = [];

    if (props.current) {
        list.push({
            label: props.current.name,
            href: urlWith({ category: null }),
        });
    }

    if (props.currentBrand) {
        list.push({
            label: props.currentBrand.name,
            href: urlWith({ brand: null }),
        });
    }

    if (props.filters.q) {
        list.push({
            label: `“${props.filters.q}”`,
            href: urlWith({ q: null }),
        });
    }

    if (props.filters.deals) {
        list.push({ label: 'Deals', href: urlWith({ deals: null }) });
    }

    return list;
});

// Page links without Laravel's « Previous / Next » entries.
const pageLinks = computed(() => props.products.links.slice(1, -1));
</script>

<template>
    <Head :title="heading" />

    <div class="mx-auto max-w-7xl px-4 py-8">
        <nav aria-label="Breadcrumb" class="text-sm text-muted-foreground">
            <ol class="flex flex-wrap items-center gap-1.5">
                <li>
                    <Link :href="home()" class="hover:text-foreground"
                        >Home</Link
                    >
                </li>
                <li aria-hidden="true"><ChevronRight class="size-3.5" /></li>
                <li>
                    <Link :href="shopIndex()" class="hover:text-foreground"
                        >Shop</Link
                    >
                </li>
                <template v-if="current || currentBrand">
                    <li aria-hidden="true">
                        <ChevronRight class="size-3.5" />
                    </li>
                    <li class="font-medium text-foreground" aria-current="page">
                        {{ current?.name ?? currentBrand?.name }}
                    </li>
                </template>
            </ol>
        </nav>

        <!-- Heading -->
        <div
            class="mt-5 flex flex-wrap items-end justify-between gap-4 border-b pb-6"
        >
            <div class="flex items-center gap-4">
                <img
                    v-if="currentBrand?.logo && !current"
                    :src="currentBrand.logo"
                    alt=""
                    class="h-12 w-auto rounded-lg border bg-white object-contain p-1.5"
                />
                <div>
                    <h1
                        class="text-2xl font-semibold tracking-tight sm:text-3xl"
                    >
                        {{ heading }}
                    </h1>
                    <p
                        v-if="current?.description ?? currentBrand?.description"
                        class="mt-1 text-muted-foreground"
                    >
                        {{ current?.description ?? currentBrand?.description }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <template v-if="products.total && products.from"
                            >Showing {{ products.from }}–{{ products.to }} of
                        </template>
                        {{ products.total }} products · টি পণ্য
                    </p>
                </div>
            </div>
            <div class="flex w-full items-center gap-2 sm:w-auto">
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-xl border px-4 text-sm font-medium lg:hidden"
                    :aria-expanded="filtersOpen"
                    aria-controls="shop-filters"
                    @click="filtersOpen = !filtersOpen"
                >
                    <SlidersHorizontal class="size-4" aria-hidden="true" />
                    Filters
                </button>
                <label for="sort" class="sr-only">Sort · সাজান</label>
                <select
                    id="sort"
                    :value="filters.sort"
                    class="h-10 min-w-0 flex-1 rounded-xl border border-input bg-background px-3 text-sm outline-none focus-visible:border-brand focus-visible:ring-4 focus-visible:ring-brand/15 sm:flex-none"
                    @change="
                        visit({
                            sort: ($event.target as HTMLSelectElement).value,
                        })
                    "
                >
                    <option
                        v-for="sort in sorts"
                        :key="sort.value"
                        :value="sort.value"
                    >
                        {{ sort.label }}
                    </option>
                </select>
            </div>
        </div>

        <p
            v-if="unknownCategory"
            class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
        >
            That category isn't available — showing all products.
        </p>

        <div class="mt-6 grid gap-8 lg:grid-cols-[240px_1fr]">
            <!-- Filters -->
            <aside
                id="shop-filters"
                class="space-y-8 lg:block"
                :class="filtersOpen ? 'block' : 'hidden'"
                aria-label="Filters"
            >
                <nav aria-label="Filter by category">
                    <h2
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Category · ক্যাটাগরি
                    </h2>
                    <ul class="mt-3 space-y-0.5 text-sm">
                        <li>
                            <Link
                                :href="urlWith({ category: null, brand: null })"
                                class="flex items-center justify-between rounded-lg px-3 py-2 transition hover:bg-muted"
                                :class="
                                    !current
                                        ? 'bg-brand-soft font-semibold text-brand'
                                        : ''
                                "
                                :aria-current="!current ? 'page' : undefined"
                                >All · সব</Link
                            >
                        </li>
                        <li v-for="category in categories" :key="category.slug">
                            <Link
                                :href="
                                    urlWith({
                                        category: category.slug,
                                        brand: null,
                                    })
                                "
                                class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 transition hover:bg-muted"
                                :class="
                                    current?.slug === category.slug
                                        ? 'bg-brand-soft font-semibold text-brand'
                                        : ''
                                "
                                :aria-current="
                                    current?.slug === category.slug
                                        ? 'page'
                                        : undefined
                                "
                            >
                                <span class="truncate">{{
                                    category.name
                                }}</span>
                                <span
                                    class="rounded-full bg-muted px-2 text-xs text-muted-foreground tabular-nums"
                                    >{{ category.count }}</span
                                >
                            </Link>
                        </li>
                    </ul>
                </nav>

                <nav v-if="brands.length" aria-label="Filter by brand">
                    <h2
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Brand · ব্র্যান্ড
                    </h2>
                    <ul class="mt-3 space-y-0.5 text-sm">
                        <li v-for="brand in brands" :key="brand.slug">
                            <Link
                                :href="
                                    urlWith({
                                        brand:
                                            currentBrand?.slug === brand.slug
                                                ? null
                                                : brand.slug,
                                    })
                                "
                                class="flex items-center gap-3 rounded-lg px-3 py-2 transition hover:bg-muted"
                                :class="
                                    currentBrand?.slug === brand.slug
                                        ? 'bg-brand-soft font-semibold text-brand'
                                        : ''
                                "
                                :aria-current="
                                    currentBrand?.slug === brand.slug
                                        ? 'true'
                                        : undefined
                                "
                                data-test="brand-filter"
                            >
                                <span
                                    class="flex size-4 shrink-0 items-center justify-center rounded border"
                                    :class="
                                        currentBrand?.slug === brand.slug
                                            ? 'border-brand bg-brand text-white'
                                            : 'border-input'
                                    "
                                    aria-hidden="true"
                                >
                                    <svg
                                        v-if="currentBrand?.slug === brand.slug"
                                        viewBox="0 0 12 12"
                                        class="size-2.5"
                                    >
                                        <path
                                            d="M2.5 6.5l2.2 2L9.5 3.5"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                        />
                                    </svg>
                                </span>
                                <span class="flex-1 truncate">{{
                                    brand.name
                                }}</span>
                                <span
                                    class="text-xs text-muted-foreground tabular-nums"
                                    >{{ brand.count }}</span
                                >
                            </Link>
                        </li>
                    </ul>
                </nav>

                <div>
                    <h2
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Offers · অফার
                    </h2>
                    <Link
                        :href="urlWith({ deals: filters.deals ? null : '1' })"
                        class="mt-3 flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition hover:bg-muted"
                        :class="
                            filters.deals
                                ? 'bg-deal/10 font-semibold text-deal'
                                : ''
                        "
                        :aria-current="filters.deals ? 'true' : undefined"
                    >
                        <Tag class="size-4" aria-hidden="true" />
                        On sale only
                    </Link>
                </div>
            </aside>

            <div class="min-w-0">
                <!-- Active filters -->
                <ul v-if="chips.length" class="mb-5 flex flex-wrap gap-2">
                    <li v-for="chip in chips" :key="chip.label">
                        <Link
                            :href="chip.href"
                            class="inline-flex items-center gap-1.5 rounded-full border bg-card py-1 pr-2 pl-3 text-sm transition hover:border-foreground/30"
                            :aria-label="`Remove filter ${chip.label}`"
                        >
                            {{ chip.label }}
                            <X class="size-3.5" aria-hidden="true" />
                        </Link>
                    </li>
                    <li>
                        <Link
                            :href="shopIndex()"
                            class="inline-flex items-center px-2 py-1 text-sm font-medium text-brand hover:underline"
                            >Clear all</Link
                        >
                    </li>
                </ul>

                <ul
                    v-if="products.data.length"
                    class="grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3"
                >
                    <li v-for="(product, i) in products.data" :key="product.id">
                        <ProductCard :product="product" :index="i" />
                    </li>
                </ul>
                <div
                    v-else
                    class="flex flex-col items-center rounded-3xl border border-dashed p-12 text-center"
                >
                    <span
                        class="flex size-14 items-center justify-center rounded-full bg-muted text-muted-foreground"
                    >
                        <PackageSearch class="size-7" aria-hidden="true" />
                    </span>
                    <p class="mt-4 font-semibold">
                        No products found · কোনো পণ্য পাওয়া যায়নি
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Try another category, brand or search word.
                    </p>
                    <Link
                        :href="shopIndex()"
                        class="mt-5 inline-flex h-10 items-center rounded-xl border px-4 text-sm font-semibold hover:bg-muted"
                        >See all products · সব পণ্য</Link
                    >
                </div>

                <!-- Pagination -->
                <nav
                    v-if="products.last_page > 1"
                    class="mt-10 flex items-center justify-center gap-1.5"
                    aria-label="Pagination"
                >
                    <Link
                        v-if="products.prev_page_url"
                        :href="products.prev_page_url"
                        class="inline-flex size-10 items-center justify-center rounded-xl border hover:bg-muted"
                        aria-label="Previous page"
                    >
                        <ChevronLeft class="size-4" aria-hidden="true" />
                    </Link>
                    <template v-for="link in pageLinks" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="inline-flex size-10 items-center justify-center rounded-xl text-sm font-medium tabular-nums transition"
                            :class="
                                link.active
                                    ? 'bg-foreground text-background'
                                    : 'border hover:bg-muted'
                            "
                            :aria-current="link.active ? 'page' : undefined"
                            >{{ link.label }}</Link
                        >
                        <span
                            v-else
                            class="inline-flex size-10 items-center justify-center text-sm text-muted-foreground"
                            >…</span
                        >
                    </template>
                    <Link
                        v-if="products.next_page_url"
                        :href="products.next_page_url"
                        class="inline-flex size-10 items-center justify-center rounded-xl border hover:bg-muted"
                        aria-label="Next page"
                    >
                        <ChevronRight class="size-4" aria-hidden="true" />
                    </Link>
                </nav>
            </div>
        </div>
    </div>
</template>
