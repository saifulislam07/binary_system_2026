<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as checkout } from '@/routes/checkout';

const props = defineProps<{
    order: {
        number: string;
        status: string;
        amount: string;
        package: string;
        paidAt: string | null;
        memberCode: string | null;
        memberStatus: string;
    };
}>();

const outcome = computed(() => {
    switch (props.order.status) {
        case 'paid':
            return {
                title: 'Payment successful',
                tone: 'border-green-300 bg-green-50 text-green-900 dark:border-green-800 dark:bg-green-950 dark:text-green-100',
            };
        case 'pending':
            return {
                title: 'Confirming your payment…',
                tone: 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100',
            };
        case 'cancelled':
            return {
                title: 'Payment cancelled',
                tone: 'border-zinc-300 bg-zinc-50 text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100',
            };
        default:
            return {
                title: 'Payment failed',
                tone: 'border-red-300 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-100',
            };
    }
});
</script>

<template>
    <Head :title="$t('Order :number', { number: order.number })" />

    <div class="flex max-w-xl flex-col gap-4 p-4">
        <div
            :class="['rounded-xl border p-4', outcome.tone]"
            data-test="order-status"
        >
            <h1 class="text-lg font-semibold">{{ $t(outcome.title) }}</h1>
            <p v-if="order.status === 'paid' && order.memberCode" class="mt-1">
                {{ $t('Your member ID is') }}
                <strong data-test="member-code">{{ order.memberCode }}</strong
                >.
            </p>
        </div>

        <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
            <dt class="text-muted-foreground">{{ $t('Order') }}</dt>
            <dd>{{ order.number }}</dd>
            <dt class="text-muted-foreground">{{ $t('Package') }}</dt>
            <dd>{{ order.package }}</dd>
            <dt class="text-muted-foreground">{{ $t('Amount') }}</dt>
            <dd>{{ order.amount }}</dd>
            <template v-if="order.paidAt">
                <dt class="text-muted-foreground">{{ $t('Paid at') }}</dt>
                <dd>{{ order.paidAt }}</dd>
            </template>
        </dl>

        <div class="flex gap-2">
            <Button as-child>
                <Link :href="dashboard()">{{ $t('Go to dashboard') }}</Link>
            </Button>
            <Button
                v-if="order.status === 'failed' || order.status === 'cancelled'"
                variant="outline"
                as-child
            >
                <Link :href="checkout()">{{ $t('Try again') }}</Link>
            </Button>
        </div>
    </div>
</template>
