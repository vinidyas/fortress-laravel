<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TransactionTable from '@/Components/Financeiro/TransactionTable.vue';
import TransactionFormModal from '@/Components/Financeiro/TransactionFormModal.vue';
import type { TransactionRow } from '@/Components/Financeiro/TransactionTable.vue';
import { Head, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useFinanceiroStore } from '@/Stores/financeiro';
import DatePicker from '@/Components/Form/DatePicker.vue';
import axios from '@/bootstrap';
import { useToast } from '@/composables/useToast';

interface AccountOption {
  id: number;
  nome: string;
}

interface CostCenterOption {
  id: number;
  nome: string;
}

interface PersonOption {
  id: number;
  nome: string;
  papeis?: string[] | null;
}

interface TransactionResource {
  data: TransactionRow[];
  links: Array<{ url: string | null; label: string; active: boolean }>;
  meta: {
    per_page?: number;
  };
}

const props = defineProps<{
  entries: TransactionResource;
  accounts: AccountOption[];
  costCenters: CostCenterOption[];
  people: PersonOption[];
  properties: Array<{ id: number; titulo?: string | null; codigo_interno?: string | null }>;
  filters: Record<string, any>;
  totals: { receita: number; despesa: number; saldo: number };
  can: { create: boolean; reconcile: boolean; export: boolean; delete: boolean };
  permissions: { update: boolean; delete: boolean; reconcile: boolean };
}>();

const store = useFinanceiroStore();
const { filters: stateFilters } = storeToRefs(store);
const toast = useToast();

const toIsoDate = (date: Date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const getDefaultMonthRange = () => {
  const today = new Date();
  const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
  return {
    start: toIsoDate(startOfMonth),
    end: toIsoDate(today),
  };
};

const parsePerPage = (value: unknown): number | 'all' => {
  if (value === 'all') {
    return 'all';
  }

  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }

  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    if (Number.isFinite(parsed) && parsed > 0) {
      return parsed;
    }
  }

  return stateFilters.value.perPage ?? 'all';
};

