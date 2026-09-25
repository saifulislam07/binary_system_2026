<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TreeNode from '@/components/TreeNode.vue';
import type { TreeNodeData } from '@/components/TreeNode.vue';
import { index as teamIndex } from '@/routes/team';

type LegCount = { total: number; active: number };

defineProps<{
    sponsor: { code: string | null; name: string } | null;
    legs: { left: LegCount; right: LegCount };
    directReferrals: number;
    tree: TreeNodeData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Team', href: teamIndex() }],
    },
});
</script>

<template>
    <Head title="Team" />

    <div class="flex flex-col gap-6 p-4">
        <h1 class="text-xl font-semibold">My team · আমার দল</h1>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border p-4" data-test="sponsor">
                <p class="text-sm text-muted-foreground">Sponsor · স্পনসর</p>
                <p v-if="sponsor" class="mt-1 font-semibold">
                    {{ sponsor.name }}
                </p>
                <p v-if="sponsor" class="text-xs text-muted-foreground">
                    {{ sponsor.code }}
                </p>
                <p v-else class="mt-1 text-muted-foreground">
                    None (top of the tree)
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-sm text-muted-foreground">Left team · বাম</p>
                <p class="mt-1 text-2xl font-semibold">{{ legs.left.total }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ legs.left.active }} active
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-sm text-muted-foreground">Right team · ডান</p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ legs.right.total }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ legs.right.active }} active
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-sm text-muted-foreground">
                    Personally sponsored · সরাসরি রেফারেল
                </p>
                <p class="mt-1 text-2xl font-semibold">{{ directReferrals }}</p>
            </div>
        </section>

        <section class="rounded-xl border p-4" aria-label="Binary tree">
            <p class="mb-4 text-sm text-muted-foreground">
                Click a member to expand or collapse their team. Deeper levels
                load as you go.
            </p>
            <div class="overflow-x-auto pb-2">
                <div class="flex min-w-max justify-center">
                    <TreeNode :node="tree" />
                </div>
            </div>
        </section>
    </div>
</template>
