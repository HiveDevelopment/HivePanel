<script setup lang="ts">
import { designTokens } from '@/composables/useDesignTokens';
import { CategoryScale, Chart as ChartJS, Legend, LinearScale, LineElement, PointElement, Tooltip } from 'chart.js';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend);

const props = defineProps<{
    title: string;
    value: string;
    labels: string[];
    used: number[];
    max: number[];
    suffix?: string;
}>();

const chartData = computed(() => ({
    labels: props.labels,
    datasets: [
        {
            label: 'In Use',
            data: props.used,
            borderWidth: 2,
            tension: 0.35,
            pointRadius: 0,
        },
        {
            label: 'Maximum',
            data: props.max,
            borderWidth: 2,
            borderDash: [6, 6],
            tension: 0.35,
            pointRadius: 0,
        },
    ],
}));

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            labels: {
                color: designTokens.value.muted,
                boxWidth: 10,
                boxHeight: 10,
            },
        },
        tooltip: {
            callbacks: {
                label(context: any) {
                    return `${context.dataset.label}: ${context.parsed.y}${props.suffix ?? ''}`;
                },
            },
        },
    },
    scales: {
        x: {
            ticks: {
                color: designTokens.value.muted,
                maxTicksLimit: 6,
            },
            grid: {
                color: designTokens.value.border,
            },
        },
        y: {
            ticks: {
                color: designTokens.value.muted,
                callback(value: number) {
                    return `${value}${props.suffix ?? ''}`;
                },
            },
            grid: {
                color: designTokens.value.border,
            },
        },
    },
}));
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-4">
            <h3 class="font-black text-foreground">{{ title }}</h3>
            <span class="text-sm font-bold text-muted-foreground">{{ value }}</span>
        </div>

        <div class="h-32 rounded-button border border-border bg-surface-dark p-3">
            <Line :data="chartData" :options="chartOptions" />
        </div>
    </div>
</template>
