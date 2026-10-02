<script setup lang="ts">
import { Minus, Plus, UserPlus } from '@lucide/vue';
import { computed, inject, ref } from 'vue';
import { t } from '@/lib/i18n';
import { treeContextKey } from '@/lib/tree';
import type { TreeNodeData } from '@/lib/tree';
import { tree as treeRoute } from '@/routes/team';

export type { TreeNodeData } from '@/lib/tree';

const props = defineProps<{ node: TreeNodeData; depth?: number }>();

const context = inject(treeContextKey, null);

const node = ref<TreeNodeData>(props.node);
const open = ref(props.node.children !== null);
const loading = ref(false);
const error = ref<string | null>(null);
const expandable = computed(() => node.value.hasLeft || node.value.hasRight);
const selected = computed(
    () => context?.selected.value?.code === node.value.code,
);

async function toggle() {
    if (open.value) {
        open.value = false;

        return;
    }

    if (node.value.children === null && node.value.code) {
        loading.value = true;
        error.value = null;

        try {
            const response = await fetch(
                treeRoute.url(node.value.code, { query: { depth: 2 } }),
                { headers: { Accept: 'application/json' } },
            );

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            node.value = await response.json();
        } catch {
            error.value = t('Could not load this branch.');
            loading.value = false;

            return;
        }

        loading.value = false;
    }

    open.value = true;
}

const bv = new Intl.NumberFormat('en-BD');

