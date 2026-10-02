<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Languages } from '@lucide/vue';
import { computed, ref } from 'vue';
import { update as updateLocale } from '@/routes/locale';

withDefaults(defineProps<{ tone?: 'light' | 'dark' }>(), { tone: 'light' });

const page = usePage();
const current = computed(() => page.props.locale);
const switching = ref(false);

// Short labels for the pill; full names for screen readers.
const options = [
    { code: 'bn', short: 'বাং', name: 'বাংলা' },
    { code: 'en', short: 'EN', name: 'English' },
] as const;

function choose(code: 'bn' | 'en') {
    if (code === current.value || switching.value) {
        return;
    }

    switching.value = true;
    router.post(
        updateLocale.url(),
        { locale: code },
        {
            preserveScroll: true,
            onFinish: () => (switching.value = false),
        },
    );
}
</script>

<template>
    <div
        class="inline-flex shrink-0 items-center gap-0.5 rounded-full p-0.5 text-xs font-semibold"
        :class="
            tone === 'dark'
                ? 'bg-white/10 ring-1 ring-white/15'
                : 'bg-muted ring-1 ring-border'
        "
        role="group"
        aria-label="Language · ভাষা"
        data-test="language-switcher"
    >
        <Languages
            class="mx-1 size-3.5 opacity-70"
            :class="tone === 'dark' ? 'text-white' : 'text-muted-foreground'"
            aria-hidden="true"
        />
        <button
            v-for="option in options"
            :key="option.code"
            type="button"
            :lang="option.code"
            :aria-pressed="current === option.code"
            :aria-label="option.name"
            :disabled="switching"
            class="rounded-full px-2.5 py-1 transition disabled:cursor-wait"
            :class="
                current === option.code
                    ? tone === 'dark'
                        ? 'bg-white text-slate-900'
                        : 'bg-background text-foreground shadow-sm'
                    : tone === 'dark'
                      ? 'text-white/70 hover:text-white'
                      : 'text-muted-foreground hover:text-foreground'
            "
            @click="choose(option.code)"
        >
            {{ option.short }}
        </button>
    </div>
</template>
