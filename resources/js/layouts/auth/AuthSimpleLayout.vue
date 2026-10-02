<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, BadgeCheck, ShieldCheck, Wallet } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { home } from '@/routes';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        /** Wider form column (registration). */
        wide?: boolean;
    }>(),
    { title: '', description: '', wide: false },
);

const page = usePage();

const points = [
    { icon: BadgeCheck, text: 'Genuine products, sold in clear bundles.' },
    { icon: ShieldCheck, text: 'Payments are confirmed with the gateway.' },
    {
        icon: Wallet,
        text: 'Track your orders, wallet and withdrawals in one place.',
    },
];
</script>

<template>
    <div class="grid min-h-svh bg-background lg:grid-cols-[minmax(0,5fr)_7fr]">
        <!-- Brand panel (desktop) -->
        <aside
            class="relative isolate hidden flex-col justify-between overflow-hidden bg-[#0b1a33] p-10 text-white lg:flex xl:p-14"
        >
            <div
                class="absolute inset-0 -z-10 bg-[radial-gradient(700px_380px_at_100%_0%,rgba(57,135,229,0.55),transparent_60%),radial-gradient(600px_360px_at_0%_100%,rgba(42,120,214,0.4),transparent_60%)]"
                aria-hidden="true"
            />
            <div
                class="absolute inset-0 -z-10 bg-[linear-gradient(rgba(255,255,255,0.05)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.05)_1px,transparent_1px)] mask-[radial-gradient(ellipse_at_center,black_30%,transparent_75%)] bg-size-[44px_44px]"
                aria-hidden="true"
            />

            <Link :href="home()" class="flex items-center gap-3 font-semibold">
                <span
                    class="flex size-10 items-center justify-center rounded-xl bg-brand shadow-lg shadow-black/30"
                >
                    <AppLogoIcon class="size-5" />
                </span>
                {{ page.props.name }}
            </Link>

            <div class="max-w-md">
                <p
                    class="text-4xl leading-tight font-semibold tracking-tight text-balance"
                >
                    {{ $t('Quality gadgets') }}
                    <span
                        class="bg-linear-to-r from-[#8cc0ff] to-white bg-clip-text text-transparent"
                        >{{ $t('at fair prices.') }}</span
                    >
                </p>
                <ul class="mt-10 space-y-5">
                    <li
                        v-for="point in points"
                        :key="point.text"
                        class="flex items-start gap-3 text-white/85"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15"
                        >
                            <component
                                :is="point.icon"
                                class="size-4"
                                aria-hidden="true"
                            />
                        </span>
                        <span class="pt-1.5">{{ $t(point.text) }}</span>
                    </li>
                </ul>
            </div>

            <p class="text-xs text-white/50">
                © {{ new Date().getFullYear() }} {{ page.props.name }}
            </p>
        </aside>

        <!-- Form column -->
        <main class="flex min-h-svh flex-col">
            <div
                class="flex items-center justify-between gap-3 px-6 py-5 sm:px-10"
            >
                <Link
                    :href="home()"
                    class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    {{ $t('Back to shop') }}
                </Link>
                <LanguageSwitcher />
            </div>

            <div
                class="flex flex-1 items-center justify-center px-6 pb-12 sm:px-10"
            >
                <div class="w-full" :class="wide ? 'max-w-xl' : 'max-w-md'">
                    <Link
                        :href="home()"
                        class="mb-8 inline-flex items-center gap-3 font-semibold lg:hidden"
                    >
                        <span
                            class="flex size-10 items-center justify-center rounded-xl bg-brand text-white shadow-md shadow-brand/25"
                        >
                            <AppLogoIcon class="size-5" />
                        </span>
                        {{ page.props.name }}
                    </Link>

                    <div class="mb-8">
                        <h1 class="text-2xl font-semibold tracking-tight">
                            {{ $t(title) }}
                        </h1>
                        <p
                            v-if="description"
                            class="mt-1.5 text-sm text-muted-foreground"
                        >
                            {{ $t(description) }}
                        </p>
                    </div>

                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>
