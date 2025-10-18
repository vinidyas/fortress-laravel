<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { reactive, ref } from 'vue';
import DatePicker from '@/Components/Form/DatePicker.vue';

const props = defineProps<{
  condominios: Array<{ id: number; nome: string }>;
  canExport: boolean;
}>();

const filters = reactive({
  cidade: '',
  condominio_id: '',
  status_contrato: '',
  ate: '',
});

const ocupacao = ref<{
  total: number;
  disponiveis: number;
  indisponiveis: number;
  ocupacao_percentual: number;
} | null>(null);
const contratos = ref<Array<Record<string, any>>>([]);
const loading = ref(false);
const errorMessage = ref('');

const loadReport = async () => {
  loading.value = true;
  errorMessage.value = '';
  try {
    const { data } = await axios.get('/api/reports/operacional', { params: filters });
    ocupacao.value = data.ocupacao;
    contratos.value = data.contratos_vencendo ?? [];
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
  window.location.href = `/api/reports/operacional/export?${params.toString()}`;
};
</script>

<template>
  <AuthenticatedLayout title="Relatório operacional">
    <Head title="Relatório operacional" />

    <section
      class="space-y-6 rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
    >
      <form class="grid gap-4 md:grid-cols-4" @submit.prevent="loadReport">
        <div>
          <label class="text-xs font-semibold text-slate-400">Cidade</label>
          <input
            v-model="filters.cidade"
            type="text"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white"
          />
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-400">Condominio</label>
          <select
            v-model="filters.condominio_id"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white"
          >
            <option value="">Todos</option>
            <option
              v-for="condominio in props.condominios"
              :key="condominio.id"
              :value="condominio.id"
            >
              {{ condominio.nome }}
            </option>
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-400">Status do contrato</label>
          <select
            v-model="filters.status_contrato"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white"
          >
            <option value="">Ativos</option>
            <option value="Ativo">Ativo</option>
            <option value="Suspenso">Suspenso</option>
            <option value="Encerrado">Encerrado</option>
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-400">Contratos ate</label>
          <DatePicker v-model="filters.ate" placeholder="dd/mm/aaaa" />
        </div>
        <div class="md:col-span-4 flex items-end">
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

      <div v-if="ocupacao" class="grid gap-4 md:grid-cols-4">
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Imoveis</p>
          <p class="text-2xl font-semibold text-white">{{ ocupacao.total }}</p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Disponiveis</p>
          <p class="text-2xl font-semibold text-emerald-300">{{ ocupacao.disponiveis }}</p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Indisponiveis</p>
          <p class="text-2xl font-semibold text-rose-300">{{ ocupacao.indisponiveis }}</p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Ocupacao</p>
          <p class="text-2xl font-semibold text-indigo-300">{{ ocupacao.ocupacao_percentual }}%</p>
        </article>
      </div>

      <section class="rounded-xl border border-slate-800 bg-slate-900/60">
        <header class="border-b border-slate-800 px-4 py-3 text-sm font-semibold text-white">
          Contratos vencendo
        </header>
        <div v-if="contratos.length === 0" class="px-4 py-4 text-sm text-slate-400">
          Nenhum contrato proximo do vencimento.
        </div>
        <div
          v-for="contrato in contratos"
          :key="contrato.id"
          class="flex flex-col gap-2 border-t border-slate-800 px-4 py-4 text-sm text-slate-200 md:flex-row md:items-center md:justify-between"
        >
          <div>
            <p class="font-semibold">Contrato {{ contrato.contrato ?? '-' }}</p>
            <p class="text-xs text-slate-400">
              Imóvel {{ contrato.imovel ?? '-' }} - {{ contrato.cidade ?? '-' }}
            </p>
          </div>
          <div class="text-sm text-slate-300">Termina em {{ contrato.data_fim ?? '-' }}</div>
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
