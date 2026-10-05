<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import {
    Palette,
    Settings,
    ShieldCheck,
    UserRound,
} from 'lucide-vue-next'

const navigation = [
    {
        title: 'Profile',
        description: 'Personal information',
        href: '/settings/profile',
        icon: UserRound,
    },
    {
        title: 'Security',
        description: 'Password and passkeys',
        href: '/settings/security',
        icon: ShieldCheck,
    },
    {
        title: 'Appearance',
        description: 'Theme and display',
        href: '/settings/appearance',
        icon: Palette,
    },
]

const currentPath = window.location.pathname
</script>

<template>
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="space-y-5">
            <section class="rounded-2xl border border-white/[0.08] bg-[#111315] p-5 sm:p-6">
                <div class="flex items-center gap-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-hive/20 bg-hive/[0.06]">
                        <Settings class="size-5 text-hive" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-white">
                            Settings
                        </h1>

                        <p class="mt-1 text-sm text-zinc-500">
                            Manage your account, security and preferences.
                        </p>
                    </div>
                </div>
            </section>

            <nav class="grid gap-3 sm:grid-cols-3">
                <Link
                    v-for="item in navigation"
                    :key="item.href"
                    :href="item.href"
                    :class="[
                        'group flex min-w-0 items-center gap-3 rounded-xl border px-4 py-3.5 transition',
                        currentPath === item.href
                            ? 'border-hive/30 bg-hive/[0.07]'
                            : 'border-white/[0.07] bg-[#111315] hover:border-white/[0.12] hover:bg-[#141719]',
                    ]"
                >
                    <div
                        :class="[
                            'flex size-9 shrink-0 items-center justify-center rounded-lg border transition',
                            currentPath === item.href
                                ? 'border-hive/20 bg-hive/10 text-hive'
                                : 'border-white/[0.06] bg-black/10 text-zinc-500 group-hover:text-zinc-300',
                        ]"
                    >
                        <component
                            :is="item.icon"
                            class="size-4"
                        />
                    </div>

                    <div class="min-w-0">
                        <div
                            :class="[
                                'text-sm font-semibold',
                                currentPath === item.href
                                    ? 'text-white'
                                    : 'text-zinc-300',
                            ]"
                        >
                            {{ item.title }}
                        </div>

                        <div class="mt-0.5 truncate text-[11px] text-zinc-600">
                            {{ item.description }}
                        </div>
                    </div>
                </Link>
            </nav>

            <main>
                <slot />
            </main>
        </div>
    </div>
</template>