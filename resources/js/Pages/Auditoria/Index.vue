<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    logs: {
        data: Array<{
            id: number;
            action: string;
            created_at: string;
            user?: { id: number; username: string } | null;
            resource?: string | null;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    canExport: boolean;
}>();

const page = usePage();
const hasLogs = computed(() => props.logs.data.length > 0);
const exportUrl = computed(() => {
    const url = page.url ?? '';
    const queryString = url.includes('?') ? url.substring(url.indexOf('?') + 1) : '';
    const params = new URLSearchParams(queryString);
    params.set('format', 'csv');

    const qs = params.toString();

    return `/api/auditoria/export${qs ? `?${qs}` : ''}`;
});

const handlePagination = (link: { url: string | null }) => {
    if (!link.url) {
        return;
    }

    router.visit(link.url, {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <AuthenticatedLayout title="Logs de Auditoria">
        <Head title="Logs de Auditoria" />

        <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
            <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-lg font-semibold text-white">Logs de auditoria</h1>
                    <p class="text-sm text-slate-400">Acompanhe as acoes executadas pelos usuarios.</p>
                </div>
                <a
                    v-if="props.canExport"
                    :href="exportUrl"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-200 transition hover:bg-slate-800"
                >
                    Exportar CSV
                </a>
            </header>

            <div class="overflow-hidden rounded-xl border border-slate-800">
                <table class="min-w-full divide-y divide-slate-800 text-sm text-slate-100">
                    <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Usuario</th>
                            <th class="px-4 py-3 text-left">Acao</th>
                            <th class="px-4 py-3 text-left">Recurso</th>
                            <th class="px-4 py-3 text-left">Data</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <tr v-if="!hasLogs" class="text-slate-400">
                            <td colspan="4" class="px-4 py-6 text-center">Nenhum log encontrado.</td>
                        </tr>
                        <tr v-else v-for="log in props.logs.data" :key="log.id" class="hover:bg-slate-900/60">
                            <td class="px-4 py-3 text-slate-200">{{ log.user?.username ?? 'Sistema' }}</td>
                            <td class="px-4 py-3 text-slate-200">{{ log.action }}</td>
                            <td class="px-4 py-3 text-slate-200">{{ log.resource ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-200">{{ log.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <footer v-if="props.logs.links.length > 1" class="mt-4 flex flex-wrap items-center justify-center gap-2">
            <button
                v-for="link in props.logs.links"
                :key="link.label"
                type="button"
                class="rounded-md px-3 py-1 text-xs transition"
                :class="link.active ? 'bg-indigo-600 text-white' : link.url ? 'text-slate-300 hover:bg-slate-800' : 'text-slate-600 cursor-default'"
                v-html="link.label"
                @click="handlePagination(link)"
            />
        </footer>
    </AuthenticatedLayout>
</template>

