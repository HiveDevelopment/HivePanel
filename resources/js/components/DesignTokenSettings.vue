<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { ChevronDown, Languages, Palette } from 'lucide-vue-next';
import { availableLanguages, locale, setLanguage, t } from '@/composables/useLanguage';

const page = usePage<any>();
const saving = ref(false);
const themes = computed(() => page.props.themes?.themes ?? []);
const selectedTheme = computed(() => themes.value.some((theme: any) => theme.id === page.props.themePreference) ? page.props.themePreference : '');

function selectTheme(event: Event) {
    router.patch('/settings/theme', { theme_id: (event.target as HTMLSelectElement).value || null }, {
        preserveScroll: true,
        onStart: () => { saving.value = true; },
        onFinish: () => { saving.value = false; },
    });
}
</script>

<template>
    <div class="divide-y divide-white/[0.06] border-t border-white/[0.06]">
        <div class="p-5 sm:p-6">
            <div class="flex items-start gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-white/[0.06] bg-white/[0.025]">
                    <Palette class="size-4 text-zinc-500" />
                </div>
                <div class="min-w-0 flex-1">
                    <label for="panel-theme" class="text-sm font-medium text-zinc-200">{{ t('theme.select') }}</label>
                    <p id="panel-theme-description" class="mt-1 text-xs leading-5 text-zinc-500">{{ t('theme.offered') }}</p>
                    <div class="relative mt-4 w-full sm:max-w-sm">
                        <select
                            id="panel-theme"
                            :value="selectedTheme"
                            :disabled="saving"
                            aria-describedby="panel-theme-description panel-theme-error"
                            :aria-invalid="Boolean(page.props.errors?.theme_id)"
                            class="h-11 w-full appearance-none rounded-button border border-white/[0.08] bg-surface-dark py-2 pl-3 pr-10 text-sm font-medium text-zinc-200 outline-none transition focus:border-hive/50 focus:ring-2 focus:ring-hive/10 disabled:cursor-wait disabled:opacity-60"
                            @change="selectTheme"
                        >
                            <option value="">{{ t('theme.default') }}</option>
                            <option v-for="theme in themes" :key="theme.id" :value="theme.id">{{ theme.name }}</option>
                        </select>
                        <ChevronDown class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-zinc-500" />
                    </div>
                    <p v-if="page.props.errors?.theme_id" id="panel-theme-error" role="alert" class="mt-2 text-xs text-status-danger">{{ page.props.errors.theme_id }}</p>
                </div>
            </div>
        </div>

        <div class="p-5 sm:p-6">
            <div class="flex items-start gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-white/[0.06] bg-white/[0.025]">
                    <Languages class="size-4 text-zinc-500" />
                </div>
                <div class="min-w-0 flex-1">
                    <label for="panel-language" class="text-sm font-medium text-zinc-200">{{ t('language.title') }}</label>
                    <p id="panel-language-description" class="mt-1 text-xs leading-5 text-zinc-500">{{ t('language.description') }}</p>
                    <div class="relative mt-4 w-full sm:max-w-sm">
                        <select
                            id="panel-language"
                            :value="locale"
                            aria-describedby="panel-language-description"
                            class="h-11 w-full appearance-none rounded-button border border-white/[0.08] bg-surface-dark py-2 pl-3 pr-10 text-sm font-medium text-zinc-200 outline-none transition focus:border-hive/50 focus:ring-2 focus:ring-hive/10"
                            @change="setLanguage(($event.target as HTMLSelectElement).value)"
                        >
                            <option v-for="language in availableLanguages" :key="language.id" :value="language.id">{{ language.name }}</option>
                        </select>
                        <ChevronDown class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-zinc-500" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
