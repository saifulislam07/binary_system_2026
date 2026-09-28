<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProductCard from '@/components/shop/ProductCard.vue';
import { Button } from '@/components/ui/button';
import type { ProductCardData } from '@/lib/shop';
import { index as shopIndex } from '@/routes/shop';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Category = {
    slug: string;
    name: string;
    nameBn: string | null;
    count: number;
};

const props = defineProps<{
    products: Paginated<ProductCardData>;
    categories: Category[];
    current: {
        slug: string;
        name: string;
        nameBn: string | null;
        description: string | null;
    } | null;
    filters: { q: string; sort: string };
    unknownCategory: boolean;
}>();

const sorts = [
    { value: 'featured', label: 'Featured · জনপ্রিয়' },
    { value: 'price_asc', label: 'Price: low to high · কম দাম আগে' },
    { value: 'price_desc', label: 'Price: high to low · বেশি দাম আগে' },
    { value: 'newest', label: 'Newest · নতুন' },
];

const heading = computed(() =>
    props.current
        ? props.current.name +
          (props.current.nameBn ? ` · ${props.current.nameBn}` : '')
        : props.filters.q
          ? `Results for “${props.filters.q}”`
          : 'All products · সব পণ্য',
);

function visit(changes: Record<string, string | null>) {
    const query: Record<string, string> = {};
    const next = {
        category: props.current?.slug ?? null,
        q: props.filters.q || null,
        sort: props.filters.sort === 'featured' ? null : props.filters.sort,
        ...changes,
    };

    for (const [key, value] of Object.entries(next)) {
        if (value) {
            query[key] = value;
        }
    }

    router.get(shopIndex.url({ query }), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="heading" />

    <div class="mx-auto max-w-7xl px-4 py-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold sm:text-3xl">
                    {{ heading }}
                </h1>
                <p
                    v-if="current?.description"
                    class="mt-1 text-muted-foreground"
                >
                    {{ current.description }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ products.total }} products · টি পণ্য
                </p>
            </div>
            <div class="flex items-center gap-2">
                <label for="sort" class="text-sm text-muted-foreground"
                    >Sort · সাজান</label
                >
                <select
                    id="sort"
                    :value="filters.sort"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
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
            class="mt-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
        >
            That category isn't available — showing all products.
        </p>

        <!-- Category chips -->
        <nav class="mt-6" aria-label="Filter by category">
            <ul class="flex flex-wrap gap-2">
                <li>
                    <Link
                        :href="
                            shopIndex(
                                filters.q
                                    ? { query: { q: filters.q } }
                                    : undefined,
                            )
                        "
                        class="inline-block rounded-full border px-3 py-1 text-sm hover:bg-muted"
                        :class="!current ? 'border-foreground font-medium' : ''"
                        :aria-current="!current ? 'page' : undefined"
                        >All · সব</Link
                    >
                </li>
                <li v-for="category in categories" :key="category.slug">
                    <Link
                        :href="
                            shopIndex({ query: { category: category.slug } })
                        "
                        class="inline-block rounded-full border px-3 py-1 text-sm hover:bg-muted"
                        :class="
                            current?.slug === category.slug
                                ? 'border-foreground font-medium'
                                : ''
                        "
                        :aria-current="
                            current?.slug === category.slug ? 'page' : undefined
                        "
                        >{{ category.name }}
                        <span class="text-muted-foreground">{{
                            category.count
                        }}</span></Link
                    >
                </li>
            </ul>
        </nav>

        <ul
            v-if="products.data.length"
            class="mt-6 grid grid-cols-2 gap-3 sm:gap-6 md:grid-cols-3 lg:grid-cols-4"
        >
            <li v-for="(product, i) in products.data" :key="product.id">
                <ProductCard :product="product" :index="i" />
            </li>
        </ul>
        <div
            v-else
            class="mt-6 rounded-xl border p-10 text-center text-muted-foreground"
        >
            <p>No products found · কোনো পণ্য পাওয়া যায়নি</p>
            <Button variant="outline" class="mt-4" as-child>
                <Link :href="shopIndex()">See all products · সব পণ্য</Link>
            </Button>
        </div>

        <nav
            v-if="products.last_page > 1"
            class="mt-8 flex items-center justify-between text-sm"
            aria-label="Pagination"
        >
            <Button
                variant="outline"
                size="sm"
                :disabled="!products.prev_page_url"
                as-child
            >
                <Link :href="products.prev_page_url ?? '#'">Previous</Link>
            </Button>
            <span class="text-muted-foreground"
                >Page {{ products.current_page }} of
                {{ products.last_page }}</span
            >
            <Button
                variant="outline"
                size="sm"
                :disabled="!products.next_page_url"
                as-child
            >
                <Link :href="products.next_page_url ?? '#'">Next</Link>
            </Button>
        </nav>
    </div>
</template>
