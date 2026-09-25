<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { address as updateAddress, index as kyc, store } from '@/routes/kyc';

type Doc = {
    id: number;
    type: string;
    number: string;
    status: string;
    submitted: string | null;
    rejectionReason: string | null;
};

defineProps<{
    status: string;
    canSubmit: boolean;
    documents: Doc[];
    profile: {
        name: string;
        email: string;
        phone: string | null;
        address: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Profile & KYC', href: kyc() }],
    },
});

const docType = ref<'nid' | 'passport'>('nid');

const statusText: Record<string, { label: string; class: string }> = {
    not_submitted: {
        label: 'Not submitted · জমা দেওয়া হয়নি',
        class: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
    },
    pending: {
        label: 'Under review · যাচাই চলছে',
        class: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    },
    approved: {
        label: 'Approved · অনুমোদিত',
        class: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
    },
    rejected: {
        label: 'Rejected · বাতিল',
        class: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
    },
};

const fieldClass =
    'border-input dark:bg-input/30 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm';
</script>

<template>
    <Head title="Profile & KYC" />

    <div class="flex max-w-3xl flex-col gap-8 p-4">
        <section class="flex flex-col gap-4">
            <h1 class="text-xl font-semibold">Profile · প্রোফাইল</h1>
            <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1 text-sm">
                <dt class="text-muted-foreground">Name</dt>
                <dd>{{ profile.name }}</dd>
                <dt class="text-muted-foreground">Email</dt>
                <dd>{{ profile.email }}</dd>
                <dt class="text-muted-foreground">Mobile</dt>
                <dd>{{ profile.phone ?? '—' }}</dd>
            </dl>
            <p class="text-xs text-muted-foreground">
                Change your name or email under Settings → Profile.
            </p>

            <Form
                v-bind="updateAddress.form()"
                v-slot="{ errors, processing }"
                class="grid gap-2"
            >
                <Label for="address">Address · ঠিকানা</Label>
                <textarea
                    id="address"
                    name="address"
                    rows="2"
                    required
                    :class="fieldClass"
                    :value="profile.address ?? ''"
                />
                <InputError :message="errors.address" />
                <Button
                    type="submit"
                    variant="outline"
                    class="w-fit"
                    :disabled="processing"
                    >Save address</Button
                >
            </Form>
        </section>

        <section class="flex flex-col gap-4">
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-semibold">
                    KYC verification · পরিচয় যাচাই
                </h2>
                <span
                    :class="[
                        'rounded-full px-2 py-0.5 text-xs',
                        statusText[status]?.class,
                    ]"
                    data-test="kyc-status"
                    >{{ statusText[status]?.label ?? status }}</span
                >
            </div>

            <Form
                v-if="canSubmit"
                v-bind="store.form()"
                v-slot="{ errors, processing }"
                class="grid gap-4 rounded-xl border p-4"
            >
                <div class="flex gap-6 text-sm">
                    <label class="flex items-center gap-2">
                        <input
                            v-model="docType"
                            type="radio"
                            name="type"
                            value="nid"
                        />
                        National ID · জাতীয় পরিচয়পত্র
                    </label>
                    <label class="flex items-center gap-2">
                        <input
                            v-model="docType"
                            type="radio"
                            name="type"
                            value="passport"
                        />
                        Passport · পাসপোর্ট
                    </label>
                </div>
                <InputError :message="errors.type" />

                <div class="grid gap-2">
                    <Label for="document_number">{{
                        docType === 'nid' ? 'NID number' : 'Passport number'
                    }}</Label>
                    <Input
                        id="document_number"
                        name="document_number"
                        required
                        :placeholder="
                            docType === 'nid'
                                ? '10, 13 or 17 digits'
                                : 'e.g. A01234567'
                        "
                    />
                    <InputError :message="errors.document_number" />
                </div>

                <div class="grid gap-2">
                    <Label for="document"
                        >Document scan (JPG, PNG or PDF, max 5 MB)</Label
                    >
                    <input
                        id="document"
                        name="document"
                        type="file"
                        accept=".jpg,.jpeg,.png,.pdf"
                        required
                        class="text-sm"
                    />
                    <InputError :message="errors.document" />
                </div>

                <div class="grid gap-2">
                    <Label for="photo">Your photo (JPG or PNG, max 3 MB)</Label>
                    <input
                        id="photo"
                        name="photo"
                        type="file"
                        accept=".jpg,.jpeg,.png"
                        required
                        class="text-sm"
                    />
                    <InputError :message="errors.photo" />
                </div>

                <Button
                    type="submit"
                    class="w-fit"
                    :disabled="processing"
                    data-test="submit-kyc"
                >
                    <Spinner v-if="processing" />
                    Submit for review · জমা দিন
                </Button>
            </Form>

            <div
                v-if="documents.length"
                class="overflow-x-auto rounded-xl border"
            >
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Submitted</th>
                            <th class="px-4 py-2 font-medium">Document</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2 font-medium">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in documents" :key="d.id" class="border-t">
                            <td class="px-4 py-2">{{ d.submitted }}</td>
                            <td class="px-4 py-2">
                                {{ d.type }} {{ d.number }}
                            </td>
                            <td class="px-4 py-2">
                                <span
                                    :class="[
                                        'rounded-full px-2 py-0.5 text-xs',
                                        statusText[d.status]?.class,
                                    ]"
                                    >{{ statusText[d.status]?.label }}</span
                                >
                            </td>
                            <td class="px-4 py-2 text-muted-foreground">
                                {{ d.rejectionReason ?? '' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
