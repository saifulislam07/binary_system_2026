<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
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
        class="group relative flex h-full flex-col overflow-hidden rounded-2xl border bg-card transition duration-300 hover:-translate-y-0.5 hover:border-brand/40 hover:shadow-xl hover:shadow-slate-900/8 motion-reduce:transition-none motion-reduce:hover:translate-y-0"
        data-test="product-card"
    >
        <div class="relative aspect-4/3 overflow-hidden bg-surface">
            <img
                v-if="product.image"
                :src="product.image"
                :alt="product.name"
                loading="lazy"
                class="size-full object-cover transition-transform duration-500 group-hover:scale-105 motion-reduce:transition-none"
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
                class="absolute top-3 left-3 rounded-full bg-deal px-2.5 py-1 text-xs font-bold text-white shadow-sm"
                >−{{ product.discount }}%</span
            >
            <span
                class="absolute right-3 bottom-3 flex size-9 translate-y-2 items-center justify-center rounded-full bg-white text-slate-900 opacity-0 shadow-md transition group-hover:translate-y-0 group-hover:opacity-100 motion-reduce:transition-none"
                aria-hidden="true"
            >
                <ArrowUpRight class="size-4" />
            </span>
        </div>
        <div class="flex flex-1 flex-col p-3.5 sm:p-4">
            <p
                class="truncate text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
            >
                {{ product.brand ?? product.category ?? ' ' }}
            </p>
            <h3
                class="mt-1 line-clamp-2 text-sm leading-snug font-semibold sm:text-[15px]"
            >
                <!-- The whole card is the link (stretched). -->
                <Link
                    :href="show(product.slug)"
                    class="after:absolute after:inset-0 after:rounded-2xl focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-brand"
                    >{{ product.name }}</Link
                >
            </h3>
            <div class="mt-auto flex flex-wrap items-baseline gap-x-2 pt-3">
                <span
                    class="text-lg font-bold tracking-tight tabular-nums sm:text-xl"
                    :class="product.discount ? 'text-deal' : ''"
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
