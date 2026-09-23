<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index as checkout, store } from '@/routes/checkout';

type PackageOption = {
    id: number;
    name: string;
    description: string | null;
    price: string;
    bv: string;
};
type GatewayOption = { value: string; label: string };

const props = defineProps<{
    memberStatus: string;
    selectedPackageId: number | null;
    packages: PackageOption[];
    gateways: GatewayOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Packages', href: checkout() }],
    },
});

const packageId = ref<number | null>(
    props.selectedPackageId ?? props.packages[0]?.id ?? null,
);
const gateway = ref<string | null>(props.gateways[0]?.value ?? null);
</script>

<template>
    <Head title="Packages" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">
                Choose a package · প্যাকেজ নির্বাচন
            </h1>
            <p
                v-if="memberStatus === 'pending'"
                class="text-sm text-muted-foreground"
            >
                Your account activates as soon as this payment succeeds. পেমেন্ট
                সফল হলেই আপনার অ্যাকাউন্ট সক্রিয় হবে।
            </p>
        </div>

        <Form
            v-bind="store.form()"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <fieldset class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <legend class="sr-only">Package</legend>
                <label
                    v-for="pkg in packages"
                    :key="pkg.id"
                    class="cursor-pointer rounded-xl border p-4 transition has-[:checked]:border-primary has-[:checked]:ring-2 has-[:checked]:ring-primary/30"
                    :data-test="`package-${pkg.id}`"
                >
                    <input
                        v-model="packageId"
                        type="radio"
                        name="package_id"
                        :value="pkg.id"
                        class="sr-only"
                    />
                    <span class="block text-lg font-semibold">{{
                        pkg.name
                    }}</span>
                    <span class="block text-2xl font-bold">{{
                        pkg.price
                    }}</span>
                    <span class="block text-sm text-muted-foreground"
                        >{{ pkg.bv }} BV</span
                    >
                    <span
                        v-if="pkg.description"
                        class="mt-2 block text-sm text-muted-foreground"
                        >{{ pkg.description }}</span
                    >
                </label>
            </fieldset>
            <InputError :message="errors.package_id" />

            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">
                    Pay with · পেমেন্ট পদ্ধতি
                </legend>
                <label
                    v-for="option in gateways"
                    :key="option.value"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        v-model="gateway"
                        type="radio"
                        name="gateway"
                        :value="option.value"
                    />
                    {{ option.label }}
                </label>
                <InputError :message="errors.gateway" />
            </fieldset>

            <Button
                type="submit"
                class="w-full sm:w-auto"
                :disabled="processing || !packageId || !gateway"
                data-test="pay-button"
            >
                <Spinner v-if="processing" />
                Continue to payment · পেমেন্ট করুন
            </Button>
        </Form>
    </div>
</template>
