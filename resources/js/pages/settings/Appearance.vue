<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="$t('Appearance settings')" />

    <h1 class="sr-only">{{ $t('Appearance settings') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="$t('Appearance')"
            :description="$t('Light, dark, or follow your device')"
        />
        <AppearanceTabs />
    </div>

    <div class="mt-10 space-y-6">
        <Heading
            variant="small"
            :title="$t('Language')"
            :description="
                $t('Choose the language for the site and your notifications')
            "
        />
        <LanguageSwitcher />
    </div>
</template>
