<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance'
import {
    Check,
    Monitor,
    Moon,
    Sun,
} from 'lucide-vue-next'

interface Props {
    class?: string
}

const { class: containerClass = '' } = defineProps<Props>()

const { appearance, updateAppearance } = useAppearance()

const options = [
    {
        value: 'light',
        Icon: Sun,
        label: 'Light',
        description: 'Use the light HivePanel interface.',
    },
    {
        value: 'dark',
        Icon: Moon,
        label: 'Dark',
        description: 'Use the dark HivePanel interface.',
    },
    {
        value: 'system',
        Icon: Monitor,
        label: 'System',
        description: 'Automatically match your device setting.',
    },
] as const
</script>

<template>
    <div
        :class="[
            'grid gap-3 md:grid-cols-3',
            containerClass,
        ]"
    >
        <button
            v-for="{ value, Icon, label, description } in options"
            :key="value"
            type="button"
            :aria-pressed="appearance === value"
            :class="[
                'group relative flex min-h-36 flex-col rounded-xl border p-4 text-left transition',
                appearance === value
                    ? 'border-hive/30 bg-hive/[0.06]'
                    : 'border-white/[0.07] bg-[#0d0f11] hover:border-white/[0.13] hover:bg-[#101315]',
            ]"
            @click="updateAppearance(value)"
        >
            <div class="flex items-start justify-between gap-3">
                <div
                    :class="[
                        'flex size-10 items-center justify-center rounded-xl border transition',
                        appearance === value
                            ? 'border-hive/20 bg-hive/10 text-hive'
                            : 'border-white/[0.06] bg-white/[0.025] text-zinc-500 group-hover:text-zinc-300',
                    ]"
                >
                    <component
                        :is="Icon"
                        class="size-4"
                    />
                </div>

                <div
                    :class="[
                        'flex size-5 items-center justify-center rounded-full border transition',
                        appearance === value
                            ? 'border-hive bg-hive text-black'
                            : 'border-white/[0.1] bg-transparent text-transparent',
                    ]"
                >
                    <Check class="size-3" />
                </div>
            </div>

            <div class="mt-auto pt-5">
                <div
                    :class="[
                        'text-sm font-semibold transition',
                        appearance === value
                            ? 'text-white'
                            : 'text-zinc-300',
                    ]"
                >
                    {{ label }}
                </div>

                <p class="mt-1 text-xs leading-5 text-zinc-600">
                    {{ description }}
                </p>
            </div>
        </button>
    </div>
</template>