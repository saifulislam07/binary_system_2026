<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Clock,
    Mail,
    MapPin,
    Phone,
    Search,
    ShoppingBag,
    UserRound,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { joinUrl, rememberReferral } from '@/lib/shop';
import type { ShopNavigation } from '@/lib/shop';
import { dashboard, home, login, membership } from '@/routes';
import { index as shopIndex } from '@/routes/shop';

const page = usePage();
const signedIn = computed(() => Boolean(page.props.auth.user));
const nav = computed<ShopNavigation>(
    () =>
        (page.props.shop as ShopNavigation | null) ?? {
            categories: [],
            contact: {},
            canRegister: false,
        },
);

const search = ref(
    new URLSearchParams(page.url.split('?')[1] ?? '').get('q') ?? '',
);

function submitSearch() {
    const q = search.value.trim();
    router.get(shopIndex.url(q ? { query: { q } } : undefined));
}

const currentCategory = computed(() =>
    new URLSearchParams(page.url.split('?')[1] ?? '').get('category'),
);

onMounted(() => {
    // A referral link to any shop page remembers the sponsor for sign-up.
    rememberReferral(
        new URLSearchParams(window.location.search).get('ref')?.toUpperCase() ??
            null,
    );
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <div class="bg-[#2a78d6] text-white">
            <p class="mx-auto max-w-7xl px-4 py-2 text-center text-sm">
                Secure checkout with bKash, Nagad &amp; card · বিকাশ, নগদ ও
                কার্ডে নিরাপদ পেমেন্ট
            </p>
        </div>

        <header
            class="sticky top-0 z-30 border-b bg-background/95 backdrop-blur supports-backdrop-filter:bg-background/80"
        >
            <div
                class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:gap-6"
            >
                <Link
                    :href="home()"
                    class="flex shrink-0 items-center gap-2 font-semibold"
                    aria-label="Home"
                >
                    <span
                        class="flex size-9 items-center justify-center rounded-lg bg-[#2a78d6] text-white"
                    >
                        <AppLogoIcon class="size-5" />
                    </span>
                    <span class="hidden lg:inline">{{ page.props.name }}</span>
                </Link>

                <form
                    role="search"
                    class="relative min-w-0 flex-1"
                    @submit.prevent="submitSearch"
                >
                    <label for="shop-search" class="sr-only"
                        >Search products</label
                    >
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <input
                        id="shop-search"
                        v-model="search"
                        type="search"
                        placeholder="Search products · পণ্য খুঁজুন"
                        class="h-10 w-full rounded-full border border-input bg-muted/40 pr-4 pl-9 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                </form>

                <div class="flex shrink-0 items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="sm:w-auto sm:px-3"
                        as-child
                    >
                        <Link
                            :href="signedIn ? dashboard() : login()"
                            data-test="account-link"
                        >
                            <UserRound class="size-5" aria-hidden="true" />
                            <span class="hidden sm:inline">{{
                                signedIn ? 'My account' : 'Log in · লগইন'
                            }}</span>
                            <span class="sr-only sm:hidden">{{
                                signedIn ? 'My account' : 'Log in'
                            }}</span>
                        </Link>
                    </Button>
                    <Button class="hidden sm:inline-flex" as-child>
                        <Link :href="shopIndex()">
                            <ShoppingBag class="size-5" aria-hidden="true" />
                            Shop · দোকান
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Category bar -->
            <nav
                v-if="nav.categories.length"
                class="border-t"
                aria-label="Categories"
            >
                <ul
                    class="mx-auto flex max-w-7xl [scrollbar-width:none] gap-1 overflow-x-auto px-4 py-2 text-sm whitespace-nowrap [&::-webkit-scrollbar]:hidden"
                >
                    <li>
                        <Link
                            :href="shopIndex()"
                            class="inline-block rounded-full px-3 py-1 hover:bg-muted"
                            :class="
                                page.url.startsWith('/shop') && !currentCategory
                                    ? 'bg-muted font-medium'
                                    : ''
                            "
                            >All products · সব পণ্য</Link
                        >
                    </li>
                    <li v-for="category in nav.categories" :key="category.slug">
                        <Link
                            :href="
                                shopIndex({
                                    query: { category: category.slug },
                                })
                            "
                            class="inline-block rounded-full px-3 py-1 hover:bg-muted"
                            :class="
                                currentCategory === category.slug
                                    ? 'bg-muted font-medium'
                                    : ''
                            "
                            >{{ category.name }}</Link
                        >
                    </li>
                </ul>
            </nav>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <footer class="bg-slate-950 text-slate-300">
            <div
                class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div>
                    <div
                        class="flex items-center gap-2 font-semibold text-white"
                    >
                        <span
                            class="flex size-8 items-center justify-center rounded-md bg-[#2a78d6]"
                        >
                            <AppLogoIcon class="size-5" />
                        </span>
                        {{ page.props.name }}
                    </div>
                    <p class="mt-4 text-sm text-slate-400">
                        Genuine electronics at fair prices.
                    </p>
                </div>

                <nav aria-label="Shop categories">
                    <p class="font-semibold text-white">Shop · দোকান</p>
                    <ul class="mt-4 space-y-2 text-sm">
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
                                class="hover:text-white"
                                >{{ category.name
                                }}<template v-if="category.nameBn">
                                    · {{ category.nameBn }}</template
                                ></Link
                            >
                        </li>
                        <li>
                            <Link :href="shopIndex()" class="hover:text-white"
                                >All products · সব পণ্য</Link
                            >
                        </li>
                    </ul>
                </nav>

                <nav aria-label="Account">
                    <p class="font-semibold text-white">Account · অ্যাকাউন্ট</p>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li v-if="signedIn">
                            <Link :href="dashboard()" class="hover:text-white"
                                >My dashboard · ড্যাশবোর্ড</Link
                            >
                        </li>
                        <template v-else>
                            <li>
                                <Link :href="login()" class="hover:text-white"
                                    >Log in · লগইন</Link
                                >
                            </li>
                            <li v-if="nav.canRegister">
                                <Link :href="joinUrl()" class="hover:text-white"
                                    >Create account · অ্যাকাউন্ট খুলুন</Link
                                >
                            </li>
                        </template>
                        <li>
                            <Link
                                :href="membership()"
                                class="hover:text-white"
                                data-test="membership-link"
                                >Membership &amp; earnings · সদস্যপদ ও আয়</Link
                            >
                        </li>
                    </ul>
                </nav>

                <div v-if="Object.keys(nav.contact).length" data-test="contact">
                    <p class="font-semibold text-white">Contact · যোগাযোগ</p>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li v-if="nav.contact.phone" class="flex gap-2">
                            <Phone
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <a
                                :href="`tel:${nav.contact.phone}`"
                                class="hover:text-white"
                                >{{ nav.contact.phone }}</a
                            >
                        </li>
                        <li v-if="nav.contact.email" class="flex gap-2">
                            <Mail
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <a
                                :href="`mailto:${nav.contact.email}`"
                                class="hover:text-white"
                                >{{ nav.contact.email }}</a
                            >
                        </li>
                        <li v-if="nav.contact.address" class="flex gap-2">
                            <MapPin
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span>{{ nav.contact.address }}</span>
                        </li>
                        <li v-if="nav.contact.hours" class="flex gap-2">
                            <Clock
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span>{{ nav.contact.hours }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-slate-800">
                <p class="mx-auto max-w-7xl px-4 py-6 text-xs text-slate-400">
                    © {{ new Date().getFullYear() }} {{ page.props.name }} ·
                    Prices in BDT · দাম টাকায়
                </p>
            </div>
        </footer>
    </div>
</template>
