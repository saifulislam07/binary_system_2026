<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

export type IncomePoint = {
    label: string;
    from: string;
    to: string;
    amount: number; // whole taka, may be negative after reversals
    formatted: string;
};

const props = defineProps<{ points: IncomePoint[]; title: string }>();

// Geometry in real CSS pixels: the SVG is drawn at its container's measured
// width (never scaled), so text stays 11px and bars stay ≤ 24px wide.
const container = ref<HTMLElement | null>(null);
const width = ref(0); // measured on mount; nothing is drawn until then
const W = computed(() => Math.max(280, width.value));
const H = 220;
const M = { top: 20, right: 12, bottom: 28, left: 56 };
const plotW = computed(() => W.value - M.left - M.right);
const plotH = H - M.top - M.bottom;
const BAR_MAX = 24;
const RADIUS = 4;

let observer: ResizeObserver | null = null;

onMounted(() => {
    if (container.value) {
        width.value = container.value.clientWidth;
        observer = new ResizeObserver(([entry]) => {
            width.value = entry.contentRect.width;
        });
        observer.observe(container.value);
    }
});

onBeforeUnmount(() => observer?.disconnect());

function niceStep(raw: number): number {
    if (raw <= 0) {
        return 1;
    }

    const pow = 10 ** Math.floor(Math.log10(raw));
    const n = raw / pow;

    return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * pow;
}

const scale = computed(() => {
    const values = props.points.map((p) => p.amount);
    const max = Math.max(0, ...values);
    const min = Math.min(0, ...values);
    const step = niceStep((max - min) / 3 || 1);
    const top = Math.ceil(max / step) * step || step;
    const bottom = Math.floor(min / step) * step;
    const ticks: number[] = [];

    for (let v = bottom; v <= top + step / 2; v += step) {
        ticks.push(v);
    }

    const y = (v: number) => M.top + ((top - v) / (top - bottom)) * plotH;

    return { ticks, y, zero: y(0) };
});

const band = computed(() => plotW.value / Math.max(1, props.points.length));
const barW = computed(() => Math.min(BAR_MAX, band.value * 0.5));

/** Column path: 4px rounded data-end, square at the baseline. */
function barPath(i: number, value: number): string {
    const { y, zero } = scale.value;
    const w = barW.value;
    const x = M.left + band.value * i + (band.value - w) / 2;
    const end = y(value);
    const h = Math.abs(zero - end);

    if (h < 0.5) {
        return '';
    }

    const r = Math.min(RADIUS, h, w / 2);

    if (value >= 0) {
        return `M${x},${zero} V${end + r} Q${x},${end} ${x + r},${end} H${x + w - r} Q${x + w},${end} ${x + w},${end + r} V${zero} Z`;
    }

    return `M${x},${zero} V${end - r} Q${x},${end} ${x + r},${end} H${x + w - r} Q${x + w},${end} ${x + w},${end - r} V${zero} Z`;
}

