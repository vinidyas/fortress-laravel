<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ContratoForm from '@/Components/Contratos/ContratoForm.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ contratoId?: number | null }>();

const contratoId = computed(() => props.contratoId ?? null);

const handleSaved = () => {
  router.visit('/contratos', {
    preserveScroll: true,
  });
};

const handleCancel = () => {
  router.visit('/contratos');
};
</script>

<template>
  <AuthenticatedLayout :title="contratoId ? 'Editar contrato' : 'Novo contrato'">
    <Head :title="contratoId ? 'Editar contrato' : 'Novo contrato'" />

    <div class="mb-6 flex items-center justify-between">
      <div>
        <h2 class="text-2xl font-semibold text-white">{{ contratoId ? 'Editar contrato' : 'Novo contrato' }}</h2>
        <p class="text-sm text-slate-400">Atualize os dados do contrato nos campos abaixo.</p>
      </div>
      <Link
        href="/contratos"
        class="rounded-md border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800"
      >
        Voltar
      </Link>
    </div>

    <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
      <ContratoForm mode="edit" :contrato-id="contratoId" @saved="handleSaved" @cancel="handleCancel" />
    </div>
  </AuthenticatedLayout>
</template>
