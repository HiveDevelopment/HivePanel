<script setup lang="ts">
import { designTokens } from '@/composables/useDesignTokens';
import { t } from '@/composables/useLanguage';
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps<{
    title: string;
    value: string;
    unit?: string;
    labels: string[];
    used: number[];
    max: number[];
}>();

const series = computed(() => [
    {
        name: 'In Use',
        data: props.used,
    },
    {
        name: 'Maximum',
        data: props.max,
    },
]);

const options = computed(() => ({
    chart: {
        type: 'line',
        height: 210,
        toolbar: {
            show: false,
        },
        zoom: {
            enabled: false,
        },
        animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 350,
        },
        background: 'transparent',
        foreColor: designTokens.value.muted,
    },
    stroke: {
        curve: 'smooth',
        width: [3, 2],
        dashArray: [0, 6],
    },
    markers: {
        size: 0,
    },
    grid: {
        borderColor: designTokens.value.border,
        strokeDashArray: 4,
    },
    xaxis: {
        categories: props.labels,
        labels: {
            style: {
                colors: designTokens.value.muted,
                fontSize: '11px',
            },
            rotate: 0,
        },
        axisBorder: {
            show: false,
        },
        axisTicks: {
            show: false,
        },
        tooltip: {
            enabled: false,
        },
    },
    yaxis: {
        min: 0,
        labels: {
            style: {
                colors: designTokens.value.muted,
                fontSize: '11px',
            },
            formatter(value: number) {
                return `${Math.round(value)}${props.unit ?? ''}`;
            },
        },
    },
    legend: {
        show: true,
        position: 'top',
        horizontalAlign: 'right',
        labels: {
            colors: designTokens.value.muted,
        },
        markers: {
            width: 8,
            height: 8,
            radius: 999,
        },
    },
    tooltip: {
        theme: 'dark',
        y: {
            formatter(value: number) {
                return `${value}${props.unit ? ` ${props.unit}` : ''}`;
            },
        },
    },
    dataLabels: {
        enabled: false,
    },
}));
</script>

<template>
    <div class="rounded-button border border-border bg-surface-dark p-4">
        <div class="mb-4 flex items-center justify-between gap-4">
            <div>
                <h3 class="font-black text-foreground">
                    {{ title }}
                </h3>

                <p class="mt-1 text-xs font-bold text-muted-foreground">
                    {{ t('ui.used_vs_maximum') }}
                </p>
            </div>

            <div class="text-right text-sm font-black text-foreground">
                {{ value }}
            </div>
        </div>

        <VueApexCharts type="line" height="210" :options="options" :series="series" />
    </div>
</template>