const compact = new Intl.NumberFormat('en-BD', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

const hovered = ref<number | null>(null);
const lastIndex = computed(() => props.points.length - 1);
</script>

<template>
    <figure class="income-chart w-full min-w-0 rounded-xl border p-4">
        <figcaption class="mb-2 text-sm font-medium">{{ title }}</figcaption>

        <div ref="container" class="relative w-full min-w-0 overflow-hidden">
            <svg
                v-if="width > 0"
                :width="W"
                :height="H"
                :viewBox="`0 0 ${W} ${H}`"
                class="block"
                role="img"
                :aria-label="`${title}: ${points.map((p) => `${p.label} ${p.formatted}`).join(', ')}`"
            >
                <!-- Recessive hairline grid + y ticks -->
                <g>
                    <template v-for="t in scale.ticks" :key="t">
                        <line
                            :x1="M.left"
                            :x2="W - M.right"
                            :y1="scale.y(t)"
                            :y2="scale.y(t)"
                            :class="t === 0 ? 'baseline' : 'grid'"
                        />
                        <text
                            :x="M.left - 8"
                            :y="scale.y(t)"
                            text-anchor="end"
                            dominant-baseline="middle"
                            class="tick"
                        >
                            ৳{{ compact.format(t) }}
                        </text>
                    </template>
                </g>

                <g v-for="(p, i) in points" :key="p.from">
                    <path :d="barPath(i, p.amount)" class="bar" />
                    <!-- X labels -->
                    <text
                        :x="M.left + band * i + band / 2"
                        :y="H - 8"
                        text-anchor="middle"
                        class="tick"
                    >
                        {{ p.label }}
                    </text>
                    <!-- Selective direct label: the latest period only -->
                    <text
                        v-if="i === lastIndex && p.amount !== 0"
                        :x="M.left + band * i + band / 2"
                        :y="scale.y(p.amount) + (p.amount >= 0 ? -6 : 14)"
                        text-anchor="middle"
                        class="value"
                    >
                        {{ p.formatted }}
                    </text>
                    <!-- Hit target: the whole band, bigger than the mark -->
                    <rect
                        :x="M.left + band * i"
                        :y="M.top"
                        :width="band"
                        :height="plotH"
                        fill="transparent"
                        tabindex="0"
                        :aria-label="`${p.label}: ${p.formatted}`"
                        class="hit"
                        @pointerenter="hovered = i"
                        @pointerleave="hovered = null"
                        @focus="hovered = i"
                        @blur="hovered = null"
                    />
                </g>
            </svg>

            <div
                v-if="hovered !== null && points[hovered]"
                class="tooltip"
                :style="{
                    left: `${((M.left + band * hovered + band / 2) / W) * 100}%`,
                }"
                role="status"
            >
                <strong>{{ points[hovered].formatted }}</strong>
                <span>{{
                    points[hovered].from === points[hovered].to
                        ? points[hovered].from
                        : `${points[hovered].from} – ${points[hovered].to}`
                }}</span>
            </div>
        </div>

        <details class="mt-3 text-sm">
            <summary class="cursor-pointer text-muted-foreground">
                Show as table
            </summary>
            <table class="mt-2 w-full">
                <thead class="text-left text-muted-foreground">
                    <tr>
                        <th class="py-1 font-medium">Period</th>
                        <th class="py-1 text-right font-medium">Net income</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in points" :key="p.from" class="border-t">
                        <td class="py-1">
                            {{
                                p.from === p.to ? p.from : `${p.from} – ${p.to}`
                            }}
                        </td>
                        <td class="py-1 text-right tabular-nums">
                            {{ p.formatted }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </details>
    </figure>
</template>

<style>
/* Colour tokens (unscoped so the app-level .dark class can override them). */
.income-chart {
    --series-1: #2a78d6;
    --grid: #e1e0d9;
    --axis: #c3c2b7;
    --muted: #898781;
    --text-primary: #0b0b0b;
    --tooltip-bg: #fcfcfb;
}

.dark .income-chart {
    --series-1: #3987e5;
    --grid: #2c2c2a;
    --axis: #383835;
    --muted: #898781;
    --text-primary: #ffffff;
    --tooltip-bg: #1a1a19;
}
</style>

<style scoped>
.bar {
    fill: var(--series-1);
}

.grid {
    stroke: var(--grid);
    stroke-width: 1;
}

.baseline {
    stroke: var(--axis);
    stroke-width: 1;
}

.tick {
    fill: var(--muted);
    font-size: 11px;
    font-variant-numeric: tabular-nums;
}

.value {
    fill: var(--text-primary);
    font-size: 11px;
    font-weight: 600;
}

.hit {
    cursor: default;
    outline: none;
}

.hit:focus-visible {
    stroke: var(--series-1);
    stroke-width: 1;
}

.tooltip {
    position: absolute;
    top: 0;
    transform: translateX(-50%);
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 6px 10px;
    border-radius: 8px;
    border: 1px solid var(--grid);
    background: var(--tooltip-bg);
    color: var(--text-primary);
    font-size: 12px;
    white-space: nowrap;
    pointer-events: none;
    box-shadow: 0 2px 8px rgb(0 0 0 / 0.08);
}

.tooltip span {
    color: var(--muted);
}
</style>
