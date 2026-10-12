<script setup lang="ts">
defineProps<{ form: any }>()
</script>

<template>
    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
        <h2 class="text-lg font-black">Console and shutdown</h2>
        <p class="mt-1 text-sm text-zinc-500">Define commands for this Comb. Suggestions fill the console input; they do not execute automatically.</p>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="text-sm font-bold">Command suggestions (one per line)
                <textarea v-model="form.console_commands_text" rows="5" placeholder="listplayers&#10;help" class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-white" />
            </label>
            <div class="space-y-3">
                <label class="block text-sm font-bold">Shutdown command
                    <input v-model="form.stop_command" placeholder="quit" class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-white" />
                </label>
                <p class="text-xs text-zinc-500">Leave blank for a signal-only server. Use the shutdown command supported by the game, such as quit or exit.</p>
                <label class="block text-sm font-bold">Grace period (seconds)
                    <input v-model.number="form.stop_timeout" type="number" min="1" max="600" class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-white" />
                </label>
                <label class="block text-sm font-bold">Shutdown signal
                    <select v-model="form.stop_signal" class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-white">
                        <option>SIGTERM</option><option>SIGINT</option><option>SIGHUP</option><option>SIGQUIT</option>
                    </select>
                </label>
            </div>
        </div>
        <p class="mt-3 text-xs text-zinc-500">Shutdown commands get the grace period to finish before the signal fallback. Shutdown settings are used when stopping existing Cells; other Comb runtime changes apply on their next start.</p>
    </section>
</template>