const initials = computed(() =>
    node.value.name
        .replace(/^(mr|mrs|ms|miss|dr|prof)\.?\s+/i, '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join(''),
);

// A stable avatar color per member.
const palettes = [
    ['#2a78d6', '#6ea8f0'],
    ['#0f9d7a', '#4fd1a5'],
    ['#7c4dff', '#b39bff'],
    ['#e0672b', '#f7a26b'],
    ['#c2377b', '#ef7fb4'],
    ['#0e7490', '#4cc3dc'],
];
const avatar = computed(() => {
    const code = node.value.code ?? node.value.name;
    let hash = 0;

    for (const char of code) {
        hash = (hash * 31 + char.charCodeAt(0)) >>> 0;
    }

    const [a, b] = palettes[hash % palettes.length];

    return { background: `linear-gradient(135deg, ${a}, ${b})` };
});

const leftShare = computed(() => {
    const total = node.value.leftBv + node.value.rightBv;

    return total === 0 ? 50 : Math.round((node.value.leftBv / total) * 100);
});
</script>

<template>
    <div class="bt-node" data-test="tree-node">
        <button
            type="button"
            class="bt-card"
            :class="{
                'is-selected': selected,
                'is-inactive': !node.active,
                'is-root': (depth ?? 0) === 0,
            }"
            :data-status="node.status"
            :aria-pressed="selected"
            :aria-label="`${node.name}, ${node.code}, ${$t(node.status)}`"
            @click="context?.select(node)"
        >
            <span class="flex items-center gap-2.5">
                <span class="bt-avatar" :style="avatar" aria-hidden="true">{{
                    initials
                }}</span>
                <span class="min-w-0 text-left">
                    <span class="block truncate text-[13px] font-semibold">{{
                        node.name
                    }}</span>
                    <span
                        class="block text-[11px] text-muted-foreground tabular-nums"
                        >{{ node.code }}</span
                    >
                </span>
            </span>
            <span class="mt-2 flex flex-wrap gap-1">
                <span v-if="node.package" class="bt-tag is-package">{{
                    node.package
                }}</span>
                <span
                    v-if="node.rank && node.rank !== 'Member'"
                    class="bt-tag is-rank"
                    >{{ $t(node.rank) }}</span
                >
                <span v-if="!node.active" class="bt-tag">{{
                    $t(node.status)
                }}</span>
            </span>
            <span class="mt-2.5 block text-[10.5px] text-muted-foreground">
                <span class="flex justify-between tabular-nums">
                    <span>{{ $t('L') }} {{ bv.format(node.leftBv) }}</span>
                    <span>{{ $t('R') }} {{ bv.format(node.rightBv) }}</span>
                </span>
                <span class="bt-bar" aria-hidden="true">
                    <span class="l" :style="{ width: `${leftShare}%` }" />
                    <span class="r" :style="{ width: `${100 - leftShare}%` }" />
                </span>
            </span>
        </button>

        <button
            v-if="expandable"
            type="button"
            class="bt-toggle"
            :aria-expanded="open"
            :aria-label="open ? $t('Collapse team') : $t('Expand team')"
            :disabled="loading"
            @click="toggle"
        >
            <span
                v-if="loading"
                class="size-3 animate-spin rounded-full border-2 border-current border-t-transparent"
                aria-hidden="true"
            />
            <component
                :is="open ? Minus : Plus"
                v-else
                class="size-3.5"
                aria-hidden="true"
            />
        </button>
        <p v-if="error" class="mt-2 text-xs text-red-600" role="alert">
            {{ error }}
        </p>

        <div v-if="open && node.children" class="bt-children">
            <div
                v-for="side in ['left', 'right'] as const"
                :key="side"
                class="bt-branch"
                :class="side === 'left' ? 'is-left' : 'is-right'"
            >
                <span class="bt-side">{{
                    side === 'left' ? $t('Left') : $t('Right')
                }}</span>
                <TreeNode
                    v-if="node.children[side]"
                    :node="node.children[side]!"
                    :depth="(depth ?? 0) + 1"
                />
                <div v-else class="bt-empty">
                    <UserPlus
                        class="mx-auto mb-1 size-5 opacity-60"
                        aria-hidden="true"
                    />
                    {{ $t('Vacant') }}
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* Binary tree (member app). Unscoped so the app's .dark tokens apply. */
.bt-node {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.bt-card {
    position: relative;
    width: 12.5rem;
    padding: 0.75rem 0.75rem 1.05rem;
    border: 1px solid var(--border);
    border-radius: 1rem;
    background: var(--card);
    color: var(--card-foreground);
    text-align: left;
    box-shadow:
        0 1px 2px rgb(15 23 42 / 0.05),
        0 1px 3px rgb(15 23 42 / 0.06);
    transition:
        box-shadow 0.15s ease,
        border-color 0.15s ease,
        transform 0.15s ease;
    cursor: pointer;
}

.bt-card::before {
    content: '';
    position: absolute;
    inset: 0 0 auto;
    height: 3px;
    border-radius: 1rem 1rem 0 0;
    background: var(--bt-accent, var(--muted-foreground));
}

.bt-card:hover {
    border-color: color-mix(in srgb, var(--brand) 50%, var(--border));
    box-shadow: 0 12px 28px -14px rgb(15 23 42 / 0.35);
    transform: translateY(-1px);
}

.bt-card:focus-visible {
    outline: 2px solid var(--brand);
    outline-offset: 2px;
}

.bt-card.is-selected {
    border-color: var(--brand);
    box-shadow:
        0 0 0 4px color-mix(in srgb, var(--brand) 18%, transparent),
        0 12px 28px -14px rgb(15 23 42 / 0.35);
}

.bt-card.is-inactive {
    background: var(--muted);
}

.bt-card[data-status='active'] {
    --bt-accent: #16a34a;
}

.bt-card[data-status='pending'] {
    --bt-accent: #d97706;
}

.bt-card[data-status='suspended'],
.bt-card[data-status='terminated'] {
    --bt-accent: #dc2626;
}

.bt-avatar {
    position: relative;
    display: inline-flex;
    flex: none;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 9999px;
    color: #fff;
    font-size: 0.78rem;
    font-weight: 700;
}

.bt-avatar::after {
    content: '';
    position: absolute;
    right: -1px;
    bottom: -1px;
    width: 0.7rem;
    height: 0.7rem;
    border: 2px solid var(--card);
    border-radius: 9999px;
    background: var(--bt-accent, var(--muted-foreground));
}

.bt-tag {
    padding: 0 0.45rem;
    border-radius: 9999px;
    background: var(--muted);
    color: var(--muted-foreground);
    font-size: 0.66rem;
    font-weight: 600;
    line-height: 1.1rem;
    text-transform: capitalize;
}

.bt-tag.is-package {
    background: var(--brand-soft);
    color: var(--brand);
}

.bt-tag.is-rank {
    background: rgb(245 158 11 / 0.15);
    color: #b45309;
}

.dark .bt-tag.is-rank {
    color: #fcd34d;
}

.bt-bar {
    display: flex;
    height: 0.3rem;
    margin-top: 0.25rem;
    overflow: hidden;
    border-radius: 9999px;
    background: var(--muted);
}

.bt-bar .l {
    background: var(--brand);
}

.bt-bar .r {
    background: color-mix(in srgb, var(--brand) 40%, transparent);
}

.bt-toggle {
    position: relative;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.6rem;
    height: 1.6rem;
    margin-top: -0.8rem;
    border: 1px solid var(--border);
    border-radius: 9999px;
    background: var(--card);
    color: var(--muted-foreground);
    box-shadow: 0 1px 3px rgb(15 23 42 / 0.12);
    cursor: pointer;
}

.bt-toggle:hover {
    border-color: var(--brand);
    color: var(--brand);
}

/* Connectors: a stem down from the parent, a bar across, a drop to each child. */
.bt-children {
    --bt-gap: 1.75rem;
    --bt-line: color-mix(in srgb, var(--muted-foreground) 35%, transparent);
    position: relative;
    display: flex;
    gap: var(--bt-gap);
    padding-top: 2.1rem;
}

.bt-children::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    height: 1.05rem;
    border-left: 2px solid var(--bt-line);
}

.bt-branch {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.bt-branch::before {
    content: '';
    position: absolute;
    top: -1.05rem;
    width: calc(50% + var(--bt-gap) / 2);
    height: 1.05rem;
    border-top: 2px solid var(--bt-line);
}

.bt-branch.is-left::before {
    right: calc(var(--bt-gap) / -2);
    border-left: 2px solid var(--bt-line);
    border-top-left-radius: 0.75rem;
}

.bt-branch.is-right::before {
    left: calc(var(--bt-gap) / -2);
    border-right: 2px solid var(--bt-line);
    border-top-right-radius: 0.75rem;
}

.bt-side {
    margin-bottom: 0.4rem;
    padding: 0 0.45rem;
    border-radius: 9999px;
    background: var(--background);
    color: var(--muted-foreground);
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.bt-empty {
    width: 12.5rem;
    padding: 1.1rem 0.6rem;
    border: 2px dashed var(--border);
    border-radius: 1rem;
    color: var(--muted-foreground);
    font-size: 0.78rem;
    text-align: center;
}

@media (prefers-reduced-motion: reduce) {
    .bt-card {
        transition: none;
    }
}
</style>
