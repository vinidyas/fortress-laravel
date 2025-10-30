<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { reactive, ref } from 'vue';
import DatePicker from '@/Components/Form/DatePicker.vue';

const props = defineProps<{
  accounts: Array<{ id: number; nome: string }>;
  canExport: boolean;
}>();

const filters = reactive({
  de: '',
  ate: '',
  account_id: '',
  status: '',
});

const loading = ref(false);
const totals = ref<{
  receitas: number;
  despesas: number;
  saldo: number;
  planejado: number;
  pendente: number;
  atrasado: number;
} | null>(null);
const errorMessage = ref('');

const loadReport = async () => {
  loading.value = true;
  errorMessage.value = '';
  try {
    const { data } = await axios.get('/api/reports/financeiro', { params: filters });
    totals.value = data.totals;
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message ?? 'Nao foi possivel carregar o relatorio.';
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
  window.location.href = `/api/reports/financeiro/export?${params.toString()}`;
};
</script>

<template>
  <AuthenticatedLayout title="Relatório financeiro">
    <Head title="Relatório financeiro" />

    <section
      class="space-y-6 rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
    >
      <form class="grid gap-4 md:grid-cols-5" @submit.prevent="loadReport">
        <div>
          <label class="text-xs font-semibold text-slate-400">Período de</label>
          <DatePicker v-model="filters.de" placeholder="dd/mm/aaaa" />
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-400">Período até</label>
          <DatePicker v-model="filters.ate" placeholder="dd/mm/aaaa" />
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-400">Conta</label>
          <select
            v-model="filters.account_id"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white"
          >
            <option value="">Todas</option>
            <option v-for="account in props.accounts" :key="account.id" :value="account.id">
              {{ account.nome }}
            </option>
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-400">Status</label>
          <select
            v-model="filters.status"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white"
          >
            <option value="">Todos</option>
            <option value="planejado">Planejado</option>
            <option value="pendente">Pendente</option>
            <option value="atrasado">Atrasado</option>
            <option value="pago">Pago</option>
            <option value="cancelado">Cancelado</option>
          </select>
        </div>
        <div class="flex items-end gap-2">
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

      <div v-if="totals" class="grid gap-4 md:grid-cols-3">
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Receitas</p>
          <p class="text-2xl font-semibold text-emerald-300">
            {{
              new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(
                totals.receitas ?? 0
              )
            }}
          </p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Despesas</p>
          <p class="text-2xl font-semibold text-rose-300">
            {{
              new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(
                totals.despesas ?? 0
              )
            }}
          </p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Saldo</p>
          <p
            :class="[
              'text-2xl font-semibold',
              (totals.saldo ?? 0) >= 0 ? 'text-emerald-300' : 'text-rose-300',
            ]"
          >
            {{
              new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(
                totals.saldo ?? 0
              )
            }}
          </p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Planejado</p>
          <p class="text-xl font-semibold text-slate-200">
            {{
              new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(
                totals.planejado ?? 0
              )
            }}
          </p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Pendente</p>
          <p class="text-xl font-semibold text-amber-200">
            {{
              new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(
                totals.pendente ?? 0
              )
            }}
          </p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Atrasado</p>
          <p class="text-xl font-semibold text-rose-200">
            {{
              new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(
                totals.atrasado ?? 0
              )
            }}
          </p>
        </article>
      </div>

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
