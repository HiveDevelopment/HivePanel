<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import {
    Fingerprint,
    LoaderCircle,
    LockKeyhole,
    ShieldCheck,
} from 'lucide-vue-next'

defineProps<{
    returnTo?: string | null
}>()

const form = useForm({
    password: '',
})

const submit = () => {
    form.post(route('password.confirm'), {
        preserveScroll: true,

        onFinish: () => {
            form.reset('password')
        },
    })
}
</script>

<template>
    <AuthLayout
        title="Confirm it's you"
        description="This is a security-sensitive action. Confirm your password to continue."
    >
        <Head title="Confirm password" />

        <div class="space-y-5">
            <div class="rounded-xl border border-hive/15 bg-hive/[0.05] p-4">
                <div class="flex items-start gap-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-hive/15 bg-hive/[0.07]">
                        <ShieldCheck class="size-4 text-hive" />
                    </div>

                    <div>
                        <p class="text-xs font-semibold text-zinc-200">
                            Security verification
                        </p>

                        <p class="mt-1 text-xs leading-5 text-zinc-500">
                            HivePanel requires your password before allowing changes to sensitive account credentials.
                        </p>
                    </div>
                </div>
            </div>

            <form
                class="space-y-5"
                @submit.prevent="submit"
            >
                <div>
                    <label
                        for="password"
                        class="mb-2 block text-xs font-semibold uppercase tracking-wide text-zinc-500"
                    >
                        Current password
                    </label>

                    <div class="flex items-center gap-3 rounded-xl border border-white/[0.07] bg-[#0d0f11] px-4 py-3 transition focus-within:border-hive/50 focus-within:ring-4 focus-within:ring-hive/[0.05]">
                        <LockKeyhole class="size-4 shrink-0 text-zinc-600" />

                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            autofocus
                            autocomplete="current-password"
                            placeholder="Enter your password"
                            class="w-full bg-transparent text-sm font-medium text-white outline-none placeholder:text-zinc-700"
                        />
                    </div>

                    <InputError
                        class="mt-2"
                        :message="form.errors.password"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-hive px-4 py-3 text-sm font-semibold text-black transition hover:bg-hive-light disabled:cursor-wait disabled:opacity-60"
                >
                    <LoaderCircle
                        v-if="form.processing"
                        class="size-4 animate-spin"
                    />

                    <Fingerprint
                        v-else
                        class="size-4"
                    />

                    {{ form.processing ? 'Confirming...' : 'Confirm and continue' }}
                </button>
            </form>

            <div class="flex items-center justify-center gap-2 text-[11px] text-zinc-600">
                <ShieldCheck class="size-3.5" />
                Protected account action
            </div>
        </div>
    </AuthLayout>
</template>