<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { tints } from '@/lib/shop';
import type { ProductCardData } from '@/lib/shop';
import { show } from '@/routes/shop';

defineProps<{
    product: ProductCardData;
    index?: number;
}>();
</script>

<template>
    <article
        class="group relative flex h-full flex-col overflow-hidden rounded-xl border bg-card transition-shadow hover:shadow-lg"
        data-test="product-card"
    >
        <div class="relative aspect-4/3 overflow-hidden bg-muted">
            <img
                v-if="product.image"
                :src="product.image"
                :alt="product.name"
                loading="lazy"
                class="size-full object-cover transition-transform duration-300 group-hover:scale-105"
            />
            <div
                v-else
                class="flex size-full items-center justify-center bg-linear-to-br"
                :class="tints[(index ?? 0) % tints.length]"
            >
                <AppLogoIcon class="size-10" />
            </div>
            <span
                v-if="product.discount"
                class="absolute top-2 left-2 rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white"
                >−{{ product.discount }}%</span
            >
        </div>
        <div class="flex flex-1 flex-col p-3 sm:p-4">
            <p v-if="product.category" class="text-xs text-muted-foreground">
                {{ product.category }}
            </p>
            <h3 class="mt-0.5 line-clamp-2 text-sm font-semibold sm:text-base">
                <!-- The whole card is the link (stretched). -->
                <Link
                    :href="show(product.slug)"
                    class="after:absolute after:inset-0 after:rounded-xl focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-[#2a78d6]"
                    >{{ product.name }}</Link
                >
            </h3>
            <div class="mt-auto flex flex-wrap items-baseline gap-x-2 pt-2">
                <span
                    class="text-lg font-bold tracking-tight tabular-nums sm:text-xl"
                    >{{ product.price }}</span
                >
                <span
                    v-if="product.compareAt"
                    class="text-xs text-muted-foreground tabular-nums line-through sm:text-sm"
                    >{{ product.compareAt }}</span
                >
            </div>
        </div>
    </article>
</template>
