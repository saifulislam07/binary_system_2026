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
import { t } from '@/lib/i18n';
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
        findError.value = t(":code isn't in your team", { code });

        return null;
    }

    if (!response.ok) {
        findError.value = t('Could not load the tree. Please try again.');

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
            openView();
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
        openView();
    }
}

async function backToMe() {
    trail.value = [];
    root.value = props.tree;
    selected.value = null;
    await nextTick();
    openView();
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

/**
 * Starting view: full size where the tree fits across, zoomed out (down to
 * 45%) on narrow screens so both legs show.
 */
function openView() {
    const box = viewport.value?.getBoundingClientRect();
    const width = canvas.value?.offsetWidth ?? 0;

    if (box && width) {
        scale.value = Math.min(1, Math.max(0.45, (box.width - 24) / width));
    }

    center();
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

// Panning may start on a card too (on phones they cover most of the
// canvas): it only becomes a drag after a few pixels, and then the tap
// that would follow is swallowed. Two fingers pinch to zoom.
const pointers = new Map<number, { x: number; y: number }>();
let pinch: { distance: number; scale: number } | null = null;
let dragged = false;

function pinchState() {
    const [a, b] = [...pointers.values()];
    const box = viewport.value!.getBoundingClientRect();

    return {
        distance: Math.hypot(a.x - b.x, a.y - b.y),
        midX: (a.x + b.x) / 2 - box.left,
        midY: (a.y + b.y) / 2 - box.top,
    };
}

function onPointerDown(event: PointerEvent) {
    if ((event.target as HTMLElement).closest('input, [data-panel]')) {
        return;
    }

    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

    if (pointers.size === 2) {
        pinch = { distance: pinchState().distance, scale: scale.value };
        drag = null;

        return;
    }

    dragged = false;
    drag = {
        id: event.pointerId,
        startX: event.clientX,
        startY: event.clientY,
        x: x.value,
        y: y.value,
    };
}

function onPointerMove(event: PointerEvent) {
    if (!pointers.has(event.pointerId)) {
        return;
    }

    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

    if (pinch && pointers.size === 2) {
        const now = pinchState();
        zoomTo(
            pinch.scale * (now.distance / pinch.distance),
            now.midX,
            now.midY,
        );
        dragged = true;

        return;
    }

    if (!drag || drag.id !== event.pointerId) {
        return;
    }

    const dx = event.clientX - drag.startX;
    const dy = event.clientY - drag.startY;

    if (!dragged && Math.hypot(dx, dy) < 6) {
        return;
    }

    if (!dragged) {
        dragged = true;
        panning.value = true;
        viewport.value?.setPointerCapture(event.pointerId);
    }

    x.value = drag.x + dx;
    y.value = drag.y + dy;
}

function onPointerUp(event: PointerEvent) {
    pointers.delete(event.pointerId);

    if (pointers.size < 2) {
        pinch = null;
    }

    if (drag?.id === event.pointerId || pointers.size === 0) {
        drag = null;
        panning.value = false;
    }
}

function swallowClickAfterDrag(event: MouseEvent) {
    if (dragged) {
        event.stopPropagation();
        event.preventDefault();
        dragged = false;
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

onMounted(openView);

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
    { label: 'Active', color: '#16a34a' },
    { label: 'Pending', color: '#d97706' },
    { label: 'Suspended', color: '#dc2626' },
];
</script>

<template>
    <Head :title="$t('Team')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ $t('My team') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'Your placement tree: every member below you, on your left and right.',
                        )
                    }}
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
                        {{ $t('Sponsor') }}
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
                    {{ $t('None (top of the tree)') }}
                </p>
            </div>

            <div class="rounded-2xl border bg-card p-5 sm:col-span-2">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm text-muted-foreground">
                            {{ $t('Left team') }}
                        </p>
                        <p class="mt-1 text-3xl font-semibold tabular-nums">
                            {{ legs.left.total }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                $t(':count active', { count: legs.left.active })
                            }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-muted-foreground">
                            {{ $t('Right team') }}
                        </p>
                        <p class="mt-1 text-3xl font-semibold tabular-nums">
                            {{ legs.right.total }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                $t(':count active', {
                                    count: legs.right.active,
                                })
                            }}
                        </p>
                    </div>
                </div>
                <div
                    class="mt-4 flex h-2 overflow-hidden rounded-full bg-muted"
                    role="img"
                    :aria-label="
                        $t('Left :left, right :right', {
                            left: legs.left.total,
                            right: legs.right.total,
                        })
                    "
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
                        {{ $t('Personally sponsored') }}
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
            :aria-label="$t('Binary tree')"
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
                    <label for="find-member" class="sr-only">{{
                        $t('Find a member by code')
                    }}</label>
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <input
                            id="find-member"
                            v-model="findCode"
                            :placeholder="$t('Find member') + ' · MBR-100004'"
                            class="h-9 w-56 rounded-lg border border-input bg-background pr-3 pl-8 text-sm uppercase outline-none placeholder:normal-case focus-visible:border-brand focus-visible:ring-4 focus-visible:ring-brand/15"
                        />
                    </div>
                    <button
                        type="submit"
                        class="h-9 rounded-lg bg-brand px-3 text-sm font-semibold text-white hover:bg-brand-strong disabled:opacity-60"
                        :disabled="finding"
                    >
                        {{ $t('Show') }}
                    </button>
                    <button
                        v-if="trail.length"
                        type="button"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg border px-3 text-sm font-medium hover:bg-muted"
                        @click="back"
                    >
                        {{ $t('Back') }}
                    </button>
                    <button
                        v-if="trail.length"
                        type="button"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg border px-3 text-sm font-medium hover:bg-muted"
                        @click="backToMe"
                    >
                        <ArrowUpToLine class="size-4" aria-hidden="true" />
                        {{ $t('Back to me') }}
                    </button>
                </form>

                <div
                    class="flex items-center gap-1"
                    role="group"
                    :aria-label="$t('Zoom')"
                >
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-lg border hover:bg-muted"
                        :aria-label="$t('Zoom out')"
                        @click="zoomTo(scale / 1.2)"
                    >
                        <Minus class="size-4" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="h-9 min-w-14 rounded-lg px-2 text-sm font-medium tabular-nums hover:bg-muted"
                        :aria-label="$t('Reset zoom')"
                        @click="((scale = 1), center())"
                    >
                        {{ Math.round(scale * 100) }}%
                    </button>
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-lg border hover:bg-muted"
                        :aria-label="$t('Zoom in')"
                        @click="zoomTo(scale * 1.2)"
                    >
                        <Plus class="size-4" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="ml-1 flex size-9 items-center justify-center rounded-lg border hover:bg-muted"
                        :aria-label="$t('Fit to screen')"
                        :title="$t('Fit to screen')"
                        @click="fit"
                    >
                        <Scan class="size-4" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-lg border hover:bg-muted"
                        :aria-label="$t('Full screen')"
                        :title="$t('Full screen')"
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
                :aria-label="
                    $t(
                        'Tree canvas. Drag or use the arrow keys to move, plus and minus to zoom.',
                    )
                "
                @pointerdown="onPointerDown"
                @click.capture="swallowClickAfterDrag"
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
                    :aria-label="$t('Member details')"
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
                            :aria-label="$t('Close details')"
                            @click="selected = null"
                        >
                            <X class="size-4" aria-hidden="true" />
                        </button>
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                {{ $t('Status') }}
                            </dt>
                            <dd class="font-medium capitalize">
                                {{ $t(selected.status) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                {{ $t('Package') }}
                            </dt>
                            <dd class="font-medium">
                                {{ selected.package ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                {{ $t('Rank') }}
                            </dt>
                            <dd class="font-medium">
                                {{ selected.rank ? $t(selected.rank) : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                {{ $t('Joined') }}
                            </dt>
                            <dd class="font-medium tabular-nums">
                                {{ selected.joined ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                {{ $t('Left BV') }}
                            </dt>
                            <dd class="font-medium tabular-nums">
                                {{ bv.format(selected.leftBv) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                {{ $t('Right BV') }}
                            </dt>
                            <dd class="font-medium tabular-nums">
                                {{ bv.format(selected.rightBv) }}
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-xs text-muted-foreground">
                                {{ $t('Team volume (lifetime)') }}
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
                        {{ $t('Show tree from here') }}
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
                        {{ $t(item.label) }}
                    </li>
                    <li class="flex items-center gap-1.5">
                        <span
                            class="size-2.5 rounded-full border-2 border-dashed border-muted-foreground/50"
                            aria-hidden="true"
                        />
                        {{ $t('Vacant') }}
                    </li>
                </ul>
                <p class="hidden sm:block">
                    {{
                        $t(
                            'Drag to move · Ctrl + scroll to zoom · click a member for details, + to open their team',
                        )
                    }}
                </p>
                <p class="sm:hidden">
                    {{
                        $t(
                            'Drag to move · pinch to zoom · tap a member for details',
                        )
                    }}
                </p>
            </div>
        </section>
    </div>
</template>
