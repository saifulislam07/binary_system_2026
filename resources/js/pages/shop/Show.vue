<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Check, ChevronRight, ShoppingCart } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ProductCard from '@/components/shop/ProductCard.vue';
import { Button } from '@/components/ui/button';
import { buyPackageUrl } from '@/lib/shop';
import type { ProductCardData } from '@/lib/shop';
import { home } from '@/routes';
import { index as shopIndex } from '@/routes/shop';

type Product = ProductCardData & {
    description: string | null;
    highlights: string[];
    categorySlug: string | null;
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
const shown = computed(() => props.product.gallery[active.value] ?? null);
</script>

<template>
    <Head :title="product.name" />

    <div class="mx-auto max-w-7xl px-4 py-6">
        <nav aria-label="Breadcrumb" class="text-sm text-muted-foreground">
            <ol class="flex flex-wrap items-center gap-1">
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
            </ol>
        </nav>

        <div class="mt-6 grid gap-8 lg:grid-cols-2 lg:gap-12">
            <!-- Gallery -->
            <div>
                <div class="overflow-hidden rounded-2xl border bg-muted">
                    <img
                        v-if="shown"
                        :src="shown.large"
                        :alt="product.name"
                        class="aspect-4/3 w-full object-contain"
                    />
                    <div
                        v-else
                        class="flex aspect-4/3 items-center justify-center bg-linear-to-br from-sky-100 to-blue-200 text-blue-700 dark:from-sky-950 dark:to-blue-900 dark:text-blue-200"
                    >
                        <AppLogoIcon class="size-16" />
                    </div>
                </div>
                <ul
                    v-if="product.gallery.length > 1"
                    class="mt-3 flex gap-3"
                    aria-label="Photos"
                >
                    <li v-for="(image, i) in product.gallery" :key="image.id">
                        <button
                            type="button"
                            class="block overflow-hidden rounded-lg border-2 focus-visible:ring-2 focus-visible:ring-[#2a78d6] focus-visible:outline-none"
                            :class="
                                i === active
                                    ? 'border-[#2a78d6]'
                                    : 'border-transparent'
                            "
                            :aria-label="`Photo ${i + 1}`"
                            :aria-pressed="i === active"
                            @click="active = i"
                        >
                            <img
                                :src="image.thumb"
                                alt=""
                                class="h-16 w-20 object-cover"
                            />
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Details -->
            <div>
                <p v-if="product.brand" class="text-sm text-muted-foreground">
                    {{ product.brand }}
                </p>
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    {{ product.name }}
                </h1>

                <div class="mt-4 flex flex-wrap items-baseline gap-3">
                    <span class="text-3xl font-bold tabular-nums">{{
                        product.price
                    }}</span>
                    <template v-if="product.compareAt">
                        <span
                            class="text-lg text-muted-foreground tabular-nums line-through"
                            >{{ product.compareAt }}</span
                        >
                        <span
                            class="rounded-full bg-red-600 px-2 py-0.5 text-sm font-semibold text-white"
                            >Save {{ product.discount }}%</span
                        >
                    </template>
                </div>

                <p
                    v-if="product.description"
                    class="mt-6 leading-relaxed text-muted-foreground"
                >
                    {{ product.description }}
                </p>

                <ul v-if="product.highlights.length" class="mt-6 space-y-2">
                    <li
                        v-for="highlight in product.highlights"
                        :key="highlight"
                        class="flex gap-2"
                    >
                        <Check
                            class="mt-0.5 size-5 shrink-0 text-[#2a78d6]"
                            aria-hidden="true"
                        />
                        <span>{{ highlight }}</span>
                    </li>
                </ul>

                <!-- Where to buy it -->
                <section
                    class="mt-8 rounded-2xl border p-5"
                    aria-labelledby="buy-title"
                >
                    <h2 id="buy-title" class="font-semibold">
                        Get it in a package · প্যাকেজে কিনুন
                    </h2>
                    <template v-if="packages.length">
                        <p class="mt-1 text-sm text-muted-foreground">
                            This product comes in the package{{
                                packages.length > 1 ? 's' : ''
                            }}
                            below.
                        </p>
                        <ul class="mt-4 space-y-3">
                            <li
                                v-for="pkg in packages"
                                :key="pkg.id"
                                class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-muted/50 p-3"
                                data-test="package-offer"
                            >
                                <div>
                                    <p class="font-medium">
                                        {{ pkg.name }} package
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ pkg.price
                                        }}<template v-if="pkg.quantity > 1">
                                            · includes
                                            {{ pkg.quantity }}</template
                                        >
                                    </p>
                                </div>
                                <Button v-if="signedIn || canRegister" as-child>
                                    <Link
                                        :href="buyPackageUrl(signedIn, pkg.id)"
                                    >
                                        <ShoppingCart
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Buy · কিনুন
                                    </Link>
                                </Button>
                            </li>
                        </ul>
                    </template>
                    <p v-else class="mt-2 text-sm text-muted-foreground">
                        Coming soon in a package · শীঘ্রই প্যাকেজে পাওয়া যাবে
                    </p>
                </section>
            </div>
        </div>

        <section
            v-if="related.length"
            class="mt-12"
            aria-labelledby="related-title"
        >
            <h2 id="related-title" class="text-xl font-semibold">
                You may also like · আরও দেখুন
            </h2>
            <ul class="mt-4 grid grid-cols-2 gap-3 sm:gap-6 lg:grid-cols-4">
                <li v-for="(item, i) in related" :key="item.id">
                    <ProductCard :product="item" :index="i" />
                </li>
            </ul>
        </section>
    </div>
</template>
