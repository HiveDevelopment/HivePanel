<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue'
import ConfirmHost from '@/components/ui/ConfirmHost.vue'
import type { BreadcrumbItemType } from '@/types'
import GlobalToasts from '@/components/ui/GlobalToasts.vue'

type CellStatus = 'offline' | 'starting' | 'running' | 'stopping'

interface Props {
    breadcrumbs?: BreadcrumbItemType[]
    context?: 'dashboard' | 'server' | 'admin'
    activeCell?: any
    activeCellStatus?: CellStatus
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    context: 'dashboard',
})
</script>

<template>
    <AppLayout
        :breadcrumbs="breadcrumbs"
        :context="context"
        :active-cell="activeCell"
        :active-cell-status="activeCellStatus"
    >
        <slot />
        <ConfirmHost />
        <GlobalToasts />
    </AppLayout>
</template>