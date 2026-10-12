<script setup lang="ts">
import UserAvatar from '@/components/UserAvatar.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { type SharedData, type User } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';
import UserMenuContent from './UserMenuContent.vue';

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user as User);
</script>

<template>
    <DropdownMenu :modal="false">
        <DropdownMenuTrigger aria-label="Open account menu" style="display: inline-flex; flex: 0 0 auto; padding: 0; border: 0; height: 44px; width: auto; white-space: nowrap;" class="account-trigger rounded-lg text-foreground hover:bg-sidebar-accent focus-visible:ring-2 focus-visible:ring-hive data-[state=open]:bg-sidebar-accent">
                <span class="account-trigger-content" style="display: inline-flex; align-items: center; flex-direction: row; flex-wrap: nowrap; gap: 8px; height: 44px; box-sizing: border-box; padding: 6px; white-space: nowrap;">
                    <UserAvatar :user="user" />
                    <span class="hidden max-w-40 truncate text-sm font-semibold sm:block">{{ user.name }}</span>
                    <ChevronDown class="hidden size-4 text-muted-foreground sm:block" />
                </span>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="min-w-56 rounded-lg" side="bottom" align="end" :side-offset="8">
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>

<style scoped>
.account-trigger {
    display: inline-flex;
    box-sizing: border-box;
    flex: 0 0 auto;
    width: max-content;
    min-width: 44px;
    height: 44px;
    padding: 0;
    border: 0;
    transition: background-color 150ms;
    align-items: center;
    flex-direction: row;
    white-space: nowrap;
}

.account-trigger-content {
    display: inline-flex;
    align-items: center;
    flex-direction: row;
    flex-wrap: nowrap;
    gap: 0.5rem;
}
</style>
