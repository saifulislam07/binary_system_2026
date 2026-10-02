<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    BadgeCheck,
    ArrowRight,
    Clock,
    Languages,
    LayoutGrid,
    Mail,
    MapPin,
    Menu,
    Phone,
    Search,
    ShieldCheck,
    ShoppingBag,
    Tag,
    UserRound,
    X,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { joinUrl, localName, rememberReferral } from '@/lib/shop';
import type { ShopNavigation } from '@/lib/shop';
import { dashboard, home, login, membership } from '@/routes';
import { index as shopIndex } from '@/routes/shop';

const page = usePage();
const signedIn = computed(() => Boolean(page.props.auth.user));
const userName = computed(
    () => (page.props.auth.user as { name?: string } | null)?.name ?? '',
);
const nav = computed<ShopNavigation>(
    () =>
        (page.props.shop as ShopNavigation | null) ?? {
            categories: [],
            contact: {},
            canRegister: false,
        },
);

const query = computed(() => new URLSearchParams(page.url.split('?')[1] ?? ''));
const search = ref(query.value.get('q') ?? '');
const currentCategory = computed(() => query.value.get('category'));
const onDeals = computed(() => query.value.get('deals') === '1');
const onShop = computed(() => page.url.startsWith('/shop'));
const menuOpen = ref(false);

watch(
    () => page.url,
    () => (menuOpen.value = false),
);

function submitSearch() {
    const q = search.value.trim();
    router.get(shopIndex.url(q ? { query: { q } } : undefined));
}

onMounted(() => {
    // A referral link to any shop page remembers the sponsor for sign-up.
    rememberReferral(
        new URLSearchParams(window.location.search).get('ref')?.toUpperCase() ??
            null,
    );
});

