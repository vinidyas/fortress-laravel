<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    centers: {
        data: Array<{ id: number; nome: string; descricao: string | null }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    can: { create: boolean; update: boolean; delete: boolean };
}>();
</script>

<template>
    <AuthenticatedLayout title="Centros de custo">
        <Head title="Centros de custo" />

        <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
            <header class="mb-4 flex items-center justify-between">
                <div>
                    <h1 class="text-lg font-semibold text-white">Centros de custo</h1>
                    <p class="text-sm text-slate-400">Categorias para agrupar lançamentos financeiros.</p>
                </div>
                <button
                    v-if="props.can.create"
                    type="button"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500"
                >
                    Novo centro
                </button>
            </header>

            <div class="overflow-hidden rounded-xl border border-slate-800">
                <table class="min-w-full divide-y divide-slate-800 text-sm text-slate-100">
                    <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Nome</th>
                            <th class="px-4 py-3 text-left">Descrição</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <tr v-if="props.centers.data.length === 0">
                            <td colspan="2" class="px-4 py-6 text-center text-slate-400">Nenhum centro cadastrado.</td>
                        </tr>
                        <tr v-for="center in props.centers.data" :key="center.id">
                            <td class="px-4 py-3 text-white">{{ center.nome }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ center.descricao ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
