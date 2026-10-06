<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Plus, ShieldCheck, Trash2, Users } from 'lucide-vue-next'
import { ref } from 'vue'

const props = defineProps<{ roles: any[] }>()
const deleting = ref<any | null>(null)
const processing = ref(false)

function destroyRole() {
    if (!deleting.value) return

    processing.value = true
    router.delete(`/admin/roles/${deleting.value.id}`, {
        preserveScroll: true,
        onSuccess: () => deleting.value = null,
        onFinish: () => processing.value = false,
    })
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head title="Roles" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex size-11 items-center justify-center rounded-xl border border-hive/20 bg-hive/10">
                                    <ShieldCheck class="size-5 text-hive" />
                                </div>
                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">Roles</h1>
                                    <p class="mt-2 text-sm text-zinc-400">Create administrative roles and control exactly what staff can access.</p>
                                </div>
                            </div>

                            <Link href="/admin/roles/create" class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2.5 text-sm font-black text-black transition hover:opacity-90">
                                <Plus class="size-4" />
                                Create Role
                            </Link>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-panel border border-zinc-800 bg-surface">
                        <div v-if="roles.length === 0" class="p-10 text-center">
                            <ShieldCheck class="mx-auto size-8 text-zinc-700" />
                            <p class="mt-3 font-black text-zinc-300">No roles created</p>
                            <p class="mt-1 text-sm text-zinc-500">Create a role to give staff restricted administrative access.</p>
                        </div>

                        <div v-else class="divide-y divide-zinc-800">
                            <div v-for="role in roles" :key="role.id" class="flex flex-col gap-4 p-5 transition hover:bg-white/[0.015] sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <Link :href="`/admin/roles/${role.id}/edit`" class="font-black text-white hover:text-hive">{{ role.name }}</Link>
                                        <span v-if="role.is_system" class="rounded-full border border-hive/20 bg-hive/10 px-2 py-0.5 text-[10px] font-black uppercase text-hive">System</span>
                                    </div>
                                    <p class="mt-1 text-sm text-zinc-500">{{ role.description || 'No description provided.' }}</p>
                                    <div class="mt-2 flex gap-4 text-xs text-zinc-600">
                                        <span class="inline-flex items-center gap-1.5"><Users class="size-3.5" /> {{ role.users_count }} users</span>
                                        <span>{{ role.permissions_count }} permissions</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <Link :href="`/admin/roles/${role.id}/edit`" class="rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:text-hive">Edit</Link>
                                    <button v-if="!role.is_system" type="button" class="inline-flex size-9 items-center justify-center rounded-button border border-red-500/20 bg-red-500/5 text-red-400 transition hover:bg-red-500/10" @click="deleting = role">
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>

        <ConfirmationModal
            :open="Boolean(deleting)"
            title="Delete role"
            :description="`Delete ${deleting?.name ?? 'this role'}? Users assigned to it will immediately lose the permissions granted by this role.`"
            confirm-text="Delete role"
            cancel-text="Cancel"
            danger
            :loading="processing"
            @cancel="deleting = null"
            @confirm="destroyRole"
        />
    </AppLayout>
</template>
