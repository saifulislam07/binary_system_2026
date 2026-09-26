<script setup lang="ts">
export type RankRequirement = {
    label: string;
    have: string;
    need: string;
    percent: number;
    met: boolean;
};

export type RankData = {
    current: string;
    next: string | null;
    requirements: RankRequirement[];
};

defineProps<{ rank: RankData }>();

const labels: Record<string, string> = {
    'Personal sales': 'Personal sales · ব্যক্তিগত বিক্রয়',
    'Team sales': 'Team sales · দলের বিক্রয়',
    'Active team': 'Active team · সক্রিয় সদস্য',
};
</script>

<template>
    <section class="rank-progress rounded-xl border p-4" aria-label="Rank">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <p class="text-sm text-muted-foreground">Current rank · র‍্যাংক</p>
            <p v-if="rank.next" class="text-sm text-muted-foreground">
                Next:
                <span class="font-medium text-foreground">{{ rank.next }}</span>
            </p>
        </div>
        <p class="mt-1 text-2xl font-semibold" data-test="current-rank">
            {{ rank.current }}
        </p>

        <p v-if="!rank.next" class="mt-2 text-sm text-muted-foreground">
            Highest rank reached · সর্বোচ্চ র‍্যাংক
        </p>

        <ul v-else class="mt-3 grid gap-3" data-test="rank-requirements">
            <li v-for="req in rank.requirements" :key="req.label">
                <p class="text-sm">{{ labels[req.label] ?? req.label }}</p>
                <div
                    class="meter-track mt-1 h-2 overflow-hidden rounded-full"
                    role="progressbar"
                    :aria-valuenow="req.percent"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-label="`${req.label}: ${req.have} of ${req.need}`"
                >
                    <div
                        class="meter-fill h-full rounded-full"
                        :style="{ width: `${req.percent}%` }"
                    />
                </div>
                <p class="mt-1 text-right text-xs text-muted-foreground">
                    {{ req.have }} of {{ req.need }}
                    <span v-if="req.met" class="text-foreground">· met ✓</span>
                </p>
            </li>
        </ul>
    </section>
</template>

<style>
/* Meter: fill in the accent, track a lighter step of the same ramp (dataviz spec).
   Unscoped so the app's .dark class can override the tokens. */
.rank-progress {
    --meter-fill: #2a78d6;
    --meter-track: #cde2fb;
}

.dark .rank-progress {
    --meter-fill: #3987e5;
    --meter-track: #184f95;
}

.rank-progress .meter-track {
    background: var(--meter-track);
}

.rank-progress .meter-fill {
    background: var(--meter-fill);
}
</style>
