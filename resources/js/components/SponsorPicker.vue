<script setup lang="ts">
import { LoaderCircle, Search, UserRound } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import { index as searchSponsors } from '@/routes/sponsors';

/**
 * Sponsor ID field with a search dropdown: type part of a member ID or a
 * name, then pick from the matching active members. The input itself is the
 * submitted `sponsor_code`, so typing a full ID still works.
 */
type Match = { code: string; name: string };

const props = withDefaults(
    defineProps<{
        id: string;
        name: string;
        placeholder?: string;
        tabindex?: number;
        autofocus?: boolean;
    }>(),
    { placeholder: '', tabindex: undefined, autofocus: false },
);

const model = defineModel<string>({ required: true });
const emit = defineEmits<{
    select: [match: Match];
    blur: [];
}>();

const open = ref(false);
const loading = ref(false);
const results = ref<Match[]>([]);
const active = ref(-1);
const searched = ref('');
let timer: ReturnType<typeof setTimeout> | undefined;
let pending: AbortController | null = null;

const listId = computed(() => `${props.id}-options`);

function longEnough(term: string): boolean {
    const digits = term.replace(/\D/g, '');

    return /^(?:[A-Za-z]{0,3}-?)?\d+$/.test(term)
        ? digits.length >= 2
        : term.length >= 3;
}

async function search(term: string) {
    pending?.abort();
    pending = new AbortController();
    loading.value = true;

    try {
        const response = await fetch(
            searchSponsors.url({ query: { q: term } }),
            {
                headers: { Accept: 'application/json' },
                signal: pending.signal,
            },
        );
        const body = (await response.json()) as { results?: Match[] };
        results.value = response.ok ? (body.results ?? []) : [];
        searched.value = term;
        active.value = results.value.length ? 0 : -1;
        open.value = true;
    } catch {
        // Aborted by a newer search, or offline: keep what's shown.
    } finally {
        loading.value = false;
    }
}

function onInput(event: Event) {
    const term = (event.target as HTMLInputElement).value.trim();
    model.value = (event.target as HTMLInputElement).value;
    clearTimeout(timer);

    if (!longEnough(term)) {
        results.value = [];
        open.value = false;

        return;
    }

    timer = setTimeout(() => void search(term), 250);
}

function choose(match: Match) {
    model.value = match.code;
    open.value = false;
    emit('select', match);
}

function onKeydown(event: KeyboardEvent) {
    if (!open.value || results.value.length === 0) {
        if (event.key === 'ArrowDown' && longEnough(model.value.trim())) {
            void search(model.value.trim());
        }

        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        active.value = (active.value + 1) % results.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        active.value =
            (active.value - 1 + results.value.length) % results.value.length;
    } else if (event.key === 'Enter' && active.value >= 0) {
        event.preventDefault();
        choose(results.value[active.value]);
    } else if (event.key === 'Escape') {
        open.value = false;
    }
}

function onBlur() {
    // Let a click on an option land first.
    setTimeout(() => {
        open.value = false;
        emit('blur');
    }, 150);
}

onBeforeUnmount(() => {
    clearTimeout(timer);
    pending?.abort();
});
</script>

<template>
    <div class="relative">
        <Search
            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
        />
        <input
            :id="id"
            :name="name"
            :value="model"
            type="text"
            role="combobox"
            autocomplete="off"
            spellcheck="false"
            :aria-expanded="open"
            :aria-controls="listId"
            aria-autocomplete="list"
            :aria-activedescendant="
                open && active >= 0 ? `${listId}-${active}` : undefined
            "
            :placeholder="placeholder"
            :tabindex="tabindex"
            :autofocus="autofocus"
            required
            class="h-11 w-full rounded-md border border-input bg-transparent pr-10 pl-9 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
            data-test="sponsor-search"
            @input="onInput"
            @keydown="onKeydown"
            @focus="open = results.length > 0"
            @blur="onBlur"
        />
        <LoaderCircle
            v-if="loading"
            class="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
            aria-hidden="true"
        />

        <ul
            v-show="open"
            :id="listId"
            role="listbox"
            :aria-label="$t('Matching sponsors')"
            class="absolute inset-x-0 top-full z-20 mt-1 max-h-72 overflow-y-auto rounded-xl border bg-popover p-1 text-popover-foreground shadow-lg"
        >
            <li
                v-for="(match, i) in results"
                :id="`${listId}-${i}`"
                :key="match.code"
                role="option"
                :aria-selected="i === active"
                class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2"
                :class="i === active ? 'bg-brand-soft text-brand' : ''"
                data-test="sponsor-option"
                @mousedown.prevent="choose(match)"
                @mousemove="active = i"
            >
                <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground"
                >
                    <UserRound class="size-4" aria-hidden="true" />
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-semibold tabular-nums">{{
                        match.code
                    }}</span>
                    <span
                        class="block truncate text-xs text-muted-foreground"
                        >{{ match.name }}</span
                    >
                </span>
            </li>
            <li
                v-if="results.length === 0 && !loading"
                class="px-3 py-3 text-sm text-muted-foreground"
                role="presentation"
            >
                {{
                    $t('No active member matches “:term”.', { term: searched })
                }}
            </li>
        </ul>
    </div>
</template>
