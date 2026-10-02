<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CircleCheck } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SponsorPicker from '@/components/SponsorPicker.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { login, membership } from '@/routes';
import { store } from '@/routes/register';
import { show as showSponsor } from '@/routes/sponsors';

type PackageOption = { id: number; name: string; price: string };

const props = defineProps<{
    passwordRules: string;
    packages: PackageOption[];
    sponsorCode: string | null;
    selectedPackageId?: number | null;
}>();

defineOptions({
    layout: {
        title: 'Create an account',
        description: 'Join with your sponsor’s ID.',
        wide: true,
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
        sponsorError.value = body.message ?? t('Sponsor not found.');
    }
}

if (sponsorCode.value) {
    void lookupSponsor();
}

const sides = [
    { value: 'left', label: 'Left' },
    { value: 'right', label: 'Right' },
] as const;
</script>

<template>
    <Head :title="$t('Create an account')" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-8"
    >
        <!-- Sponsor & package -->
        <section class="grid gap-5">
            <h2
                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
                {{ $t('Sponsor & package') }}
            </h2>

            <div class="grid gap-2">
                <Label for="sponsor_code">{{ $t('Sponsor ID') }}</Label>
                <SponsorPicker
                    id="sponsor_code"
                    v-model="sponsorCode"
                    name="sponsor_code"
                    :tabindex="1"
                    :autofocus="!sponsorCode"
                    :placeholder="$t('Sponsor ID or name, e.g. MBR-100001')"
                    @select="lookupSponsor"
                    @blur="lookupSponsor"
                />
                <p
                    v-if="!sponsorName && !sponsorError"
                    class="text-xs text-muted-foreground"
                >
                    {{
                        $t(
                            'Type the ID digits or at least 3 letters of the name, then pick your sponsor from the list.',
                        )
                    }}
                </p>
                <p
                    v-if="sponsorName"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-green-700 dark:text-green-400"
                    data-test="sponsor-name"
                >
                    <CircleCheck class="size-4" aria-hidden="true" />
                    {{ $t('Sponsor: :name', { name: sponsorName }) }}
                </p>
                <InputError
                    :message="errors.sponsor_code ?? sponsorError ?? undefined"
                />
            </div>

            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">
                    {{ $t('Position under sponsor') }}
                </legend>
                <div class="grid grid-cols-2 gap-3">
                    <label
                        v-for="(side, i) in sides"
                        :key="side.value"
                        class="flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 text-sm font-medium transition has-checked:border-brand has-checked:bg-brand-soft has-checked:text-brand has-focus-visible:ring-2 has-focus-visible:ring-brand"
                    >
                        <input
                            type="radio"
                            name="preferred_side"
                            :value="side.value"
                            required
                            :tabindex="2"
                            :checked="i === 0"
                            class="accent-brand"
                        />
                        {{ $t(side.label) }}
                    </label>
                </div>
                <InputError :message="errors.preferred_side" />
            </fieldset>

            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">
                    {{ $t('Package') }}
                </legend>
                <div class="grid grid-cols-2 gap-3">
                    <label
                        v-for="pkg in packages"
                        :key="pkg.id"
                        class="relative flex cursor-pointer flex-col rounded-xl border px-4 py-3 transition has-checked:border-brand has-checked:bg-brand-soft has-checked:ring-1 has-checked:ring-brand has-focus-visible:ring-2 has-focus-visible:ring-brand"
                    >
                        <input
                            type="radio"
                            name="package_id"
                            :value="pkg.id"
                            required
                            :tabindex="3"
                            :checked="pkg.id === selectedPackageId"
                            class="peer sr-only"
                        />
                        <span class="text-sm font-semibold">{{
                            pkg.name
                        }}</span>
                        <span
                            class="text-lg font-bold tracking-tight tabular-nums"
                            >{{ pkg.price }}</span
                        >
                        <CircleCheck
                            class="absolute top-3 right-3 hidden size-4 text-brand peer-checked:block"
                            aria-hidden="true"
                        />
                    </label>
                </div>
                <InputError :message="errors.package_id" />
            </fieldset>
        </section>

        <!-- Your details -->
        <section class="grid gap-5 border-t pt-8">
            <h2
                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
                {{ $t('Your details') }}
            </h2>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="name">{{ $t('Full name') }}</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        v-focus="!!sponsorCode"
                        :tabindex="4"
                        autocomplete="name"
                        name="name"
                        :placeholder="$t('Full name')"
                        class="h-11"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="phone">{{ $t('Mobile number') }}</Label>
                    <Input
                        id="phone"
                        type="tel"
                        required
                        :tabindex="5"
                        autocomplete="tel"
                        name="phone"
                        placeholder="01712345678"
                        class="h-11"
                    />
                    <InputError :message="errors.phone" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">{{ $t('Email address') }}</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="6"
                        autocomplete="email"
                        name="email"
                        placeholder="email@example.com"
                        class="h-11"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="nid">{{ $t('NID number') }}</Label>
                    <Input
                        id="nid"
                        type="text"
                        inputmode="numeric"
                        required
                        :tabindex="7"
                        name="nid"
                        :placeholder="$t('10, 13 or 17 digits')"
                        class="h-11"
                    />
                    <InputError :message="errors.nid" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="address">{{ $t('Address') }}</Label>
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
        </section>

        <!-- Password -->
        <section class="grid gap-5 border-t pt-8">
            <h2
                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
                {{ $t('Password') }}
            </h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="password">{{ $t('Password') }}</Label>
                    <PasswordInput
                        id="password"
                        required
                        :tabindex="9"
                        autocomplete="new-password"
                        name="password"
                        :placeholder="$t('Password')"
                        :passwordrules="passwordRules"
                        class="h-11"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">{{
                        $t('Confirm password')
                    }}</Label>
                    <PasswordInput
                        id="password_confirmation"
                        required
                        :tabindex="10"
                        autocomplete="new-password"
                        name="password_confirmation"
                        :placeholder="$t('Confirm password')"
                        :passwordrules="passwordRules"
                        class="h-11"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>
            </div>
        </section>

        <p
            class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
            data-test="earnings-disclaimer"
        >
            {{
                $t(
                    'Membership is sponsor-based: members earn commission only from genuine product sales in their team. There is no guaranteed income.',
                )
            }}
            <TextLink
                :href="membership()"
                target="_blank"
                class="font-semibold"
                data-test="membership-link"
                >{{ $t('How membership & earnings work') }}</TextLink
            >
        </p>

        <div class="grid gap-4">
            <Button
                type="submit"
                class="h-11 w-full text-base"
                tabindex="11"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                {{ $t('Create account') }}
            </Button>

            <p class="text-center text-sm text-muted-foreground">
                {{ $t('Already have an account?') }}
                <TextLink
                    :href="login()"
                    class="font-semibold"
                    :tabindex="12"
                    data-test="login-link"
                >
                    {{ $t('Log in') }}
                </TextLink>
            </p>
        </div>
    </Form>
</template>
