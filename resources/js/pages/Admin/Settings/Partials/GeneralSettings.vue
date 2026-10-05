<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Building2, Globe2, Image, Save } from 'lucide-vue-next'
import type { SettingsPayload } from '../types'

const props = defineProps<{
    settings: SettingsPayload['general']
}>()

const form = useForm({
    company_name: props.settings.company_name ?? 'HivePanel',
    company_logo: props.settings.company_logo ?? '',
    default_language: props.settings.default_language ?? 'en',
})

function submit() {
    form.patch('/admin/settings/general', {
        preserveScroll: true,
    })
}
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit">
        <section class="rounded-panel border border-white/[0.06] bg-surface p-5 sm:p-6">
            <div class="mb-6">
                <h3 class="text-base font-semibold text-white">
                    Branding
                </h3>

                <p class="mt-1 text-sm text-zinc-500">
                    Configure how your HivePanel installation is presented to users.
                </p>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                <div>
                    <label class="flex items-center gap-2 text-sm font-medium text-zinc-300">
                        <Building2 class="size-4 text-zinc-500" />
                        Company name
                    </label>

                    <input
                        v-model="form.company_name"
                        type="text"
                        class="mt-2 w-full rounded-lg border border-white/[0.07] bg-[#111417] px-3.5 py-2.5 text-sm text-white outline-none transition placeholder:text-zinc-600 focus:border-hive/50"
                        placeholder="HivePanel"
                    />

                    <p v-if="form.errors.company_name" class="mt-2 text-xs font-medium text-status-danger">
                        {{ form.errors.company_name }}
                    </p>
                </div>

                <div>
                    <label class="flex items-center gap-2 text-sm font-medium text-zinc-300">
                        <Globe2 class="size-4 text-zinc-500" />
                        Default language
                    </label>

                    <select
                        v-model="form.default_language"
                        class="mt-2 w-full rounded-lg border border-white/[0.07] bg-[#111417] px-3.5 py-2.5 text-sm text-white outline-none transition focus:border-hive/50"
                    >
                        <option value="en">English</option>
                        <option value="en-GB">English (UK)</option>
                    </select>

                    <p v-if="form.errors.default_language" class="mt-2 text-xs font-medium text-status-danger">
                        {{ form.errors.default_language }}
                    </p>
                </div>

                <div class="lg:col-span-2">
                    <label class="flex items-center gap-2 text-sm font-medium text-zinc-300">
                        <Image class="size-4 text-zinc-500" />
                        Company logo URL
                    </label>

                    <input
                        v-model="form.company_logo"
                        type="text"
                        class="mt-2 w-full rounded-lg border border-white/[0.07] bg-[#111417] px-3.5 py-2.5 text-sm text-white outline-none transition placeholder:text-zinc-600 focus:border-hive/50"
                        placeholder="https://example.com/logo.png"
                    />

                    <p class="mt-2 text-xs text-zinc-600">
                        Used throughout the panel, authentication pages and emails.
                    </p>

                    <p v-if="form.errors.company_logo" class="mt-2 text-xs font-medium text-status-danger">
                        {{ form.errors.company_logo }}
                    </p>
                </div>
            </div>
        </section>

        <div class="flex justify-end">
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center gap-2 rounded-lg bg-hive px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-hive-light disabled:opacity-50"
            >
                <Save class="size-4" />
                {{ form.processing ? 'Saving...' : 'Save changes' }}
            </button>
        </div>
    </form>
</template>