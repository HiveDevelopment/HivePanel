<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { onBeforeUnmount, ref, watch } from 'vue'
import { Building2, Globe2, Image, Save, RotateCcw, Upload, Link2 } from 'lucide-vue-next'
import type { SettingsPayload } from '../types'

const props = defineProps<{ settings: SettingsPayload['general'] }>()

type Asset = 'logo' | 'favicon'
type Source = 'upload' | 'url'

const form = useForm({
    company_name: props.settings.company_name ?? 'HivePanel',
    default_language: props.settings.default_language ?? 'en',
    company_logo: props.settings.company_logo ?? '',
    company_favicon: props.settings.company_favicon ?? '',
    logo_upload: null as File | null,
    favicon_upload: null as File | null,
    reset_logo: false,
    reset_favicon: false,
})

const logoSource = ref<Source>('upload')
const faviconSource = ref<Source>('upload')
const previews = ref<Record<Asset, string>>({ logo: '', favicon: '' })

function setUpload(asset: Asset, event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null
    if (previews.value[asset]) URL.revokeObjectURL(previews.value[asset])
    previews.value[asset] = file ? URL.createObjectURL(file) : ''
    if (asset === 'logo') {
        form.logo_upload = file
        form.reset_logo = false
    } else {
        form.favicon_upload = file
        form.reset_favicon = false
    }
}

function resetAsset(asset: Asset) {
    if (previews.value[asset]) URL.revokeObjectURL(previews.value[asset])
    previews.value[asset] = ''
    if (asset === 'logo') {
        form.logo_upload = null
        form.company_logo = ''
        form.reset_logo = true
    } else {
        form.favicon_upload = null
        form.company_favicon = ''
        form.reset_favicon = true
    }
}

function assetPreview(asset: Asset): string {
    if (asset === 'logo') return form.reset_logo ? '' : previews.value.logo || form.company_logo
    return form.reset_favicon ? '' : previews.value.favicon || form.company_favicon
}

watch(logoSource, (source) => {
    if (source === 'url') { form.logo_upload = null; if (previews.value.logo) URL.revokeObjectURL(previews.value.logo); previews.value.logo = '' }
})
watch(faviconSource, (source) => {
    if (source === 'url') { form.favicon_upload = null; if (previews.value.favicon) URL.revokeObjectURL(previews.value.favicon); previews.value.favicon = '' }
})
onBeforeUnmount(() => Object.values(previews.value).forEach((url) => { if (url) URL.revokeObjectURL(url) }))

