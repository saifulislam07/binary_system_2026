<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

// Pages pass these through `defineOptions({ layout: { ... } })`; title and
// description are translation keys.
const {
    title = '',
    description = '',
    wide = false,
} = defineProps<{
    title?: string;
    description?: string;
    wide?: boolean;
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description" :wide="wide">
        <slot />
    </AuthLayout>
</template>
