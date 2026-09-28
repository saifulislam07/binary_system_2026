<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- Brand mark: one member with a left and a right leg. -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 40"
        :class="className"
        v-bind="$attrs"
    >
        <path
            d="M20 11 9.5 29M20 11l10.5 18"
            fill="none"
            stroke="currentColor"
            stroke-width="3.5"
            stroke-linecap="round"
        />
        <circle cx="20" cy="9" r="6" fill="currentColor" />
        <circle cx="9" cy="30" r="5.5" fill="currentColor" />
        <circle cx="31" cy="30" r="5.5" fill="currentColor" />
    </svg>
</template>
