<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Rocket, Save } from 'lucide-vue-next';
const props = defineProps<{ cell: any; schema: Array<any>; variables: Record<string, string>; canEdit: boolean }>();
const editable = computed(() => props.schema.filter(variable => variable.name && !['server_ip', 'server_port'].includes(variable.name) && variable.user_editable !== false));
const form = useForm({ variables: Object.fromEntries(editable.value.map(variable => [variable.name, String(props.variables[variable.name] ?? variable.default ?? '')])) });
</script>

<template>
    <AppLayout context="server" :active-cell="cell">
        <Head :title="`${cell.name} - Startup`" />
        <main class="space-y-5 p-5 sm:p-8">
            <section class="rounded-panel border border-white/[0.08] bg-surface p-5"><div class="flex items-center gap-3"><Rocket class="size-5 text-hive" /><div><h1 class="text-xl font-bold">Startup</h1><p class="mt-1 text-xs text-zinc-500">Manage the variables declared by this Cell’s Comb. Changes apply on the next start.</p></div></div></section>
            <section class="rounded-panel border border-white/[0.08] bg-surface p-5"><h2 class="text-sm font-semibold text-zinc-200">Startup command</h2><pre class="mt-3 overflow-auto whitespace-pre-wrap rounded-button bg-surface-dark p-4 font-mono text-xs text-zinc-400">{{ cell.startup?.command || cell.comb_data?.startup?.command || 'Defined by the Comb' }}</pre></section>
            <form class="space-y-5" @submit.prevent="form.patch(`/cells/${cell.id}/startup`, { preserveScroll: true })">
                <fieldset :disabled="!canEdit || form.processing" class="grid gap-4 md:grid-cols-2">
                    <section v-for="variable in editable" :key="variable.name" class="rounded-panel border border-white/[0.08] bg-surface p-5">
                        <label :for="`variable-${variable.name}`" class="block text-sm font-semibold text-zinc-200">{{ variable.label || variable.name }}</label><p class="mt-1 min-h-5 text-xs leading-5 text-zinc-500">{{ variable.description }}</p>
                        <input :id="`variable-${variable.name}`" v-model="form.variables[variable.name]" :type="variable.type === 'password' || variable.secret ? 'password' : 'text'" :required="Boolean(variable.required)" maxlength="4096" autocomplete="off" class="mt-4 w-full rounded-button border border-white/[0.08] bg-surface-dark px-3 py-2.5 text-sm text-zinc-200 outline-none focus:border-hive/50 disabled:opacity-60" />
                    </section>
                </fieldset>
                <div v-if="Object.keys(form.errors).length" role="alert" class="rounded-button border border-status-danger/20 p-4 text-xs text-status-danger"><p v-for="(message, key) in form.errors" :key="key">{{ message }}</p></div>
                <p v-if="cell.configuration_pending" class="text-xs text-status-warning">The running container is using its previous startup settings. Restart to apply the saved changes.</p>
                <button v-if="canEdit" type="submit" :disabled="form.processing" class="flex items-center gap-2 rounded-button bg-hive px-5 py-3 text-xs font-bold text-black disabled:opacity-50"><Save class="size-4" />{{ form.processing ? 'Saving…' : 'Save variables' }}</button>
                <p v-if="form.recentlySuccessful" role="status" class="text-xs text-status-success">Variables saved.</p>
            </form>
        </main>
    </AppLayout>
</template>