const promises = [
    { icon: ShieldCheck, text: 'Secure payment' },
    { icon: BadgeCheck, text: 'Genuine products' },
    { icon: Languages, text: 'Bangla & English' },
];
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <!-- Utility bar -->
        <div class="bg-slate-950 text-slate-300">
            <div
                class="mx-auto flex h-9 max-w-7xl items-center justify-between gap-4 px-4 text-xs"
            >
                <p class="min-w-0 truncate">
                    <span class="font-medium text-white">{{
                        $t('bKash · Nagad · Card')
                    }}</span>
                    — {{ $t('secure checkout') }}
                </p>
                <div class="flex shrink-0 items-center gap-4">
                    <a
                        v-if="nav.contact.phone"
                        :href="`tel:${nav.contact.phone}`"
                        class="hidden items-center gap-1.5 hover:text-white md:inline-flex"
                    >
                        <Phone class="size-3.5" aria-hidden="true" />
                        {{ nav.contact.phone }}
                    </a>
                    <Link
                        :href="membership()"
                        class="hidden hover:text-white sm:inline"
                        >{{ $t('Membership') }}</Link
                    >
                    <LanguageSwitcher tone="dark" />
                </div>
            </div>
        </div>

        <header
            class="sticky top-0 z-30 border-b bg-background/90 backdrop-blur-md supports-backdrop-filter:bg-background/75"
        >
            <div
                class="mx-auto flex h-18 max-w-7xl items-center gap-3 px-4 sm:gap-6"
            >
                <button
                    type="button"
                    class="-ml-2 inline-flex size-10 items-center justify-center rounded-lg hover:bg-muted lg:hidden"
                    :aria-expanded="menuOpen"
                    aria-controls="shop-menu"
                    @click="menuOpen = !menuOpen"
                >
                    <component
                        :is="menuOpen ? X : Menu"
                        class="size-5"
                        aria-hidden="true"
                    />
                    <span class="sr-only">{{ $t('Categories menu') }}</span>
                </button>

                <Link
                    :href="home()"
                    class="flex shrink-0 items-center gap-2.5"
                    :aria-label="$t('Home')"
                >
                    <span
                        class="flex size-10 items-center justify-center rounded-xl bg-linear-to-br from-brand to-[#1b56a0] text-white shadow-md shadow-brand/25"
                    >
                        <AppLogoIcon class="size-5" />
                    </span>
                    <span class="hidden leading-tight lg:block">
                        <span
                            class="block text-[15px] font-semibold tracking-tight"
                            >{{ page.props.name }}</span
                        >
                        <span class="block text-xs text-muted-foreground">{{
                            $t('Genuine electronics')
                        }}</span>
                    </span>
                </Link>

                <form
                    role="search"
                    class="flex min-w-0 flex-1 items-center rounded-full border border-input bg-muted/50 p-1 transition focus-within:border-brand focus-within:bg-background focus-within:ring-4 focus-within:ring-brand/15"
                    @submit.prevent="submitSearch"
                >
                    <label for="shop-search" class="sr-only">{{
                        $t('Search products')
                    }}</label>
                    <Search
                        class="ml-3 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <input
                        id="shop-search"
                        v-model="search"
                        type="search"
                        :placeholder="$t('Search products, brands')"
                        class="h-9 min-w-0 flex-1 bg-transparent px-2.5 text-sm outline-none placeholder:text-muted-foreground"
                    />
                    <button
                        type="submit"
                        class="hidden h-9 shrink-0 rounded-full bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-strong sm:block"
                    >
                        {{ $t('Search') }}
                    </button>
                </form>

                <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                    <Link
                        :href="signedIn ? dashboard() : login()"
                        class="flex items-center gap-2.5 rounded-xl px-2 py-1.5 hover:bg-muted"
                        data-test="account-link"
                    >
                        <span
                            class="flex size-9 items-center justify-center rounded-full bg-brand-soft text-brand"
                        >
                            <UserRound class="size-[18px]" aria-hidden="true" />
                        </span>
                        <span class="hidden leading-tight sm:block">
                            <span class="block text-xs text-muted-foreground">{{
                                signedIn
                                    ? $t('Hello, :name', {
                                          name: userName.split(' ')[0],
                                      })
                                    : $t('Hello, sign in')
                            }}</span>
                            <span class="block text-sm font-semibold">{{
                                signedIn ? $t('My account') : $t('Log in')
                            }}</span>
                        </span>
                        <span class="sr-only sm:hidden">{{
                            signedIn ? $t('My account') : $t('Log in')
                        }}</span>
                    </Link>
                    <Link
                        :href="shopIndex()"
                        class="hidden items-center gap-2 rounded-xl bg-foreground px-4 py-2.5 text-sm font-semibold text-background transition hover:opacity-90 md:inline-flex"
                    >
                        <ShoppingBag class="size-4" aria-hidden="true" />
                        {{ $t('Shop') }}
                    </Link>
                </div>
            </div>

            <!-- Category bar -->
            <nav
                v-if="nav.categories.length"
                class="hidden border-t lg:block"
                :aria-label="$t('Categories')"
            >
                <ul
                    class="mx-auto flex h-12 max-w-7xl items-center gap-1 px-4 text-sm"
                >
                    <li>
                        <Link
                            :href="shopIndex()"
                            class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 font-medium hover:bg-muted"
                            :class="
                                onShop && !currentCategory && !onDeals
                                    ? 'bg-brand-soft text-brand'
                                    : ''
                            "
                        >
                            <LayoutGrid class="size-4" aria-hidden="true" />
                            {{ $t('All products') }}
                        </Link>
                    </li>
                    <li v-for="category in nav.categories" :key="category.slug">
                        <Link
                            :href="
                                shopIndex({
                                    query: { category: category.slug },
                                })
                            "
                            class="inline-block rounded-lg px-3 py-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                            :class="
                                currentCategory === category.slug
                                    ? 'bg-brand-soft font-medium text-brand!'
                                    : ''
                            "
                            >{{ localName(category) }}</Link
                        >
                    </li>
                    <li class="ml-auto">
                        <Link
                            :href="shopIndex({ query: { deals: 1 } })"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 font-semibold text-deal hover:bg-deal/10"
                            :class="onDeals ? 'bg-deal/10' : ''"
                        >
                            <Tag class="size-4" aria-hidden="true" />
                            {{ $t('Deals') }}
                        </Link>
                    </li>
                </ul>
            </nav>

            <!-- Mobile category menu -->
            <nav
                v-if="menuOpen"
                id="shop-menu"
                class="border-t lg:hidden"
                :aria-label="$t('Categories')"
            >
                <ul class="mx-auto grid max-w-7xl gap-1 px-4 py-3 text-sm">
                    <li>
                        <Link
                            :href="shopIndex()"
                            class="flex items-center gap-2 rounded-lg px-3 py-2.5 font-medium hover:bg-muted"
                        >
                            <LayoutGrid class="size-4" aria-hidden="true" />
                            {{ $t('All products') }}
                        </Link>
                    </li>
                    <li v-for="category in nav.categories" :key="category.slug">
                        <Link
                            :href="
                                shopIndex({
                                    query: { category: category.slug },
                                })
                            "
                            class="flex justify-between rounded-lg px-3 py-2.5 hover:bg-muted"
                            :class="
                                currentCategory === category.slug
                                    ? 'bg-brand-soft text-brand'
                                    : ''
                            "
                        >
                            <span>{{ localName(category) }}</span>
                        </Link>
                    </li>
                    <li>
                        <Link
                            :href="shopIndex({ query: { deals: 1 } })"
                            class="flex items-center gap-2 rounded-lg px-3 py-2.5 font-semibold text-deal hover:bg-deal/10"
                        >
                            <Tag class="size-4" aria-hidden="true" />
                            {{ $t('Deals') }}
                        </Link>
                    </li>
                </ul>
            </nav>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <!-- Service promises -->
        <section
            class="border-t bg-surface"
            :aria-label="$t('Why shop with us')"
        >
            <ul class="mx-auto grid max-w-7xl gap-4 px-4 py-6 sm:grid-cols-3">
                <li
                    v-for="promise in promises"
                    :key="promise.text"
                    class="flex items-center justify-center gap-3 text-sm font-medium sm:justify-start"
                >
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-background text-brand shadow-sm ring-1 ring-border"
                    >
                        <component
                            :is="promise.icon"
                            class="size-5"
                            aria-hidden="true"
                        />
                    </span>
                    {{ $t(promise.text) }}
                </li>
            </ul>
        </section>

        <footer class="bg-slate-950 text-slate-400">
            <div
                class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1.2fr]"
            >
                <div>
                    <div
                        class="flex items-center gap-2.5 font-semibold text-white"
                    >
                        <span
                            class="flex size-9 items-center justify-center rounded-xl bg-brand"
                        >
                            <AppLogoIcon class="size-5" />
                        </span>
                        {{ page.props.name }}
                    </div>
                    <p class="mt-4 max-w-xs text-sm leading-relaxed">
                        {{
                            $t(
                                'Genuine electronics at fair prices, sold in clear bundles.',
                            )
                        }}
                    </p>
                    <ul
                        class="mt-6 flex flex-wrap gap-2"
                        :aria-label="$t('Accepted payment methods')"
                    >
                        <li
                            class="rounded-md bg-white px-2.5 py-1 text-xs font-bold text-[#e2136e]"
                        >
                            bKash
                        </li>
                        <li
                            class="rounded-md bg-white px-2.5 py-1 text-xs font-bold text-[#ec1c24]"
                        >
                            Nagad
                        </li>
                        <li
                            class="rounded-md bg-white px-2.5 py-1 text-xs font-bold text-[#1a1f71]"
                        >
                            VISA
                        </li>
                        <li
                            class="rounded-md bg-white px-2.5 py-1 text-xs font-bold text-slate-800"
                        >
                            Mastercard
                        </li>
                    </ul>
                </div>

                <nav :aria-label="$t('Shop categories')">
                    <p
                        class="text-xs font-semibold tracking-wider text-white uppercase"
                    >
                        {{ $t('Shop') }}
                    </p>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        <li
                            v-for="category in nav.categories"
                            :key="category.slug"
                        >
                            <Link
                                :href="
                                    shopIndex({
                                        query: { category: category.slug },
                                    })
                                "
                                class="transition hover:text-white"
                                >{{ localName(category) }}</Link
                            >
                        </li>
                        <li>
                            <Link
                                :href="shopIndex()"
                                class="transition hover:text-white"
                                >{{ $t('All products') }}</Link
                            >
                        </li>
                    </ul>
                </nav>

                <nav :aria-label="$t('Account')">
                    <p
                        class="text-xs font-semibold tracking-wider text-white uppercase"
                    >
                        {{ $t('Account') }}
                    </p>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        <li v-if="signedIn">
                            <Link
                                :href="dashboard()"
                                class="transition hover:text-white"
                                >{{ $t('My dashboard') }}</Link
                            >
                        </li>
                        <template v-else>
                            <li>
                                <Link
                                    :href="login()"
                                    class="transition hover:text-white"
                                    >{{ $t('Log in') }}</Link
                                >
                            </li>
                            <li v-if="nav.canRegister">
                                <Link
                                    :href="joinUrl()"
                                    class="transition hover:text-white"
                                    >{{ $t('Create account') }}</Link
                                >
                            </li>
                        </template>
                        <li>
                            <Link
                                :href="membership()"
                                class="transition hover:text-white"
                                data-test="membership-link"
                                >{{ $t('Membership & earnings') }}</Link
                            >
                        </li>
                    </ul>
                </nav>

                <div v-if="Object.keys(nav.contact).length">
                    <p
                        class="text-xs font-semibold tracking-wider text-white uppercase"
                    >
                        {{ $t('Contact') }}
                    </p>
                    <ul class="mt-4 space-y-3 text-sm" data-test="contact">
                        <li v-if="nav.contact.phone" class="flex gap-2.5">
                            <Phone
                                class="mt-0.5 size-4 shrink-0 text-slate-500"
                                aria-hidden="true"
                            />
                            <a
                                :href="`tel:${nav.contact.phone}`"
                                class="transition hover:text-white"
                                >{{ nav.contact.phone }}</a
                            >
                        </li>
                        <li v-if="nav.contact.email" class="flex gap-2.5">
                            <Mail
                                class="mt-0.5 size-4 shrink-0 text-slate-500"
                                aria-hidden="true"
                            />
                            <a
                                :href="`mailto:${nav.contact.email}`"
                                class="transition hover:text-white"
                                >{{ nav.contact.email }}</a
                            >
                        </li>
                        <li v-if="nav.contact.address" class="flex gap-2.5">
                            <MapPin
                                class="mt-0.5 size-4 shrink-0 text-slate-500"
                                aria-hidden="true"
                            />
                            <span>{{ nav.contact.address }}</span>
                        </li>
                        <li v-if="nav.contact.hours" class="flex gap-2.5">
                            <Clock
                                class="mt-0.5 size-4 shrink-0 text-slate-500"
                                aria-hidden="true"
                            />
                            <span>{{ nav.contact.hours }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-white/10">
                <div
                    class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-6 text-xs"
                >
                    <p>
                        © {{ new Date().getFullYear() }} {{ page.props.name }} ·
                        {{ $t('Prices in BDT') }}
                    </p>
                    <Link
                        :href="membership()"
                        class="inline-flex items-center gap-1 transition hover:text-white"
                        >{{ $t('How membership works') }}
                        <ArrowRight class="size-3.5" aria-hidden="true"
                    /></Link>
                </div>
            </div>
        </footer>
    </div>
</template>
