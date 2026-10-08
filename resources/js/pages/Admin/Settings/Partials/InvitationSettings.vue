<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Mail, Save } from 'lucide-vue-next'

const props = defineProps<{
    settings: {
        subject: string
        greeting: string
        message: string
        button_text: string
        footer: string
    }
}>()

const form = useForm({ ...props.settings })

function save() {
    form.patch('/admin/settings/invitations', { preserveScroll: true })
}
</script>

<template>
    <section class="space-y-5 rounded-panel border border-white/[0.06] bg-surface p-6">
        <div class="flex items-center gap-3">
            <div class="rounded-xl bg-hive/10 p-3">
                <Mail class="size-5 text-hive" />
            </div>
            <div>
                <h3 class="font-semibold text-white">User invitation email</h3>
                <p class="text-sm text-zinc-500">Customise the email sent when an administrator invites a user.</p>
            </div>
        </div>

        <form class="space-y-4" @submit.prevent="save">
            <div v-for="field in (['subject', 'greeting', 'message', 'button_text', 'footer'] as const)" :key="field">
                <label class="block text-sm text-zinc-300">
                    {{ { subject: 'Email subject', greeting: 'Greeting', message: 'Main message', button_text: 'Button label', footer: 'Footer' }[field] }}
                    <textarea
                        v-if="field === 'message' || field === 'footer'"
                        v-model="form[field]"
                        :rows="field === 'message' ? 5 : 3"
                        class="mt-2 w-full rounded-lg border border-white/10 bg-surface-dark p-3 text-white"
                    />
                    <input
                        v-else
                        v-model="form[field]"
                        class="mt-2 w-full rounded-lg border border-white/10 bg-surface-dark p-3 text-white"
                    />
                </label>
                <p v-if="form.errors[field]" class="mt-1 text-xs text-red-400">{{ form.errors[field] }}</p>
            </div>

            <p class="text-xs text-zinc-500">
                Available placeholders: {name}, {email}, {app_name}. The password setup URL and its expiry notice are generated securely by HivePanel and cannot be edited here.
            </p>

            <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg bg-hive px-4 py-2.5 text-sm font-semibold text-black disabled:opacity-50">
                <Save class="size-4" /> {{ form.processing ? 'Saving…' : 'Save invitation template' }}
            </button>
        </form>
    </section>
</template>