const hydrateFromServer = (source: Record<string, any>) => {
  const { start, end } = getDefaultMonthRange();
  const currentDateFrom = stateFilters.value.dateFrom ?? start;
  const currentDateTo = stateFilters.value.dateTo ?? end;

  const resolvedDateFrom =
    typeof source.data_de === 'string' && source.data_de.trim() !== ''
      ? source.data_de
      : currentDateFrom;
  const resolvedDateTo =
    typeof source.data_ate === 'string' && source.data_ate.trim() !== ''
      ? source.data_ate
      : currentDateTo;

  const perPageSource = source.per_page ?? stateFilters.value.perPage ?? 'all';

  store.setFilters({
    search: source.search ?? '',
    tipo: source.tipo ?? '',
    status: source.status ?? '',
    accountId: source.account_id ? Number(source.account_id) : null,
    costCenterId: source.cost_center_id ? Number(source.cost_center_id) : null,
    dateFrom: resolvedDateFrom,
    dateTo: resolvedDateTo,
    perPage: parsePerPage(perPageSource),
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

type DatePreset = 'today' | 'yesterday' | 'tomorrow' | 'thisWeek' | 'lastWeek' | 'thisMonth';

const datePresets: Array<{ id: DatePreset; label: string }> = [
  { id: 'today', label: 'Hoje' },
  { id: 'yesterday', label: 'Ontem' },
  { id: 'tomorrow', label: 'Amanhã' },
  { id: 'thisWeek', label: 'Essa semana' },
  { id: 'lastWeek', label: 'Semana passada' },
  { id: 'thisMonth', label: 'Esse mês' },
];

const normalizeDate = (date: Date) => new Date(date.getFullYear(), date.getMonth(), date.getDate());

const addDays = (date: Date, amount: number) =>
  new Date(date.getFullYear(), date.getMonth(), date.getDate() + amount);

const formatDate = (date: Date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const resolvePresetRange = (preset: DatePreset) => {
  const today = normalizeDate(new Date());
  let start = today;
  let end = today;

  switch (preset) {
    case 'today':
      break;
    case 'yesterday':
      start = addDays(today, -1);
      end = addDays(today, -1);
      break;
    case 'tomorrow':
      start = addDays(today, 1);
      end = addDays(today, 1);
      break;
    case 'thisWeek': {
      const startOfWeek = addDays(today, -today.getDay());
      start = startOfWeek;
      end = addDays(startOfWeek, 6);
      break;
    }
    case 'thisMonth': {
      start = new Date(today.getFullYear(), today.getMonth(), 1);
      end = today;
      break;
    }
    case 'lastWeek': {
      const startOfCurrentWeek = addDays(today, -today.getDay());
      start = addDays(startOfCurrentWeek, -7);
      end = addDays(start, 6);
      break;
    }
    default:
      break;
  }

  return {
    start: formatDate(start),
    end: formatDate(end),
  };
};

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

const activeDatePreset = computed<DatePreset | null>(() => {
  const from = stateFilters.value.dateFrom;
  const to = stateFilters.value.dateTo;

  if (!from || !to) {
    return null;
  }

  for (const preset of datePresets) {
    const { start, end } = resolvePresetRange(preset.id);
    if (start === from && end === to) {
      return preset.id;
    }
  }

  return null;
});

const applyDatePreset = (preset: DatePreset) => {
  const { start, end } = resolvePresetRange(preset);
  store.setFilters({
    dateFrom: start,
    dateTo: end,
    perPage: preset === 'thisMonth' ? 'all' : stateFilters.value.perPage,
  });
  submitFilters();
};

const perPageModel = computed({
  get: () => (stateFilters.value.perPage === 'all' ? 'all' : String(stateFilters.value.perPage ?? '15')),
  set: (value: string) => {
    if (value === 'all') {
      store.setFilters({ perPage: 'all' });
      return;
    }

    const parsed = Number(value);
    store.setFilters({ perPage: Number.isNaN(parsed) || parsed <= 0 ? 15 : parsed });
  },
});

type ModalMode = 'create' | 'edit';

const modalState = reactive({
  visible: false,
  mode: 'create' as ModalMode,
  transaction: null as any,
  permissions: { ...props.permissions },
});

const openCreateModal = () => {
  modalState.mode = 'create';
  modalState.transaction = null;
  modalState.permissions = { ...props.permissions };
  modalState.visible = true;
};

const closeTransactionModal = () => {
  modalState.visible = false;
  modalState.transaction = null;
};

const refreshTransactions = () => {
  router.get(route('financeiro.index'), store.query, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const handleTransactionCreated = () => {
  modalState.visible = false;
  refreshTransactions();
};

const handleTransactionUpdated = () => {
  modalState.visible = false;
  refreshTransactions();
};

const handleTransactionDeleted = () => {
  modalState.visible = false;
  refreshTransactions();
};

const mapEntryToFormPayload = (entry: any) => ({
  id: entry.id,
  clone_of_id: entry.clone_of_id ?? null,
  account: entry.account ?? null,
  counter_account: entry.counter_account ?? null,
  cost_center: entry.cost_center ?? null,
  property: entry.property ?? null,
  property_label: entry.property_label ?? entry.propertyLabel ?? null,
  propertyLabel: entry.property_label ?? entry.propertyLabel ?? null,
  property_label_mcc: entry.property_label_mcc ?? entry.propertyLabelMcc ?? null,
  propertyLabelMcc: entry.property_label_mcc ?? entry.propertyLabelMcc ?? null,
  person: entry.person ?? null,
  movement_date: entry.movement_date ?? entry.data_ocorrencia ?? null,
  due_date: entry.due_date ?? null,
  payment_date: entry.payment_date ?? entry.due_date ?? entry.movement_date ?? null,
  descricao: entry.description ?? null,
  description: entry.description ?? null,
  description_id: entry.description_id ?? null,
  notes: entry.notes ?? null,
  reference_code: entry.reference_code ?? null,
  tipo: entry.tipo ?? entry.type ?? 'receita',
  valor: entry.valor ?? entry.amount ?? '0',
  status: entry.status ?? entry.status_code ?? 'planejado',
  installments: (entry.installments ?? []).map((installment: any, index: number) => ({
    id: installment.id ?? null,
    numero_parcela: installment.numero_parcela ?? index + 1,
    movement_date: installment.movement_date ?? null,
    due_date: installment.due_date ?? installment.movement_date ?? null,
    payment_date: installment.payment_date ?? installment.due_date ?? null,
    valor_principal: String(installment.valor_principal ?? installment.valor_total ?? 0),
    valor_juros: String(installment.valor_juros ?? 0),
    valor_multa: String(installment.valor_multa ?? 0),
    valor_desconto: String(installment.valor_desconto ?? 0),
    valor_total: String(installment.valor_total ?? 0),
    status: installment.status ?? 'planejado',
    meta: (installment.meta as Record<string, unknown> | null) ?? null,
  })),
  allocations: (entry.allocations ?? []).map((allocation: any) => ({
    cost_center_id: allocation.cost_center_id ?? allocation.cost_center?.id ?? null,
    property_id: allocation.property_id ?? allocation.property?.id ?? null,
    percentage:
      allocation.percentage !== undefined && allocation.percentage !== null
        ? String(allocation.percentage)
        : '',
    amount:
      allocation.amount !== undefined && allocation.amount !== null
        ? String(allocation.amount)
        : '',
  })),
  currency: entry.currency ?? 'BRL',
  attachments: entry.attachments ?? [],
  receipts: entry.receipts ?? [],
});

const prepareClonePayload = (entry: any) => {
  const payload = mapEntryToFormPayload(entry);

  return {
    ...payload,
    id: null,
    clone_of_id: null,
    status: 'planejado',
    payment_date: null,
    installments: (payload.installments ?? []).map((installment: any, index: number) => ({
      ...installment,
      id: null,
      status: 'planejado',
      payment_date: null,
      numero_parcela: installment.numero_parcela ?? index + 1,
      meta: null,
    })),
    attachments: [],
    receipts: [],
  };
};

const openViewModal = async (transaction: TransactionRow | { id: number }) => {
  let transactionId = typeof transaction === 'number' ? transaction : transaction.id;
  if (typeof transaction !== 'number' && transaction.clone_of_id) {
    transactionId = transaction.clone_of_id;
  }
  if (!transactionId) {
    toast.error('Lançamento inválido.');
    return;
  }

  try {
    const { data } = await axios.get(`/api/financeiro/journal-entries/${transactionId}`);
    const entry = data?.data ?? {};

    const payload = mapEntryToFormPayload(entry);
    modalState.mode = 'edit';
    modalState.transaction = payload;
    modalState.permissions = { ...props.permissions };
    modalState.visible = true;
  } catch (error: any) {
    const message = error?.response?.data?.message ?? 'Não foi possível carregar o lançamento.';
    toast.error(message);
  }
};

const openCloneModal = async (transaction: TransactionRow | { id: number }) => {
  let transactionId = typeof transaction === 'number' ? transaction : transaction.id;
  if (typeof transaction !== 'number' && transaction.clone_of_id) {
    transactionId = transaction.clone_of_id;
  }
  if (!transactionId) {
    toast.error('Lançamento inválido para clonagem.');
    return;
  }

  try {
    const { data } = await axios.get(`/api/financeiro/journal-entries/${transactionId}`);
    const source = data?.data ?? {};

    const payload = prepareClonePayload(source);
    modalState.mode = 'create';
    modalState.transaction = payload;
    modalState.permissions = { ...props.permissions };
    modalState.visible = true;
  } catch (error: any) {
    const message = error?.response?.data?.message ?? 'Não foi possível preparar a clonagem.';
    toast.error(message);
  }
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
        <form class="grid gap-4 md:grid-cols-8" @submit.prevent="submitFilters">
          <div class="md:col-span-2 lg:col-span-2">
            <label class="text-xs font-semibold text-slate-400">Busca</label>
            <input
              v-model="stateFilters.search"
              type="search"
              placeholder="Descrição, imóvel, fornecedor, centro de custo, valor"
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
          <div class="md:col-span-2 lg:col-span-1">
            <label class="text-xs font-semibold text-slate-400">Status</label>
            <select
              v-model="stateFilters.status"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 text-sm text-white focus:border-indigo-500 focus:outline-none"
            >
              <option value="">Todos</option>
              <option value="open">Em aberto</option>
              <option value="settled">Quitado</option>
              <option value="overdue">Em atraso</option>
              <option value="cancelled">Cancelado</option>
            </select>
          </div>
          <div class="md:col-span-2 lg:col-span-1">
            <label class="text-xs font-semibold text-slate-400">Tipo</label>
            <select
              v-model="stateFilters.tipo"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 text-sm text-white focus:border-indigo-500 focus:outline-none"
            >
              <option value="">Todos</option>
              <option value="receita">Receita</option>
              <option value="despesa">Despesa</option>
              <option value="transferencia">Transferência</option>
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
              v-model="perPageModel"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 text-sm text-white focus:border-indigo-500 focus:outline-none"
            >
              <option value="all">Todos</option>
              <option value="10">10</option>
              <option value="15">15</option>
              <option value="25">25</option>
              <option value="50">50</option>
            </select>
          </div>
          <div class="md:col-span-3 lg:col-span-3 flex flex-wrap items-end gap-3">
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
          <div class="md:col-span-3 lg:col-span-3 flex flex-wrap items-center justify-end gap-2 self-center">
            <button
              v-for="preset in datePresets"
              :key="preset.id"
              type="button"
              class="rounded-full border border-slate-700 bg-slate-900 px-3 py-1 text-xs font-medium text-slate-200 transition hover:bg-slate-800"
              :class="{
                'border-indigo-500 bg-indigo-600/20 text-indigo-100': activeDatePreset === preset.id,
              }"
              @click="applyDatePreset(preset.id)"
            >
              {{ preset.label }}
            </button>
          </div>
        </form>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Total receitas</p>
            <p class="text-2xl font-semibold text-emerald-300">
              {{ formatCurrency(props.totals.receita) }}
            </p>
          </article>
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Total despesas</p>
            <p class="text-2xl font-semibold text-rose-300">
              {{ formatCurrency(props.totals.despesa) }}
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
        :items="props.entries.data"
        :links="props.entries.links"
        :can="props.can"
        :filters="currentFilters"
        @create="openCreateModal"
        @view="openViewModal"
        @clone="openCloneModal"
      />

      <TransactionFormModal
        :show="modalState.visible"
        :accounts="props.accounts"
        :cost-centers="props.costCenters"
        :people="props.people"
        :properties="props.properties"
        :permissions="modalState.permissions"
        :mode="modalState.mode"
        :transaction="modalState.transaction"
        @created="handleTransactionCreated"
        @updated="handleTransactionUpdated"
        @deleted="handleTransactionDeleted"
        @close="closeTransactionModal"
      />
    </div>
  </AuthenticatedLayout>
</template>

