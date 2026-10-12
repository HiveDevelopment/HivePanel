<script setup lang="ts">
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ user: User }>();
const { getInitials } = useInitials();
const failed = ref(false);
const image = computed(() => {
    const source = props.user.avatar ?? '';
    if (!source || failed.value) return null;
    try {
        const host = new URL(source, 'https://hivepanel.invalid').hostname.toLowerCase();
        if (host === 'gravatar.com' || host.endsWith('.gravatar.com')) return null;
    } catch { return null; }
    return source;
});
watch(() => props.user.avatar, () => { failed.value = false; });
const hash = computed(() => {
    let value = 2166136261;
    for (const char of String(props.user.id ?? props.user.name)) value = Math.imul(value ^ char.charCodeAt(0), 16777619);
    return value >>> 0;
});
const palettes = [
    ['#241c10', '#fbbf24', '#b77916'],
    ['#10251f', '#34d399', '#16896c'],
    ['#152139', '#60a5fa', '#356bb8'],
    ['#271a36', '#c084fc', '#8753be'],
    ['#301b24', '#fb7185', '#b34260'],
    ['#10262d', '#22d3ee', '#18879d'],
];
const palette = computed(() => palettes[hash.value % palettes.length]);
</script>

<template>
    <span class="inline-flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-lg" :style="{ backgroundColor: palette[0], border: `1px solid ${palette[2]}` }">
        <img v-if="image" :src="image" :alt="user.name" class="size-full object-cover" @error="failed = true" />
        <span v-else class="select-none text-xs font-bold leading-none" :style="{ color: palette[1] }" :aria-label="`${user.name} avatar`">{{ getInitials(user.name) }}</span>
    </span>
</template>
