<script setup lang="ts">
import { designTokens } from '@/composables/useDesignTokens';
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

type ChartPoint = {
    x: number;
    y: number;
};

const props = defineProps<{
    title: string;
    value: string;
    history: ChartPoint[];
    suffix?: string;
    max?: number;
}>();

const series = computed(() => [
    {
        name: props.title,
        data: props.history,
    },
]);

const options = computed(() => ({
    chart: {
        type: 'area',
        height: 255,
        toolbar: { show: false },
        zoom: { enabled: false },
        animations: { enabled: false },
        foreColor: designTokens.value.foreground,
    },

    colors: [designTokens.value.accent],

    dataLabels: {
        enabled: false,
    },

    stroke: {
        curve: 'smooth',
        width: 3,
        colors: [designTokens.value.accent],
    },

    fill: {
        type: 'gradient',
        gradient: {
            shade: 'dark',
            type: 'vertical',
            shadeIntensity: 0,
            opacityFrom: 0.45,
            opacityTo: 0,
            stops: [0, 100],
        },
    },

    markers: {
        size: 0,
        hover: {
            size: 5,
        },
    },

    grid: {
        borderColor: designTokens.value.border,
        strokeDashArray: 3,
    },

    xaxis: {
        type: 'datetime',
        labels: {
            show: false,
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
        crosshairs: {
            stroke: {
                color: designTokens.value.accent,
                width: 1,
                dashArray: 3,
            },
        },
    },

    yaxis: {
        min: 0,
        max: props.max,
        tickAmount: 2,
        labels: {
            style: {
                colors: designTokens.value.foreground,
                fontWeight: 700,
            },
            formatter: (value: number) => `${value.toFixed(2)}${props.suffix ?? ''}`,
        },
    },

    tooltip: {
        theme: 'dark',
        style: {
            fontSize: '12px',
        },
        marker: {
            show: false,
        },
        x: {
            format: 'HH:mm:ss',
        },
        y: {
            formatter: (value: number) => `${value.toFixed(2)}${props.suffix ?? ''}`,
        },
    },

    legend: {
        show: false,
    },
}));
</script>

<template>
    <section class="rounded bg-surface p-4">
        <div class="mb-2 flex items-center justify-between">
            <h3 class="text-base font-bold text-foreground">{{ title }}</h3>
            <div class="text-sm font-black text-foreground">{{ value }}</div>
        </div>

        <VueApexCharts height="255" type="area" :options="options" :series="series" />
    </section>
</template>
