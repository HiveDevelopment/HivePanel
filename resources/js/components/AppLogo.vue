<script setup lang="ts">
import { useAppSettings } from '@/composables/useAppSettings';
import { t } from '@/composables/useLanguage';

const { appLogo } = useAppSettings();
interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <img v-if="appLogo" :src="appLogo" :alt="t('ui.logo')" class="mx-auto" style="padding: 0px 30px" />
    <img v-else src="https://cdn.thatbritish.one/HivePanelLogo.png" :alt="t('ui.logo')" class="mx-auto" style="padding: 0px 30px" />
</template>
