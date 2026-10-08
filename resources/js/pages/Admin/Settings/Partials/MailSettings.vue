<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import { useForm } from '@inertiajs/vue3'
import { Mail, Send, Save, Server } from 'lucide-vue-next'
import { ref } from 'vue'
import type { SettingsPayload } from '../types'

const props = defineProps<{ settings: SettingsPayload['mail'] }>()

const form = useForm({
    host: props.settings.host ?? '',
    port: props.settings.port ?? 587,
    encryption: props.settings.encryption ?? 'tls',
    username: props.settings.username ?? '',
    password: '',
    from_address: props.settings.from_address ?? '',
    from_name: props.settings.from_name ?? '',
})

const testEmail = ref('')
const testForm = useForm({ email: '' })

function save() {
    form.patch('/admin/settings/mail', {
        preserveScroll: true,
        onSuccess: () => {
            form.password = ''
            form.defaults()
        },
    })
}

function sendTest() {
    testForm.email = testEmail.value
    testForm.post('/admin/settings/mail/test', { preserveScroll: true })
}
</script>

<template>
    <div class="space-y-5">
        <form class="rounded-panel border border-white/[0.06] bg-surface p-5 sm:p-6" @submit.prevent="save">
            <div class="mb-5 flex items-center gap-3">
                <div class="flex size-9 items-center justify-center rounded-button bg-hive/10">
                    <Server class="size-4 text-hive" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-white">SMTP configuration</h3>
                    <p class="mt-1 text-sm text-zinc-500">Configure the outgoing mail server used by HivePanel.</p>
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="mail-host" class="text-xs font-bold uppercase tracking-wide text-zinc-500">SMTP host</label>
                    <input id="mail-host" v-model="form.host" type="text" autocomplete="off" placeholder="smtp.example.com" class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-dark px-4 py-3 text-sm text-white outline-none focus:border-hive/50" />
                    <InputError :message="form.errors.host" class="mt-2" />
                </div>
                <div>
                    <label for="mail-port" class="text-xs font-bold uppercase tracking-wide text-zinc-500">SMTP port</label>
                    <input id="mail-port" v-model.number="form.port" type="number" min="1" max="65535" class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-dark px-4 py-3 text-sm text-white outline-none focus:border-hive/50" />
                    <InputError :message="form.errors.port" class="mt-2" />
                </div>
                <div>
                    <label for="mail-encryption" class="text-xs font-bold uppercase tracking-wide text-zinc-500">Encryption</label>
                    <select id="mail-encryption" v-model="form.encryption" class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-dark px-4 py-3 text-sm text-white outline-none focus:border-hive/50">
                        <option value="tls">TLS / STARTTLS</option>
                        <option value="ssl">SSL</option>
                        <option value="none">None</option>
                    </select>
                    <InputError :message="form.errors.encryption" class="mt-2" />
                </div>
                <div>
                    <label for="mail-username" class="text-xs font-bold uppercase tracking-wide text-zinc-500">Username</label>
                    <input id="mail-username" v-model="form.username" type="text" autocomplete="off" class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-dark px-4 py-3 text-sm text-white outline-none focus:border-hive/50" />
                    <InputError :message="form.errors.username" class="mt-2" />
                </div>
                <div class="sm:col-span-2">
                    <label for="mail-password" class="text-xs font-bold uppercase tracking-wide text-zinc-500">SMTP password</label>
                    <input id="mail-password" v-model="form.password" type="password" autocomplete="new-password" placeholder="Leave blank to keep the saved password" class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-dark px-4 py-3 text-sm text-white outline-none focus:border-hive/50" />
                    <InputError :message="form.errors.password" class="mt-2" />
                </div>
                <div>
                    <label for="mail-from-address" class="text-xs font-bold uppercase tracking-wide text-zinc-500">From address</label>
                    <input id="mail-from-address" v-model="form.from_address" type="email" placeholder="noreply@example.com" class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-dark px-4 py-3 text-sm text-white outline-none focus:border-hive/50" />
                    <InputError :message="form.errors.from_address" class="mt-2" />
                </div>
                <div>
                    <label for="mail-from-name" class="text-xs font-bold uppercase tracking-wide text-zinc-500">From name</label>
                    <input id="mail-from-name" v-model="form.from_name" type="text" placeholder="HivePanel" class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-dark px-4 py-3 text-sm text-white outline-none focus:border-hive/50" />
                    <InputError :message="form.errors.from_name" class="mt-2" />
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-button bg-hive px-5 py-2.5 text-sm font-bold text-black disabled:opacity-50">
                    <Save class="size-4" /> {{ form.processing ? 'Saving…' : 'Save mail settings' }}
                </button>
            </div>
        </form>

        <form class="rounded-panel border border-white/[0.06] bg-surface p-5 sm:p-6" @submit.prevent="sendTest">
            <div class="mb-4 flex items-center gap-3">
                <Mail class="size-5 text-hive" />
                <div>
                    <h3 class="text-base font-semibold text-white">Send test email</h3>
                    <p class="mt-1 text-sm text-zinc-500">Save your SMTP settings first, then send a test message.</p>
                </div>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                <div class="min-w-0 flex-1">
                    <input v-model="testEmail" type="email" required placeholder="recipient@example.com" aria-label="Test recipient email" class="w-full rounded-button border border-zinc-800 bg-surface-dark px-4 py-3 text-sm text-white outline-none focus:border-hive/50" />
                    <InputError :message="testForm.errors.email" class="mt-2" />
                </div>
                <button type="submit" :disabled="testForm.processing || form.isDirty" class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-700 px-5 py-3 text-sm font-bold text-white disabled:opacity-40">
                    <Send class="size-4" /> {{ testForm.processing ? 'Sending…' : 'Send test' }}
                </button>
            </div>
            <p v-if="form.isDirty" class="mt-2 text-xs text-amber-400">Save your changes before testing.</p>
        </form>
    </div>
</template>
