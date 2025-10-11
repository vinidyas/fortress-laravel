<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { reactive, ref } from 'vue';

const props = defineProps<{
  canExport: boolean;
}>();

const filters = reactive({
  papel: '',
  tipo_pessoa: '',
});

const resumo = ref<{
  total: number;
  por_tipo: Record<string, number>;
  amostra: Array<Record<string, any>>;
} | null>(null);
const loading = ref(false);
const errorMessage = ref('');

const loadReport = async () => {
  loading.value = true;
  errorMessage.value = '';

  try {
    const { data } = await axios.get('/api/reports/pessoas', { params: filters });
    resumo.value = data;
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message ?? 'Não foi possível carregar o relatório.';
  } finally {
    loading.value = false;
  }
};

const exportReport = () => {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value) params.append(key, value);
  });
  params.append('format', 'csv');
  window.location.href = `/api/reports/pessoas/export?${params.toString()}`;
};
</script>

<template>
  <AuthenticatedLayout title="Relatório de pessoas">
    <Head title="Relatório de pessoas" />

    <section
      class="space-y-6 rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
    >
      <form class="grid gap-4 md:grid-cols-3" @submit.prevent="loadReport">
        <div>
          <label class="text-xs font-semibold text-slate-400">Papel</label>
          <input
            v-model="filters.papel"
            type="text"
            placeholder="ex: Proprietário"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white"
          />
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-400">Tipo de pessoa</label>
          <select
            v-model="filters.tipo_pessoa"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white"
          >
            <option value="">Ambos</option>
            <option value="Fisica">Física</option>
            <option value="Juridica">Jurídica</option>
          </select>
        </div>
        <div class="flex items-end">
          <button
            type="submit"
            class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
            :disabled="loading"
          >
            {{ loading ? 'Carregando...' : 'Atualizar' }}
          </button>
        </div>
      </form>

      <p
        v-if="errorMessage"
        class="rounded-lg border border-rose-500/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-200"
      >
        {{ errorMessage }}
      </p>

      <div v-if="resumo" class="grid gap-4 md:grid-cols-3">
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Total</p>
          <p class="text-2xl font-semibold text-white">{{ resumo.total }}</p>
        </article>
        <article
          v-for="(valor, chave) in resumo.por_tipo"
          :key="chave"
          class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm"
        >
          <p class="text-slate-400">{{ chave }}</p>
          <p class="text-2xl font-semibold text-indigo-300">{{ valor }}</p>
        </article>
      </div>

      <section class="rounded-xl border border-slate-800 bg-slate-900/60">
        <header class="border-b border-slate-800 px-4 py-3 text-sm font-semibold text-white">
          Amostra
        </header>
        <div v-if="!resumo || resumo.amostra.length === 0" class="px-4 py-4 text-sm text-slate-400">
          Nenhum registro encontrado.
        </div>
        <div v-else class="divide-y divide-slate-800">
          <article
            v-for="pessoa in resumo.amostra"
            :key="pessoa.id"
            class="grid gap-2 px-4 py-4 text-sm text-slate-200 md:grid-cols-4"
          >
            <p class="font-semibold">{{ pessoa.nome_razao_social }}</p>
            <p>Tipo {{ pessoa.tipo_pessoa }}</p>
            <p class="md:col-span-2">Papéis: {{ (pessoa.papeis ?? []).join(', ') || '-' }}</p>
          </article>
        </div>
      </section>

      <div v-if="props.canExport" class="flex gap-3">
        <button
          type="button"
          class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-800"
          @click="exportReport"
        >
          Exportar CSV
        </button>
      </div>
    </section>
  </AuthenticatedLayout>
</template>
