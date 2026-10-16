<script setup lang="ts">
import { t } from '@/composables/useLanguage';
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <img src="https://cdn.thatbritish.one/hivepanel-icon.png" :alt="t('ui.hivepanel_icon')" :class="className" />
</template>
