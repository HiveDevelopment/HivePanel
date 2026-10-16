<script lang="ts" setup>
import { t } from '@/composables/useLanguage';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <nav :aria-label="t('ui.breadcrumb')" :class="props.class">
        <slot />
    </nav>
</template>
