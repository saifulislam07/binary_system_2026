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

// Requirement labels and rank names arrive in English and are translated
// here with $t().
defineProps<{ rank: RankData }>();
</script>

<template>
    <section
        class="rank-progress rounded-xl border p-4"
        :aria-label="$t('Rank')"
    >
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <p class="text-sm text-muted-foreground">
                {{ $t('Current rank') }}
            </p>
            <p v-if="rank.next" class="text-sm text-muted-foreground">
                {{ $t('Next') }}:
                <span class="font-medium text-foreground">{{
                    $t(rank.next)
                }}</span>
            </p>
        </div>
        <p class="mt-1 text-2xl font-semibold" data-test="current-rank">
            {{ $t(rank.current) }}
        </p>

        <p v-if="!rank.next" class="mt-2 text-sm text-muted-foreground">
            {{ $t('Highest rank reached') }}
        </p>

        <ul v-else class="mt-3 grid gap-3" data-test="rank-requirements">
            <li v-for="req in rank.requirements" :key="req.label">
                <p class="text-sm">{{ $t(req.label) }}</p>
                <div
                    class="meter-track mt-1 h-2 overflow-hidden rounded-full"
                    role="progressbar"
                    :aria-valuenow="req.percent"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-label="
                        $t(':label: :have of :need', {
                            label: $t(req.label),
                            have: req.have,
                            need: req.need,
                        })
                    "
                >
                    <div
                        class="meter-fill h-full rounded-full"
                        :style="{ width: `${req.percent}%` }"
                    />
                </div>
                <p class="mt-1 text-right text-xs text-muted-foreground">
                    {{
                        $t(':have of :need', { have: req.have, need: req.need })
                    }}
                    <span v-if="req.met" class="text-foreground"
                        >· {{ $t('met') }} ✓</span
                    >
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
