<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import WalletSummary from '@/components/WalletSummary.vue';
import type { WalletSummaryData } from '@/components/WalletSummary.vue';
import { index as wallet } from '@/routes/wallet';

type Transaction = {
    id: number;
    date: string | null;
    type: string;
    typeLabel: string;
    credit: boolean;
    amount: string;
    balanceAfter: string | null;
    reference: string | null;
    description: string | null;
    status: string;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Filters = { type: string | null; from: string | null; to: string | null };

const props = defineProps<{
    summary: WalletSummaryData;
    transactions: Paginated<Transaction>;
    filters: Filters;
    types: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Wallet', href: wallet() }],
    },
});

const form = reactive<Filters>({ ...props.filters });

const fieldClass =
    'border-input dark:bg-input/30 h-9 w-full rounded-md border bg-transparent px-3 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm';

function applyFilters() {
    const query = Object.fromEntries(
        Object.entries(form).filter(([, value]) => value),
    );
    router.get(wallet.url({ query }), {}, { preserveScroll: true });
}

function resetFilters() {
    form.type = form.from = form.to = null;
    router.get(wallet.url());
}

const statusClass: Record<string, string> = {
    completed:
        'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
    pending:
        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    voided: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
};
</script>

<template>
    <Head title="Wallet" />

    <div class="flex flex-col gap-6 p-4">
        <h1 class="text-xl font-semibold">Wallet · ওয়ালেট</h1>

        <WalletSummary :summary="summary" />

        <form
            class="grid gap-4 rounded-xl border p-4 sm:grid-cols-4"
            @submit.prevent="applyFilters"
        >
            <div class="grid gap-2">
                <Label for="type">Type · ধরন</Label>
                <select id="type" v-model="form.type" :class="fieldClass">
                    <option :value="null">All types</option>
                    <option v-for="t in types" :key="t.value" :value="t.value">
                        {{ t.label }}
                    </option>
                </select>
            </div>
            <div class="grid gap-2">
                <Label for="from">From · থেকে</Label>
                <input
                    id="from"
                    v-model="form.from"
                    type="date"
                    :class="fieldClass"
                />
            </div>
            <div class="grid gap-2">
                <Label for="to">To · পর্যন্ত</Label>
                <input
                    id="to"
                    v-model="form.to"
                    type="date"
                    :class="fieldClass"
                />
            </div>
            <div class="flex items-end gap-2">
                <Button type="submit" data-test="apply-filters">Filter</Button>
                <Button type="button" variant="outline" @click="resetFilters"
                    >Reset</Button
                >
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Date</th>
                        <th class="px-4 py-2 font-medium">Type</th>
                        <th class="px-4 py-2 text-right font-medium">Amount</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Balance
                        </th>
                        <th class="px-4 py-2 font-medium">Reference</th>
                        <th class="px-4 py-2 font-medium">Description</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="t in transactions.data"
                        :key="t.id"
                        class="border-t"
                        data-test="wallet-row"
                    >
                        <td class="px-4 py-2 whitespace-nowrap">
                            {{ t.date }}
                        </td>
                        <td class="px-4 py-2">{{ t.typeLabel }}</td>
                        <td
                            :class="[
                                'px-4 py-2 text-right font-medium whitespace-nowrap tabular-nums',
                                t.credit
                                    ? 'text-green-700 dark:text-green-400'
                                    : 'text-red-700 dark:text-red-400',
                            ]"
                        >
                            {{ t.amount }}
                        </td>
                        <td
                            class="px-4 py-2 text-right whitespace-nowrap text-muted-foreground tabular-nums"
                        >
                            {{ t.balanceAfter ?? '—' }}
                        </td>
                        <td class="px-4 py-2">{{ t.reference ?? '—' }}</td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ t.description ?? '' }}
                        </td>
                        <td class="px-4 py-2">
                            <span
                                :class="[
                                    'rounded-full px-2 py-0.5 text-xs',
                                    statusClass[t.status] ?? '',
                                ]"
                                >{{ t.status }}</span
                            >
                        </td>
                    </tr>
                    <tr v-if="transactions.data.length === 0">
                        <td
                            colspan="7"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            No transactions yet · কোনো লেনদেন নেই
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="transactions.last_page > 1"
            class="flex items-center justify-between text-sm"
            aria-label="Pagination"
        >
            <Button
                variant="outline"
                size="sm"
                :disabled="!transactions.prev_page_url"
                as-child
            >
                <Link :href="transactions.prev_page_url ?? '#'" preserve-scroll
                    >Previous</Link
                >
            </Button>
            <span class="text-muted-foreground"
                >Page {{ transactions.current_page }} of
                {{ transactions.last_page }} ({{ transactions.total }})</span
            >
            <Button
                variant="outline"
                size="sm"
                :disabled="!transactions.next_page_url"
                as-child
            >
                <Link :href="transactions.next_page_url ?? '#'" preserve-scroll
                    >Next</Link
                >
            </Button>
        </nav>
    </div>
</template>
