<script setup lang="ts">
import { ref } from 'vue';
import { tree as treeRoute } from '@/routes/team';

export type TreeNodeData = {
    code: string | null;
    name: string;
    status: string;
    active: boolean;
    package: string | null;
    side: string | null;
    leftBv: number;
    rightBv: number;
    teamBv: number;
    hasLeft: boolean;
    hasRight: boolean;
    children: { left: TreeNodeData | null; right: TreeNodeData | null } | null;
};

const props = defineProps<{ node: TreeNodeData; depth?: number }>();

const node = ref<TreeNodeData>(props.node);
const open = ref(props.node.children !== null);
const loading = ref(false);
const error = ref<string | null>(null);
const expandable = props.node.hasLeft || props.node.hasRight;

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
            error.value = 'Could not load this branch.';
            loading.value = false;

            return;
        }

        loading.value = false;
    }

    open.value = true;
}

const bv = new Intl.NumberFormat('en-BD');
</script>

<template>
    <div class="flex flex-col items-center" data-test="tree-node">
        <button
            type="button"
            :class="[
                'w-44 rounded-lg border bg-card p-2 text-left text-xs shadow-xs transition',
                node.active
                    ? 'border-border'
                    : 'border-dashed border-muted-foreground/40 opacity-70',
                expandable ? 'hover:border-primary' : 'cursor-default',
            ]"
            :aria-expanded="expandable ? open : undefined"
            :disabled="!expandable || loading"
            @click="toggle"
        >
            <span class="flex items-center justify-between gap-2">
                <span class="font-semibold">{{ node.code }}</span>
                <span
                    :class="[
                        'rounded-full px-1.5 text-[10px]',
                        node.active
                            ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200'
                            : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
                    ]"
                    >{{ node.status }}</span
                >
            </span>
            <span class="block truncate">{{ node.name }}</span>
            <span class="block text-muted-foreground"
                >{{ node.package ?? '—' }} · team
                {{ bv.format(node.teamBv) }} BV</span
            >
            <span class="block text-muted-foreground"
                >L {{ bv.format(node.leftBv) }} · R
                {{ bv.format(node.rightBv) }}</span
            >
            <span
                v-if="expandable"
                class="mt-1 block text-center text-[10px] text-muted-foreground"
                >{{
                    loading ? 'Loading…' : open ? '▲ collapse' : '▼ expand'
                }}</span
            >
        </button>
        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>

        <div
            v-if="open && node.children"
            class="mt-3 flex gap-4 border-t border-border pt-3"
        >
            <div class="flex flex-col items-center">
                <span class="mb-1 text-[10px] text-muted-foreground"
                    >Left · বাম</span
                >
                <TreeNode
                    v-if="node.children.left"
                    :node="node.children.left"
                    :depth="(depth ?? 0) + 1"
                />
                <div
                    v-else
                    class="w-44 rounded-lg border border-dashed p-2 text-center text-xs text-muted-foreground"
                >
                    Empty slot
                </div>
            </div>
            <div class="flex flex-col items-center">
                <span class="mb-1 text-[10px] text-muted-foreground"
                    >Right · ডান</span
                >
                <TreeNode
                    v-if="node.children.right"
                    :node="node.children.right"
                    :depth="(depth ?? 0) + 1"
                />
                <div
                    v-else
                    class="w-44 rounded-lg border border-dashed p-2 text-center text-xs text-muted-foreground"
                >
                    Empty slot
                </div>
            </div>
        </div>
    </div>
</template>