function submit() {
    // Laravel method spoofing ensures multipart form data is parsed consistently.
    form.transform((data) => ({ ...data, _method: 'PATCH' })).post('/admin/settings/general', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.logo_upload = null
            form.favicon_upload = null
            form.reset_logo = false
            form.reset_favicon = false
            for (const asset of ['logo', 'favicon'] as const) {
                if (previews.value[asset]) URL.revokeObjectURL(previews.value[asset])
                previews.value[asset] = ''
            }
        },
    })
}
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit">
        <section class="rounded-panel border border-white/[0.06] bg-surface p-5 sm:p-6">
            <div class="mb-6">
                <h3 class="text-base font-semibold text-white">Branding</h3>
                <p class="mt-1 text-sm text-zinc-500">Configure how your HivePanel installation is presented to users.</p>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                <div>
                    <label for="company-name" class="flex items-center gap-2 text-sm font-medium text-zinc-300">
                        <Building2 class="size-4 text-zinc-500" /> Company name
                    </label>
                    <input id="company-name" v-model="form.company_name" required maxlength="100"
                        class="mt-2 w-full rounded-lg border border-white/[0.07] bg-[#111417] px-3.5 py-2.5 text-sm text-white outline-none focus:border-hive/50" />
                    <p v-if="form.errors.company_name" class="mt-2 text-xs text-status-danger">{{ form.errors.company_name }}</p>
                </div>
                <div>
                    <label for="default-language" class="flex items-center gap-2 text-sm font-medium text-zinc-300">
                        <Globe2 class="size-4 text-zinc-500" /> Default language
                    </label>
                    <select id="default-language" v-model="form.default_language"
                        class="mt-2 w-full rounded-lg border border-white/[0.07] bg-[#111417] px-3.5 py-2.5 text-sm text-white outline-none focus:border-hive/50">
                        <option value="en">English</option>
                        <option value="en-GB">English (UK)</option>
                    </select>
                </div>
            </div>

            <div v-for="asset in (['logo', 'favicon'] as const)" :key="asset" class="mt-6 border-t border-white/[0.06] pt-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-white">
                            <Image class="size-4 text-hive" /> {{ asset === 'logo' ? 'Company logo' : 'Favicon' }}
                        </h4>
                        <p class="mt-1 text-xs text-zinc-500">
                            {{ asset === 'logo' ? 'Used throughout the panel and authentication pages.' : 'Shown in browser tabs and bookmarks.' }}
                        </p>
                    </div>
                    <button type="button" class="inline-flex items-center gap-1.5 text-xs text-zinc-400 hover:text-white" @click="resetAsset(asset)">
                        <RotateCcw class="size-3.5" /> Reset to default
                    </button>
                </div>

                <div class="grid gap-4 sm:grid-cols-[96px_1fr]">
                    <div class="flex size-24 items-center justify-center overflow-hidden rounded-xl border border-white/10 bg-[#111417] p-3">
                        <img v-if="assetPreview(asset)" :src="assetPreview(asset)" :alt="asset + ' preview'" class="max-h-full max-w-full object-contain" />
                        <Image v-else class="size-7 text-zinc-600" />
                    </div>
                    <div>
                        <div class="mb-3 inline-flex rounded-lg border border-white/10 bg-[#111417] p-1">
                            <button type="button" class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold"
                                :class="(asset === 'logo' ? logoSource : faviconSource) === 'upload' ? 'bg-hive text-black' : 'text-zinc-400'"
                                @click="asset === 'logo' ? logoSource = 'upload' : faviconSource = 'upload'">
                                <Upload class="size-3.5" /> Upload file
                            </button>
                            <button type="button" class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold"
                                :class="(asset === 'logo' ? logoSource : faviconSource) === 'url' ? 'bg-hive text-black' : 'text-zinc-400'"
                                @click="asset === 'logo' ? logoSource = 'url' : faviconSource = 'url'">
                                <Link2 class="size-3.5" /> External URL
                            </button>
                        </div>
                        <input v-if="(asset === 'logo' ? logoSource : faviconSource) === 'upload'" :key="asset + '-upload'"
                            type="file" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-zinc-400 file:mr-3 file:rounded-lg file:border-0 file:bg-white/10 file:px-3 file:py-2 file:text-zinc-200"
                            @change="setUpload(asset, $event)" />
                        <input v-else :value="asset === 'logo' ? form.company_logo : form.company_favicon" type="url" placeholder="https://example.com/image.png"
                            class="w-full rounded-lg border border-white/[0.07] bg-[#111417] px-3.5 py-2.5 text-sm text-white outline-none focus:border-hive/50"
                            @input="asset === 'logo' ? (form.company_logo = ($event.target as HTMLInputElement).value, form.reset_logo = false) : (form.company_favicon = ($event.target as HTMLInputElement).value, form.reset_favicon = false)" />
                        <p class="mt-2 text-xs text-zinc-600">PNG, JPG or WebP. {{ asset === 'logo' ? 'Maximum 2 MB.' : 'Square image recommended; maximum 1 MB.' }}</p>
                        <p v-if="asset === 'logo' && (form.errors.logo_upload || form.errors.company_logo)" class="mt-2 text-xs text-status-danger">{{ form.errors.logo_upload || form.errors.company_logo }}</p>
                        <p v-if="asset === 'favicon' && (form.errors.favicon_upload || form.errors.company_favicon)" class="mt-2 text-xs text-status-danger">{{ form.errors.favicon_upload || form.errors.company_favicon }}</p>
                    </div>
                </div>
            </div>
        </section>
        <div class="flex justify-end">
            <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg bg-hive px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-hive-light disabled:opacity-50">
                <Save class="size-4" /> {{ form.processing ? 'Saving...' : 'Save changes' }}
            </button>
        </div>
    </form>
</template>
