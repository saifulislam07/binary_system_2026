<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { index as income } from '@/routes/income';

type Row = {
    id: number;
    date: string;
    type: string;
    amount: string;
    negative: boolean;
    status: string;
    source: string | null;
    description: string | null;
};
type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const props = defineProps<{
    tab: string;
    tabs: Record<string, string>;
    filters: { from: string | null; to: string | null };
    net: string;
    rows: Paginated<Row>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Income', href: income() }],
    },
});

const form = reactive({ ...props.filters });

const fieldClass =
    'border-input dark:bg-input/30 h-9 w-full rounded-md border bg-transparent px-3 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm';

function tabUrl(tab: string) {
    const query: Record<string, string> = { tab };

    if (form.from) {
        query.from = form.from;
    }

    if (form.to) {
        query.to = form.to;
    }

    return income.url({ query });
}

function applyFilters() {
    router.get(tabUrl(props.tab), {}, { preserveScroll: true });
}

const statusClass: Record<string, string> = {
    paid: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
    reversed: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
    voided: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
    pending:
        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
};
</script>

<template>
    <Head title="Income" />

    <div class="flex flex-col gap-6 p-4">
        <h1 class="text-xl font-semibold">Income · আয়</h1>

        <nav class="flex flex-wrap gap-2" aria-label="Income type">
            <Link
                v-for="(label, key) in tabs"
                :key="key"
                :href="tabUrl(String(key))"
                preserve-scroll
                :class="[
                    'rounded-full border px-3 py-1 text-sm',
                    key === tab
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'hover:bg-muted',
                ]"
                :aria-current="key === tab ? 'page' : undefined"
                :data-test="`tab-${key}`"
                >{{ label }}</Link
            >
        </nav>

        <form
            class="flex flex-wrap items-end gap-4"
            @submit.prevent="applyFilters"
        >
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
            <Button type="submit">Filter</Button>
            <p class="ml-auto text-sm" data-test="net">
                Net total: <strong>{{ net }}</strong>
            </p>
        </form>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full min-w-[640px] text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Date</th>
                        <th class="px-4 py-2 text-right font-medium">Amount</th>
                        <th class="px-4 py-2 font-medium">Source</th>
                        <th class="px-4 py-2 font-medium">Description</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="r in rows.data"
                        :key="r.id"
                        class="border-t"
                        data-test="income-row"
                    >
                        <td class="px-4 py-2 whitespace-nowrap">
                            {{ r.date }}
                        </td>
                        <td
                            :class="[
                                'px-4 py-2 text-right font-medium whitespace-nowrap tabular-nums',
                                r.negative
                                    ? 'text-red-700 dark:text-red-400'
                                    : '',
                            ]"
                        >
                            {{ r.amount }}
                        </td>
                        <td class="px-4 py-2">{{ r.source ?? '—' }}</td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ r.description ?? '' }}
                        </td>
                        <td class="px-4 py-2">
                            <span
                                :class="[
                                    'rounded-full px-2 py-0.5 text-xs',
                                    statusClass[r.status] ?? '',
                                ]"
                                >{{ r.status }}</span
                            >
                        </td>
                    </tr>
                    <tr v-if="rows.data.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            Nothing here yet · এখনো কিছু নেই
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="rows.last_page > 1"
            class="flex items-center justify-between text-sm"
            aria-label="Pagination"
        >
            <Button
                variant="outline"
                size="sm"
                :disabled="!rows.prev_page_url"
                as-child
            >
                <Link :href="rows.prev_page_url ?? '#'" preserve-scroll
                    >Previous</Link
                >
            </Button>
            <span class="text-muted-foreground"
                >Page {{ rows.current_page }} of {{ rows.last_page }}</span
            >
            <Button
                variant="outline"
                size="sm"
                :disabled="!rows.next_page_url"
                as-child
            >
                <Link :href="rows.next_page_url ?? '#'" preserve-scroll
                    >Next</Link
                >
            </Button>
        </nav>
    </div>
</template>
