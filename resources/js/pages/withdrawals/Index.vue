<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index as withdrawalsIndex, store } from '@/routes/withdrawals';

type SavedMethod = { id: number; label: string; isDefault: boolean };
type WithdrawalRow = {
    id: number;
    date: string | null;
    amount: string;
    account: string;
    status: string;
    statusLabel: string;
    rejectionReason: string | null;
    processedAt: string | null;
};
type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const props = defineProps<{
    canRequest: boolean;
    balance: string;
    minimum: string;
    providers: Record<string, string>;
    savedMethods: SavedMethod[];
    withdrawals: Paginated<WithdrawalRow>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Withdrawals', href: withdrawalsIndex() }],
    },
});

const defaultMethod = props.savedMethods.find((m) => m.isDefault);
const methodChoice = ref<string>(
    defaultMethod ? String(defaultMethod.id) : 'new',
);
const newType = ref<'mobile_banking' | 'bank'>('mobile_banking');
const usingNew = computed(() => methodChoice.value === 'new');

const fieldClass =
    'border-input dark:bg-input/30 h-9 w-full rounded-md border bg-transparent px-3 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm';

const statusClass: Record<string, string> = {
    pending:
        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    approved: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
    processing:
        'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200',
    paid: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
};
</script>

<template>
    <Head title="Withdrawals" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Withdrawals · উত্তোলন</h1>
            <p class="text-sm text-muted-foreground">
                Available: <strong data-test="balance">{{ balance }}</strong> ·
                Minimum withdrawal: {{ minimum }}
            </p>
        </div>

        <Form
            v-if="canRequest"
            v-bind="store.form()"
            :reset-on-success="['amount']"
            v-slot="{ errors, processing }"
            class="grid gap-4 rounded-xl border p-4 md:max-w-xl"
        >
            <div class="grid gap-2">
                <Label for="amount">Amount (৳) · পরিমাণ</Label>
                <Input
                    id="amount"
                    name="amount"
                    inputmode="decimal"
                    required
                    placeholder="1000"
                />
                <InputError :message="errors.amount" />
            </div>

            <fieldset class="grid gap-2">
                <legend class="mb-1 text-sm font-medium">
                    Pay to · প্রাপকের অ্যাকাউন্ট
                </legend>
                <label
                    v-for="m in savedMethods"
                    :key="m.id"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        v-model="methodChoice"
                        type="radio"
                        :value="String(m.id)"
                    />
                    {{ m.label }}
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="methodChoice" type="radio" value="new" />
                    New account · নতুন অ্যাকাউন্ট
                </label>
                <input
                    v-if="!usingNew"
                    type="hidden"
                    name="withdrawal_method_id"
                    :value="methodChoice"
                />
                <InputError :message="errors.withdrawal_method_id" />
            </fieldset>

            <template v-if="usingNew">
                <div class="flex gap-6 text-sm">
                    <label class="flex items-center gap-2">
                        <input
                            v-model="newType"
                            type="radio"
                            name="method"
                            value="mobile_banking"
                        />
                        Mobile banking · মোবাইল ব্যাংকিং
                    </label>
                    <label class="flex items-center gap-2">
                        <input
                            v-model="newType"
                            type="radio"
                            name="method"
                            value="bank"
                        />
                        Bank · ব্যাংক
                    </label>
                </div>
                <InputError :message="errors.method" />

                <div
                    v-if="newType === 'mobile_banking'"
                    class="grid gap-4 sm:grid-cols-2"
                >
                    <div class="grid gap-2">
                        <Label for="provider">Provider</Label>
                        <select
                            id="provider"
                            name="provider"
                            :class="fieldClass"
                        >
                            <option
                                v-for="(label, value) in providers"
                                :key="value"
                                :value="value"
                            >
                                {{ label }}
                            </option>
                        </select>
                        <InputError :message="errors.provider" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="mobile_number"
                            >Mobile number · মোবাইল নম্বর</Label
                        >
                        <Input
                            id="mobile_number"
                            name="mobile_number"
                            type="tel"
                            placeholder="01712345678"
                        />
                        <InputError :message="errors.mobile_number" />
                    </div>
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="bank_name">Bank name · ব্যাংক</Label>
                        <Input id="bank_name" name="bank_name" />
                        <InputError :message="errors.bank_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="branch_name">Branch · শাখা</Label>
                        <Input id="branch_name" name="branch_name" />
                        <InputError :message="errors.branch_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="account_name"
                            >Account name · হিসাবের নাম</Label
                        >
                        <Input id="account_name" name="account_name" />
                        <InputError :message="errors.account_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="account_number"
                            >Account number · হিসাব নম্বর</Label
                        >
                        <Input
                            id="account_number"
                            name="account_number"
                            inputmode="numeric"
                        />
                        <InputError :message="errors.account_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="routing_number"
                            >Routing number (optional)</Label
                        >
                        <Input
                            id="routing_number"
                            name="routing_number"
                            inputmode="numeric"
                        />
                        <InputError :message="errors.routing_number" />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="save_method" value="0" />
                    <input
                        type="checkbox"
                        name="save_method"
                        value="1"
                        checked
                    />
                    Save this account for next time
                </label>
            </template>

            <Button
                type="submit"
                :disabled="processing"
                class="w-full sm:w-auto"
                data-test="request-withdrawal"
            >
                <Spinner v-if="processing" />
                Request withdrawal · উত্তোলনের অনুরোধ
            </Button>
        </Form>
        <p v-else class="text-sm text-muted-foreground">
            Withdrawals are available once your account is active.
        </p>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full min-w-[640px] text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Requested</th>
                        <th class="px-4 py-2 text-right font-medium">Amount</th>
                        <th class="px-4 py-2 font-medium">Account</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                        <th class="px-4 py-2 font-medium">Note</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="w in withdrawals.data"
                        :key="w.id"
                        class="border-t"
                        data-test="withdrawal-row"
                    >
                        <td class="px-4 py-2 whitespace-nowrap">
                            {{ w.date }}
                        </td>
                        <td
                            class="px-4 py-2 text-right font-medium whitespace-nowrap tabular-nums"
                        >
                            {{ w.amount }}
                        </td>
                        <td class="px-4 py-2">{{ w.account }}</td>
                        <td class="px-4 py-2">
                            <span
                                :class="[
                                    'rounded-full px-2 py-0.5 text-xs whitespace-nowrap',
                                    statusClass[w.status] ?? '',
                                ]"
                                >{{ w.statusLabel }}</span
                            >
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            <template v-if="w.rejectionReason">{{
                                w.rejectionReason
                            }}</template>
                            <template v-else-if="w.processedAt"
                                >Paid {{ w.processedAt }}</template
                            >
                        </td>
                    </tr>
                    <tr v-if="withdrawals.data.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            No withdrawals yet · কোনো উত্তোলন নেই
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="withdrawals.last_page > 1"
            class="flex items-center justify-between text-sm"
            aria-label="Pagination"
        >
            <Button
                variant="outline"
                size="sm"
                :disabled="!withdrawals.prev_page_url"
                as-child
            >
                <Link :href="withdrawals.prev_page_url ?? '#'" preserve-scroll
                    >Previous</Link
                >
            </Button>
            <span class="text-muted-foreground"
                >Page {{ withdrawals.current_page }} of
                {{ withdrawals.last_page }}</span
            >
            <Button
                variant="outline"
                size="sm"
                :disabled="!withdrawals.next_page_url"
                as-child
            >
                <Link :href="withdrawals.next_page_url ?? '#'" preserve-scroll
                    >Next</Link
                >
            </Button>
        </nav>
    </div>
</template>
