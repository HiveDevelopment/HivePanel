<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowUpRight, FolderOpen, Globe, SlidersHorizontal, Terminal } from 'lucide-vue-next';
import { computed } from 'vue';
const props = defineProps<{ cell: any }>();
const comb = computed(() => props.cell.comb_data ?? {});
const root = computed(() => props.cell.variables?.document_root ?? comb.value.variables?.document_root ?? 'public');
const port = computed(() => props.cell.allocation?.port ?? props.cell.variables?.server_port ?? 'your assigned port');
const cards = computed(() => [
    {
        title: 'Connect your domain',
        description: 'Add a hostname, verify DNS and enable automatic HTTPS.',
        href: `/cells/${props.cell.id}/domains`,
        icon: Globe,
    },
    {
        title: 'Website files',
        description: 'Upload your site or application into this Cell.',
        href: `/cells/${props.cell.id}/files`,
        icon: FolderOpen,
    },
    {
        title: 'Runtime & environment',
        description: 'Select an offered runtime image and edit application variables.',
        href: `/cells/${props.cell.id}/startup`,
        icon: SlidersHorizontal,
    },
    {
        title: 'Application logs',
        description: 'Start your site and inspect its live console output.',
        href: `/cells/${props.cell.id}`,
        icon: Terminal,
    },
]);
</script>
<template>
    <AppLayout
        context="server"
        :active-cell="cell"
        :breadcrumbs="[
            { title: cell.name, href: `/cells/${cell.id}` },
            { title: 'Website', href: `/cells/${cell.id}/website` },
        ]"
        ><Head title="Website" />
        <div class="mx-auto w-full max-w-5xl space-y-6">
            <header class="flex items-center gap-4">
                <div class="flex size-12 items-center justify-center rounded-panel border border-hive/20 bg-hive/10">
                    <Globe class="size-6 text-hive" />
                </div>
                <div>
                    <h1 class="text-2xl font-black text-white">Your website</h1>
                    <p class="mt-1 text-sm text-zinc-400">{{ cell.name }} · {{ comb.name ?? 'Web application' }}</p>
                </div>
            </header>
            <section class="rounded-panel border border-zinc-800 bg-surface p-6">
                <h2 class="font-bold text-zinc-200">Application setup</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-zinc-500">Listening address</p>
                        <p class="mt-2 font-mono text-sm text-zinc-200">0.0.0.0:{{ port }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-zinc-500">Document root</p>
                        <p class="mt-2 font-mono text-sm text-zinc-200">
                            {{ ['php', 'static'].includes(comb.group) ? root : 'Defined by your application' }}
                        </p>
                    </div>
                </div>
                <p class="mt-5 text-sm leading-6 text-zinc-400">
                    Start the Cell, upload your application, then connect a domain. Static and PHP sites use their document root. Node.js and other
                    HTTP applications must listen on the PORT environment variable and accept connections on 0.0.0.0.
                </p>
                <p v-if="comb.group === 'php'" class="mt-3 text-xs leading-5 text-zinc-500">
                    PHP runs with Nginx and PHP-FPM. Set the document root to public for Laravel, or to the folder containing WordPress’s index.php.
                    Database credentials are configured separately through your application.
                </p>
            </section>
            <div class="grid gap-4 sm:grid-cols-2">
                <Link
                    v-for="card in cards"
                    :key="card.title"
                    :href="card.href"
                    class="group rounded-panel border border-zinc-800 bg-surface p-6 transition hover:border-hive/40"
                    ><div class="flex items-center justify-between">
                        <component :is="card.icon" class="size-5 text-hive" /><ArrowUpRight
                            class="size-4 text-zinc-600 transition group-hover:text-hive"
                        />
                    </div>
                    <h2 class="mt-4 font-black text-white">{{ card.title }}</h2>
                    <p class="mt-2 text-sm leading-6 text-zinc-400">{{ card.description }}</p></Link
                >
            </div>
        </div></AppLayout
    >
</template>
