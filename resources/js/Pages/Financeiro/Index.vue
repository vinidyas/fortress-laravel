<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TransactionTable from '@/Components/Financeiro/TransactionTable.vue';
import type { TransactionRow } from '@/Components/Financeiro/TransactionTable.vue';
import { Head, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { computed, onMounted, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useFinanceiroStore } from '@/Stores/financeiro';
import DatePicker from '@/Components/Form/DatePicker.vue';

interface AccountOption {
  id: number;
  nome: string;
}

interface CostCenterOption {
  id: number;
  nome: string;
}

interface TransactionResource {
  data: TransactionRow[];
  links: Array<{ url: string | null; label: string; active: boolean }>;
  meta: {
    per_page?: number;
  };
}

const props = defineProps<{
  transactions: TransactionResource;
  accounts: AccountOption[];
  costCenters: CostCenterOption[];
  filters: Record<string, any>;
  totals: { credito: number; debito: number; saldo: number };
  can: { create: boolean; reconcile: boolean; export: boolean };
}>();

const store = useFinanceiroStore();
const { filters: stateFilters } = storeToRefs(store);

const hydrateFromServer = (source: Record<string, any>) => {
  store.setFilters({
    search: source.search ?? '',
    tipo: source.tipo ?? '',
    status: source.status ?? '',
    accountId: source.account_id ? Number(source.account_id) : null,
    costCenterId: source.cost_center_id ? Number(source.cost_center_id) : null,
    dateFrom: source.data_de ?? null,
    dateTo: source.data_ate ?? null,
    perPage: props.transactions.meta?.per_page ?? stateFilters.value.perPage ?? 15,
  });
};

onMounted(() => {
  hydrateFromServer(props.filters ?? {});
});

watch(
  () => props.filters,
  (value) => {
    hydrateFromServer(value ?? {});
  }
);

type SelectModel<T> = T | '' | undefined | null | string;

watch(
  () => stateFilters.value.accountId as SelectModel<number>,
  (value) => {
    if (value === '' || value === undefined) {
      store.setFilters({ accountId: null });
      return;
    }

    if (typeof value === 'string') {
      const parsed = Number(value);
      store.setFilters({ accountId: Number.isNaN(parsed) ? null : parsed });
    }
  }
);

watch(
  () => stateFilters.value.costCenterId as SelectModel<number>,
  (value) => {
    if (value === '' || value === undefined) {
      store.setFilters({ costCenterId: null });
      return;
    }

    if (typeof value === 'string') {
      const parsed = Number(value);
      store.setFilters({ costCenterId: Number.isNaN(parsed) ? null : parsed });
    }
  }
);

const submitFilters = () => {
  router.get(route('financeiro.index'), store.query, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  store.resetFilters();
  submitFilters();
};

const formatCurrency = (value: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value ?? 0);

const saldoClasses = computed(() =>
  props.totals.saldo >= 0 ? 'text-emerald-300' : 'text-rose-300'
);

const currentFilters = computed<Record<string, string | number | null>>(() => ({
  ...store.query,
}));

watch(
  () => stateFilters.value.dateFrom,
  (value) => {
    if (value === '') {
      store.setFilters({ dateFrom: null });
    }
  }
);

watch(
  () => stateFilters.value.dateTo,
  (value) => {
    if (value === '') {
      store.setFilters({ dateTo: null });
    }
  }
);
</script>

<template>
  <AuthenticatedLayout title="Financeiro">
    <Head title="Financeiro" />

    <div class="space-y-6">
      <section
        class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
      >
        <form class="grid gap-4 md:grid-cols-7" @submit.prevent="submitFilters">
          <div class="md:col-span-2">
            <label class="text-xs font-semibold text-slate-400">Busca</label>
            <input
              v-model="stateFilters.search"
              type="search"
              placeholder="Descrição, observação"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
            />
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-400">Conta</label>
            <select
              v-model="stateFilters.accountId"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 text-sm text-white focus:border-indigo-500 focus:outline-none"
            >
              <option :value="null">Todas</option>
              <option v-for="account in props.accounts" :key="account.id" :value="account.id">
                {{ account.nome }}
              </option>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-400">Centro de custo</label>
            <select
              v-model="stateFilters.costCenterId"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 text-sm text-white focus:border-indigo-500 focus:outline-none"
            >
              <option :value="null">Todos</option>
              <option v-for="center in props.costCenters" :key="center.id" :value="center.id">
                {{ center.nome }}
              </option>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-400">Status</label>
            <select
              v-model="stateFilters.status"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 text-sm text-white focus:border-indigo-500 focus:outline-none"
            >
              <option value="">Todos</option>
              <option value="pendente">Pendente</option>
              <option value="conciliado">Conciliado</option>
              <option value="cancelado">Cancelado</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-400">Tipo</label>
            <select
              v-model="stateFilters.tipo"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 text-sm text-white focus:border-indigo-500 focus:outline-none"
            >
              <option value="">Todos</option>
              <option value="credito">Crédito</option>
              <option value="debito">Débito</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-400">Data (de)</label>
            <DatePicker
              v-model="stateFilters.dateFrom"
              placeholder="dd/mm/aaaa"
            />
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-400">Data (até)</label>
            <DatePicker
              v-model="stateFilters.dateTo"
              placeholder="dd/mm/aaaa"
            />
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-400">Registros / página</label>
            <select
              v-model.number="stateFilters.perPage"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 text-sm text-white focus:border-indigo-500 focus:outline-none"
            >
              <option :value="10">10</option>
              <option :value="15">15</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
            </select>
          </div>
          <div class="md:col-span-2 flex items-end gap-3">
            <button
              type="submit"
              class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500"
            >
              Aplicar filtros
            </button>
            <button
              type="button"
              class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-800"
              @click="resetFilters"
            >
              Limpar
            </button>
          </div>
        </form>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Total créditos</p>
            <p class="text-2xl font-semibold text-emerald-300">
              {{ formatCurrency(props.totals.credito) }}
            </p>
          </article>
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Total débitos</p>
            <p class="text-2xl font-semibold text-rose-300">
              {{ formatCurrency(props.totals.debito) }}
            </p>
          </article>
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Saldo</p>
            <p :class="['text-2xl font-semibold', saldoClasses]">
              {{ formatCurrency(props.totals.saldo) }}
            </p>
          </article>
        </div>
      </section>

      <TransactionTable
        :items="props.transactions.data"
        :links="props.transactions.links"
        :can="props.can"
        :filters="currentFilters"
      />
    </div>
  </AuthenticatedLayout>
</template>

