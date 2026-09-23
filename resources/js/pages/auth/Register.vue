<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { show as showSponsor } from '@/routes/sponsors';

type PackageOption = { id: number; name: string; price: string };

const props = defineProps<{
    passwordRules: string;
    packages: PackageOption[];
    sponsorCode: string | null;
}>();

defineOptions({
    layout: {
        title: 'Create an account',
        description:
            'Join with your sponsor’s ID · স্পনসরের আইডি দিয়ে যোগ দিন',
    },
});

const fieldClass =
    'border-input dark:bg-input/30 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm';

const sponsorCode = ref(props.sponsorCode ?? '');
const sponsorName = ref<string | null>(null);
const sponsorError = ref<string | null>(null);

async function lookupSponsor() {
    sponsorName.value = null;
    sponsorError.value = null;

    const code = sponsorCode.value.trim().toUpperCase();

    if (!/^[A-Z]{3}-\d{6,}$/.test(code)) {
        return;
    }

    const response = await fetch(showSponsor.url(code), {
        headers: { Accept: 'application/json' },
    });
    const body = await response.json();

    if (response.ok) {
        sponsorName.value = body.name;
    } else {
        sponsorError.value = body.message ?? 'Sponsor not found.';
    }
}

if (sponsorCode.value) {
    void lookupSponsor();
}
</script>

<template>
    <Head title="Register" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="sponsor_code">Sponsor ID · স্পনসর আইডি</Label>
                <Input
                    id="sponsor_code"
                    name="sponsor_code"
                    v-model="sponsorCode"
                    required
                    v-focus="!sponsorCode"
                    :tabindex="1"
                    placeholder="MBR-100001"
                    autocomplete="off"
                    @blur="lookupSponsor"
                />
                <p
                    v-if="sponsorName"
                    class="text-sm text-green-600"
                    data-test="sponsor-name"
                >
                    Sponsor: {{ sponsorName }}
                </p>
                <InputError
                    :message="errors.sponsor_code ?? sponsorError ?? undefined"
                />
            </div>

            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">
                    Position under sponsor · অবস্থান
                </legend>
                <div class="flex gap-6">
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            type="radio"
                            name="preferred_side"
                            value="left"
                            required
                            :tabindex="2"
                            checked
                        />
                        Left · বাম
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            type="radio"
                            name="preferred_side"
                            value="right"
                            :tabindex="2"
                        />
                        Right · ডান
                    </label>
                </div>
                <InputError :message="errors.preferred_side" />
            </fieldset>

            <div class="grid gap-2">
                <Label for="package_id">Package · প্যাকেজ</Label>
                <select
                    id="package_id"
                    name="package_id"
                    required
                    :tabindex="3"
                    :class="fieldClass"
                >
                    <option value="" disabled selected>Choose a package</option>
                    <option
                        v-for="pkg in packages"
                        :key="pkg.id"
                        :value="pkg.id"
                    >
                        {{ pkg.name }} — {{ pkg.price }}
                    </option>
                </select>
                <InputError :message="errors.package_id" />
            </div>

            <div class="grid gap-2">
                <Label for="name">Full name · পূর্ণ নাম</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    v-focus="!!sponsorCode"
                    :tabindex="4"
                    autocomplete="name"
                    name="name"
                    placeholder="Full name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="phone">Mobile number · মোবাইল নম্বর</Label>
                <Input
                    id="phone"
                    type="tel"
                    required
                    :tabindex="5"
                    autocomplete="tel"
                    name="phone"
                    placeholder="01712345678"
                />
                <InputError :message="errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address · ইমেইল</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="6"
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="nid">NID number · জাতীয় পরিচয়পত্র নম্বর</Label>
                <Input
                    id="nid"
                    type="text"
                    inputmode="numeric"
                    required
                    :tabindex="7"
                    name="nid"
                    placeholder="10, 13 or 17 digits"
                />
                <InputError :message="errors.nid" />
            </div>

            <div class="grid gap-2">
                <Label for="address">Address · ঠিকানা</Label>
                <textarea
                    id="address"
                    name="address"
                    rows="2"
                    required
                    :tabindex="8"
                    autocomplete="street-address"
                    :class="fieldClass"
                />
                <InputError :message="errors.address" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password · পাসওয়ার্ড</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="9"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="10"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Confirm password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="11"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Create account · নিবন্ধন করুন
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Already have an account?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="12"
                data-test="login-link"
            >
                Log in
            </TextLink>
        </div>
    </Form>
</template>
