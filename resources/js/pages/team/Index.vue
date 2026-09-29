<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ArrowUpToLine,
    Maximize,
    Minus,
    Plus,
    Scan,
    Search,
    UserRound,
    Users,
    X,
} from '@lucide/vue';
import { computed, nextTick, onMounted, provide, ref, shallowRef } from 'vue';
import TreeNode from '@/components/TreeNode.vue';
import { treeContextKey } from '@/lib/tree';
import type { TreeNodeData } from '@/lib/tree';
import { index as teamIndex, tree as treeRoute } from '@/routes/team';

type LegCount = { total: number; active: number };

const props = defineProps<{
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

const bv = new Intl.NumberFormat('en-BD');

/* ---------- Which member the tree starts from ---------- */

const root = shallowRef<TreeNodeData>(props.tree);
const trail = ref<TreeNodeData[]>([]); // earlier roots, for "Back"
const selected = ref<TreeNodeData | null>(null);
const finding = ref(false);
const findCode = ref('');
const findError = ref<string | null>(null);

provide(treeContextKey, {
    selected,
    select: (node) =>
        (selected.value = selected.value?.code === node.code ? null : node),
});

async function loadRoot(code: string): Promise<TreeNodeData | null> {
    const response = await fetch(treeRoute.url(code, { query: { depth: 2 } }), {
        headers: { Accept: 'application/json' },
    });

    if (response.status === 403 || response.status === 404) {
        findError.value = `${code} isn't in your team · আপনার দলে নেই`;

        return null;
    }

    if (!response.ok) {
        findError.value = 'Could not load the tree. Please try again.';

        return null;
    }

    return (await response.json()) as TreeNodeData;
}

async function showFrom(code: string | null) {
    if (!code || code === root.value.code) {
        return;
    }

    finding.value = true;
    findError.value = null;

    try {
        const data = await loadRoot(code);

        if (data) {
            trail.value.push(root.value);
            root.value = data;
            selected.value = data;
            findCode.value = '';
            await nextTick();
            center();
        }
    } finally {
        finding.value = false;
    }
}

function find() {
    const code = findCode.value.trim().toUpperCase();

    if (code) {
        void showFrom(code);
    }
}

async function back() {
    const previous = trail.value.pop();

    if (previous) {
        root.value = previous;
        selected.value = null;
        await nextTick();
        center();
    }
}

async function backToMe() {
    trail.value = [];
    root.value = props.tree;
    selected.value = null;
    await nextTick();
    center();
}

/* ---------- Pan & zoom ---------- */

const viewport = ref<HTMLElement | null>(null);
const canvas = ref<HTMLElement | null>(null);
const frame = ref<HTMLElement | null>(null);
const scale = ref(1);
const x = ref(0);
const y = ref(0);
const panning = ref(false);
let drag: {
    id: number;
    startX: number;
    startY: number;
    x: number;
    y: number;
} | null = null;

const transform = computed(
    () => `translate(${x.value}px, ${y.value}px) scale(${scale.value})`,
);

function clampScale(value: number) {
    return Math.min(1.6, Math.max(0.3, value));
}

function zoomTo(next: number, originX?: number, originY?: number) {
    const box = viewport.value?.getBoundingClientRect();

    if (!box) {
        return;
    }

    const ox = originX ?? box.width / 2;
    const oy = originY ?? box.height / 2;
    const target = clampScale(next);
    const ratio = target / scale.value;

    x.value = ox - (ox - x.value) * ratio;
    y.value = oy - (oy - y.value) * ratio;
    scale.value = target;
}

function center() {
    const box = viewport.value?.getBoundingClientRect();
    const width = canvas.value?.offsetWidth ?? 0;

    if (box) {
        x.value = (box.width - width * scale.value) / 2;
        y.value = 24;
    }
}

function fit() {
    const box = viewport.value?.getBoundingClientRect();
    const width = canvas.value?.offsetWidth ?? 0;
    const height = canvas.value?.offsetHeight ?? 0;

    if (!box || !width || !height) {
        return;
    }

    scale.value = clampScale(
        Math.min(1, (box.width - 32) / width, (box.height - 32) / height),
    );
    x.value = (box.width - width * scale.value) / 2;
    y.value = Math.max(16, (box.height - height * scale.value) / 2);
}

function onPointerDown(event: PointerEvent) {
    if (
        (event.target as HTMLElement).closest('button, a, input, [data-panel]')
    ) {
        return;
    }

    drag = {
        id: event.pointerId,
        startX: event.clientX,
        startY: event.clientY,
        x: x.value,
        y: y.value,
    };
    panning.value = true;
    viewport.value?.setPointerCapture(event.pointerId);
}

function onPointerMove(event: PointerEvent) {
    if (!drag || drag.id !== event.pointerId) {
        return;
    }

    x.value = drag.x + event.clientX - drag.startX;
    y.value = drag.y + event.clientY - drag.startY;
}

function onPointerUp(event: PointerEvent) {
    if (drag?.id === event.pointerId) {
        drag = null;
        panning.value = false;
    }
}

// Ctrl/⌘ + wheel zooms at the cursor; a plain wheel still scrolls the page.
function onWheel(event: WheelEvent) {
    if (!(event.ctrlKey || event.metaKey)) {
        return;
    }

    event.preventDefault();
    const box = viewport.value!.getBoundingClientRect();
    zoomTo(
        scale.value * (event.deltaY < 0 ? 1.1 : 0.9),
        event.clientX - box.left,
        event.clientY - box.top,
    );
}

function onKey(event: KeyboardEvent) {
    const step = 60;
    const moves: Record<string, [number, number]> = {
        ArrowLeft: [step, 0],
        ArrowRight: [-step, 0],
        ArrowUp: [0, step],
        ArrowDown: [0, -step],
    };

    if (event.target !== viewport.value) {
        return;
    }

    if (moves[event.key]) {
        event.preventDefault();
        x.value += moves[event.key][0];
        y.value += moves[event.key][1];
    } else if (event.key === '+' || event.key === '=') {
        zoomTo(scale.value * 1.15);
    } else if (event.key === '-') {
        zoomTo(scale.value / 1.15);
    }
}

function fullscreen() {
    if (document.fullscreenElement) {
        void document.exitFullscreen();
    } else {
        void frame.value?.requestFullscreen?.();
    }
}

onMounted(center);

/* ---------- Summary ---------- */

const totalTeam = computed(
    () => props.legs.left.total + props.legs.right.total,
);
const leftPct = computed(() =>
    totalTeam.value === 0
        ? 50
        : Math.round((props.legs.left.total / totalTeam.value) * 100),
);

const legend = [
    { label: 'Active · সক্রিয়', color: '#16a34a' },
    { label: 'Pending · অপেক্ষমাণ', color: '#d97706' },
    { label: 'Suspended · স্থগিত', color: '#dc2626' },
];
</script>

<template>
    <Head title="Team" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    My team · আমার দল
                </h1>
                <p class="text-sm text-muted-foreground">
                    Your placement tree: every member below you, on your left
                    and right.
                </p>
            </div>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border bg-card p-5" data-test="sponsor">
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-xl bg-brand-soft text-brand"
                    >
                        <UserRound class="size-5" aria-hidden="true" />
                    </span>
                    <p class="text-sm text-muted-foreground">
                        Sponsor · স্পনসর
                    </p>
                </div>
                <template v-if="sponsor">
                    <p class="mt-3 truncate font-semibold">
                        {{ sponsor.name }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ sponsor.code }}
                    </p>
                </template>
                <p v-else class="mt-3 text-muted-foreground">
                    None (top of the tree)
                </p>
            </div>

            <div class="rounded-2xl border bg-card p-5 sm:col-span-2">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Left team · বাম
                        </p>
                        <p class="mt-1 text-3xl font-semibold tabular-nums">
                            {{ legs.left.total }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ legs.left.active }} active
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-muted-foreground">
                            Right team · ডান
                        </p>
                        <p class="mt-1 text-3xl font-semibold tabular-nums">
                            {{ legs.right.total }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ legs.right.active }} active
                        </p>
                    </div>
                </div>
                <div
                    class="mt-4 flex h-2 overflow-hidden rounded-full bg-muted"
                    role="img"
                    :aria-label="`Left ${legs.left.total}, right ${legs.right.total}`"
                >
                    <span class="bg-brand" :style="{ width: `${leftPct}%` }" />
                    <span
                        class="bg-brand/40"
                        :style="{ width: `${100 - leftPct}%` }"
                    />
                </div>
            </div>

            <div class="rounded-2xl border bg-card p-5">
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                    >
                        <Users class="size-5" aria-hidden="true" />
                    </span>
                    <p class="text-sm text-muted-foreground">
                        Personally sponsored · সরাসরি রেফারেল
                    </p>
                </div>
                <p class="mt-3 text-3xl font-semibold tabular-nums">
                    {{ directReferrals }}
                </p>
            </div>
        </section>

        <section
            ref="frame"
            class="flex flex-col overflow-hidden rounded-2xl border bg-card"
            aria-label="Binary tree"
        >
            <!-- Toolbar -->
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3"
            >
                <form
                    class="flex min-w-0 items-center gap-2"
                    role="search"
                    @submit.prevent="find"
                >
                    <label for="find-member" class="sr-only"
                        >Find a member by code</label
                    >
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <input
                            id="find-member"
                            v-model="findCode"
                            placeholder="Find member · MBR-100004"
                            class="h-9 w-56 rounded-lg border border-input bg-background pr-3 pl-8 text-sm uppercase outline-none placeholder:normal-case focus-visible:border-brand focus-visible:ring-4 focus-visible:ring-brand/15"
                        />
                    </div>
                    <button
                        type="submit"
                        class="h-9 rounded-lg bg-brand px-3 text-sm font-semibold text-white hover:bg-brand-strong disabled:opacity-60"
                        :disabled="finding"
                    >
                        Show
                    </button>
                    <button
                        v-if="trail.length"
                        type="button"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg border px-3 text-sm font-medium hover:bg-muted"
                        @click="back"
                    >
                        Back
                    </button>
                    <button
                        v-if="trail.length"
                        type="button"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg border px-3 text-sm font-medium hover:bg-muted"
                        @click="backToMe"
                    >
                        <ArrowUpToLine class="size-4" aria-hidden="true" />
                        Back to me
                    </button>
                </form>

                <div
                    class="flex items-center gap-1"
                    role="group"
                    aria-label="Zoom"
                >
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-lg border hover:bg-muted"
                        aria-label="Zoom out"
                        @click="zoomTo(scale / 1.2)"
                    >
                        <Minus class="size-4" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="h-9 min-w-14 rounded-lg px-2 text-sm font-medium tabular-nums hover:bg-muted"
                        aria-label="Reset zoom"
                        @click="((scale = 1), center())"
                    >
                        {{ Math.round(scale * 100) }}%
                    </button>
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-lg border hover:bg-muted"
                        aria-label="Zoom in"
                        @click="zoomTo(scale * 1.2)"
                    >
                        <Plus class="size-4" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="ml-1 flex size-9 items-center justify-center rounded-lg border hover:bg-muted"
                        aria-label="Fit to screen"
                        title="Fit to screen"
                        @click="fit"
                    >
                        <Scan class="size-4" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-lg border hover:bg-muted"
                        aria-label="Full screen"
                        title="Full screen"
                        @click="fullscreen"
                    >
                        <Maximize class="size-4" aria-hidden="true" />
                    </button>
                </div>
            </div>
            <p
                v-if="findError"
                class="border-b bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100"
                role="alert"
            >
                {{ findError }}
            </p>

            <!-- Canvas -->
            <div
                ref="viewport"
                class="relative min-h-160 flex-1 touch-none overflow-hidden bg-[radial-gradient(circle,color-mix(in_srgb,var(--muted-foreground)_22%,transparent)_1px,transparent_1.5px)] bg-size-[22px_22px] outline-none select-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-inset"
                :class="panning ? 'cursor-grabbing' : 'cursor-grab'"
                tabindex="0"
                aria-label="Tree canvas. Drag or use the arrow keys to move, plus and minus to zoom."
                @pointerdown="onPointerDown"
                @pointermove="onPointerMove"
                @pointerup="onPointerUp"
                @pointercancel="onPointerUp"
                @wheel="onWheel"
                @keydown="onKey"
            >
                <div
                    ref="canvas"
                    class="absolute top-0 left-0 origin-top-left px-12 pt-4 pb-16"
                    :style="{ transform }"
                >
                    <TreeNode :key="root.code ?? ''" :node="root" />
                </div>

                <!-- Selected member -->
                <aside
                    v-if="selected"
                    data-panel
                    class="absolute top-4 right-4 w-72 max-w-[calc(100%-2rem)] cursor-default rounded-2xl border bg-card p-4 shadow-xl"
                    aria-label="Member details"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">
                                {{ selected.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ selected.code }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="-mt-1 -mr-1 flex size-8 items-center justify-center rounded-lg hover:bg-muted"
                            aria-label="Close details"
                            @click="selected = null"
                        >
                            <X class="size-4" aria-hidden="true" />
                        </button>
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Status
                            </dt>
                            <dd class="font-medium capitalize">
                                {{ selected.status }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Package
                            </dt>
                            <dd class="font-medium">
                                {{ selected.package ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Rank</dt>
                            <dd class="font-medium">
                                {{ selected.rank ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Joined
                            </dt>
                            <dd class="font-medium tabular-nums">
                                {{ selected.joined ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Left BV
                            </dt>
                            <dd class="font-medium tabular-nums">
                                {{ bv.format(selected.leftBv) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Right BV
                            </dt>
                            <dd class="font-medium tabular-nums">
                                {{ bv.format(selected.rightBv) }}
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-xs text-muted-foreground">
                                Team volume (lifetime)
                            </dt>
                            <dd class="font-medium tabular-nums">
                                {{ bv.format(selected.teamBv) }} BV
                            </dd>
                        </div>
                    </dl>
                    <button
                        v-if="selected.code !== root.code"
                        type="button"
                        class="mt-4 h-9 w-full rounded-lg bg-brand text-sm font-semibold text-white hover:bg-brand-strong disabled:opacity-60"
                        :disabled="finding"
                        @click="showFrom(selected.code)"
                    >
                        Show tree from here · এখান থেকে দেখুন
                    </button>
                </aside>
            </div>

            <div
                class="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3 text-xs text-muted-foreground"
            >
                <ul class="flex flex-wrap gap-4">
                    <li
                        v-for="item in legend"
                        :key="item.label"
                        class="flex items-center gap-1.5"
                    >
                        <span
                            class="size-2.5 rounded-full"
                            :style="{ background: item.color }"
                            aria-hidden="true"
                        />
                        {{ item.label }}
                    </li>
                    <li class="flex items-center gap-1.5">
                        <span
                            class="size-2.5 rounded-full border-2 border-dashed border-muted-foreground/50"
                            aria-hidden="true"
                        />
                        Vacant · খালি
                    </li>
                </ul>
                <p>
                    Drag to move · Ctrl + scroll to zoom · click a member for
                    details, + to open their team
                </p>
            </div>
        </section>
    </div>
</template>
