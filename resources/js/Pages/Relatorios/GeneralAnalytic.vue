<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DatePicker from '@/Components/Form/DatePicker.vue';
import SearchableSelect from '@/Components/Form/SearchableSelect.vue';
import { Head } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { computed, reactive, ref } from 'vue';

interface AccountOption {
  id: number;
  nome: string;
}

type ReportType = 'despesa' | 'receita' | 'todos';
type ReportStatus = 'pago' | 'em_aberto' | 'todos';
type DateBasis = 'movement' | 'due';
type OrderOption = 'movement_date' | 'due_date' | 'person' | 'description' | 'notes' | 'document';

interface FiltersState {
  type: ReportType;
  status: ReportStatus;
  date_basis: DateBasis;
  date_from: string;
  date_to: string;
  description: string;
  person_id: number | null;
  property_id: number | null;
  financial_account_id: number | null;
  cost_center_id: number | null;
  order_by: OrderOption;
  order_desc: boolean;
}

interface ReportRow {
  id: number;
  movement_date?: string | null;
  due_date?: string | null;
  type?: string | null;
  status?: string | null;
  status_label?: string | null;
  description?: string | null;
  notes?: string | null;
  document?: string | null;
  amount?: number;
  signed_amount?: number;
  person?: { id: number; nome: string } | null;
  property?: { id: number; nome: string | null } | null;
  cost_center?: { id: number; nome: string | null; codigo?: string | null } | null;
  bank_account?: { id: number; nome: string | null } | null;
}

const props = defineProps<{
  accounts: AccountOption[];
  people: Array<{ id: number; nome: string }>;
  properties: Array<{ id: number; label: string }>;
  costCenters: Array<{ id: number; nome: string; codigo: string | null; label: string }>;
  canExport: boolean;
}>();

const PREVIEW_LIMIT = 100;

const toIsoDate = (date: Date): string => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const currentMonthRange = () => {
  const now = new Date();
  const start = new Date(now.getFullYear(), now.getMonth(), 1);
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
  return {
    from: toIsoDate(start),
    to: toIsoDate(end),
  };
};

const createDefaultFilters = (): FiltersState => {
  const range = currentMonthRange();
  return {
    type: 'todos',
    status: 'todos',
    date_basis: 'movement',
    date_from: range.from,
    date_to: range.to,
    description: '',
    person_id: null,
    property_id: null,
    financial_account_id: null,
    cost_center_id: null,
    order_by: 'movement_date',
    order_desc: false,
  };
};

const accountOptions = computed(() =>
  props.accounts.map((account) => ({
    value: account.id,
    label: account.nome ?? `Conta ${account.id}`,
  }))
);

const personOptions = computed(() =>
  props.people.map((person) => ({
    value: person.id,
    label: person.nome,
  }))
);

const propertyOptions = computed(() =>
  props.properties.map((property) => ({
    value: property.id,
    label: property.label,
  }))
);

const costCenterOptions = computed(() =>
  props.costCenters.map((center) => ({
    value: center.id,
    label: center.label,
  }))
);

const showModal = ref(false);
const modalError = ref('');
const loading = ref(false);
const errorMessage = ref('');
const rows = ref<ReportRow[]>([]);
const totals = ref({ inflow: 0, outflow: 0, net: 0 });
const totalRows = ref(0);
const activeFilters = ref<FiltersState | null>(null);
const draft = reactive<FiltersState>(createDefaultFilters());

const typeOptions = [
  { value: 'despesa' as ReportType, label: 'Despesas' },
  { value: 'receita' as ReportType, label: 'Receitas' },
  { value: 'todos' as ReportType, label: 'Todos' },
];

const statusOptions = [
  { value: 'pago' as ReportStatus, label: 'Pago' },
  { value: 'em_aberto' as ReportStatus, label: 'Em aberto' },
  { value: 'todos' as ReportStatus, label: 'Todos' },
];

const dateBasisOptions = [
  { value: 'movement' as DateBasis, label: 'Data de movimento' },
  { value: 'due' as DateBasis, label: 'Data de vencimento' },
];

const orderOptions = [
  { value: 'movement_date' as OrderOption, label: 'Data de movimento' },
  { value: 'due_date' as OrderOption, label: 'Data de vencimento' },
  { value: 'person' as OrderOption, label: 'Nome do cliente/fornecedor' },
  { value: 'description' as OrderOption, label: 'Descrição' },
  { value: 'notes' as OrderOption, label: 'Observação' },
  { value: 'document' as OrderOption, label: 'Documento' },
];

const currencyFormatter = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
});

const formatCurrency = (value: number | undefined | null) => currencyFormatter.format(value ?? 0);

const formatDate = (value: string | undefined | null) => {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString('pt-BR');
};

const cloneFilters = (source: FiltersState): FiltersState => ({ ...source });

const resetDraft = (base?: FiltersState) => {
  const target = base ? cloneFilters(base) : createDefaultFilters();
  draft.type = target.type;
  draft.status = target.status;
  draft.date_basis = target.date_basis;
  draft.date_from = target.date_from;
  draft.date_to = target.date_to;
  draft.description = target.description;
  draft.person_id = target.person_id;
  draft.property_id = target.property_id;
  draft.financial_account_id = target.financial_account_id;
  draft.cost_center_id = target.cost_center_id;
  draft.order_by = target.order_by;
  draft.order_desc = target.order_desc;
};

const hasActiveFilters = computed(() => activeFilters.value !== null);
const hasRows = computed(() => rows.value.length > 0);

const activeTypeLabel = computed(() => {
  if (!activeFilters.value) return '—';
  const option = typeOptions.find((item) => item.value === activeFilters.value?.type);
  return option?.label ?? '—';
});

const activeStatusLabel = computed(() => {
  if (!activeFilters.value) return '—';
  const option = statusOptions.find((item) => item.value === activeFilters.value?.status);
  return option?.label ?? '—';
});

const activeDateBasisLabel = computed(() => {
  if (!activeFilters.value) return '—';
  const option = dateBasisOptions.find((item) => item.value === activeFilters.value?.date_basis);
  return option?.label ?? '—';
});

const accountLabel = (id: number | null) => {
  if (!id) return 'Todos os bancos';
  const match = accountOptions.value.find((option) => option.value === id);
  return match?.label ?? 'Conta selecionada';
};

const getPersonLabel = (id: number | null) => {
  if (!id) return 'Todos';
  return personOptions.value.find((person) => person.value === id)?.label ?? 'Todos';
};

const getPropertyLabel = (id: number | null) => {
  if (!id) return 'Todos';
  return propertyOptions.value.find((property) => property.value === id)?.label ?? 'Todos';
};

const getCostCenterLabel = (id: number | null) => {
  if (!id) return 'Todos';
  return costCenterOptions.value.find((center) => center.value === id)?.label ?? 'Todos';
};

const buildParams = (state: FiltersState) => {
  const params: Record<string, string | number | boolean> = {
    date_from: state.date_from,
    date_to: state.date_to,
    date_basis: state.date_basis,
    order_by: state.order_by,
    order_desc: state.order_desc ? 1 : 0,
    preview_limit: PREVIEW_LIMIT,
  };

  if (state.type && state.type !== 'todos') {
    params.type = state.type;
  }

  if (state.status && state.status !== 'todos') {
    params.status = state.status;
  }

  if (state.description) {
    params.description = state.description;
  }

  if (state.person_id) {
    params.person_id = state.person_id;
  }

  if (state.property_id) {
    params.property_id = state.property_id;
  }

  if (state.cost_center_id) {
    params.cost_center_id = state.cost_center_id;
  }

  if (state.financial_account_id) {
    params.financial_account_id = state.financial_account_id;
  }

  return params;
};

const loadReport = async (state: FiltersState) => {
  loading.value = true;
  errorMessage.value = '';
  try {
    const params = buildParams(state);
    const { data } = await axios.get('/api/reports/general-analytic', { params });
    rows.value = Array.isArray(data?.data) ? data.data : [];
    totals.value = data?.totals ?? { inflow: 0, outflow: 0, net: 0 };
    totalRows.value = data?.total_rows ?? rows.value.length;
  } catch (error: any) {
    rows.value = [];
    totals.value = { inflow: 0, outflow: 0, net: 0 };
    totalRows.value = 0;
    errorMessage.value =
      error?.response?.data?.message ?? 'Não foi possível carregar o relatório.';
  } finally {
    loading.value = false;
  }
};

const openModalForNewReport = () => {
  resetDraft();
  modalError.value = '';
  showModal.value = true;
};

const openModalToEdit = () => {
  if (activeFilters.value) {
    resetDraft(activeFilters.value);
  } else {
    resetDraft();
  }
  modalError.value = '';
  showModal.value = true;
};

const applyDraft = async () => {
  if (!draft.date_from || !draft.date_to) {
    modalError.value = 'Informe o período inicial e final.';
    return;
  }

  const from = new Date(draft.date_from);
  const to = new Date(draft.date_to);
  if (from > to) {
    modalError.value = 'A data inicial não pode ser maior que a data final.';
    return;
  }

  modalError.value = '';
  const applied = cloneFilters(draft);
  activeFilters.value = applied;
  showModal.value = false;
  await loadReport(applied);
};

const clearReport = () => {
  activeFilters.value = null;
  rows.value = [];
  totals.value = { inflow: 0, outflow: 0, net: 0 };
  totalRows.value = 0;
  errorMessage.value = '';
  resetDraft();
};

const exportReport = async (format: 'csv' | 'xlsx' | 'pdf') => {
  if (!activeFilters.value) return;
  const params = buildParams(activeFilters.value);
  params.format = format;

  if (format === 'pdf') {
    try {
      const { data, headers } = await axios.get('/api/reports/general-analytic/export', {
        params,
        responseType: 'blob',
      });

      const contentType = headers['content-type'] ?? '';
      if (!contentType.includes('application/pdf')) {
        const reader = new FileReader();
        reader.onload = () => {
          try {
            const parsed = JSON.parse(String(reader.result));
            errorMessage.value = parsed?.message ?? 'Não foi possível gerar o PDF.';
          } catch {
            errorMessage.value = 'Não foi possível gerar o PDF.';
          }
        };
        reader.readAsText(data);
        return;
      }

      const blob = new Blob([data], { type: 'application/pdf' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      setTimeout(() => URL.revokeObjectURL(url), 60_000);
    } catch (error: any) {
      const message =
        error?.response?.data?.message ??
        error?.response?.data?.errors?.format?.[0] ??
        error?.message ??
        'Não foi possível gerar o PDF.';
      errorMessage.value = message;
    }
    return;
  }

  const query = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    query.append(key, String(value));
  });

  const url = `/api/reports/general-analytic/export?${query.toString()}`;
  window.location.href = url;
};
</script>

<template>
  <AuthenticatedLayout title="Relatório Geral Analítico">
    <Head title="Relatório Geral Analítico" />

    <section
      class="space-y-6 rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
    >
      <header class="flex flex-col gap-1">
        <h1 class="text-xl font-semibold text-white">Relatório Geral Analítico</h1>
        <p class="text-sm text-slate-400">
          Combine os lançamentos financeiros com múltiplos filtros e ordenações em um único relatório
          detalhado.
        </p>
      </header>

      <div class="flex flex-wrap gap-3">
        <button
          type="button"
          class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500"
          @click="openModalForNewReport"
        >
          Novo relatório
        </button>
        <button
          v-if="hasActiveFilters"
          type="button"
          class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 transition hover:bg-slate-800"
          @click="openModalToEdit"
        >
          Editar filtros
        </button>
        <button
          v-if="hasActiveFilters"
          type="button"
          class="rounded-lg border border-rose-600/40 px-4 py-2 text-sm text-rose-200 transition hover:bg-rose-600/20"
          @click="clearReport"
        >
          Limpar seleção
        </button>
      </div>

      <div
        v-if="!hasActiveFilters"
        class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/60 px-6 py-10 text-center text-sm text-slate-400"
      >
        Utilize o botão <span class="font-semibold text-slate-200">Novo relatório</span> para escolher os filtros
        (tipo, status, período, descrição, pessoa, imóvel, conta bancária e centro de custo) antes de gerar a visualização.
      </div>

      <template v-else>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Tipo</p>
            <p class="text-lg font-semibold text-white">{{ activeTypeLabel }}</p>
          </article>
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Status</p>
            <p class="text-lg font-semibold text-white">{{ activeStatusLabel }}</p>
          </article>
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Analisado por</p>
            <p class="text-lg font-semibold text-slate-200">
              {{ activeDateBasisLabel }}
            </p>
          </article>
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Período</p>
            <p class="text-lg font-semibold text-slate-200">
              {{
                activeFilters
                  ? `${formatDate(activeFilters.date_from)} - ${formatDate(activeFilters.date_to)}`
                  : '—'
              }}
            </p>
          </article>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Descrição</p>
            <p class="text-lg font-semibold text-slate-200">
              {{ activeFilters?.description ? activeFilters.description : 'Todas' }}
            </p>
          </article>
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Fornecedor/Cliente</p>
            <p class="text-lg font-semibold text-slate-200">
              {{ activeFilters ? getPersonLabel(activeFilters.person_id) : 'Todos' }}
            </p>
          </article>
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Imóvel</p>
            <p class="text-lg font-semibold text-slate-200">
              {{ activeFilters ? getPropertyLabel(activeFilters.property_id) : 'Todos' }}
            </p>
          </article>
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Centro de custo</p>
            <p class="text-lg font-semibold text-slate-200">
              {{ activeFilters ? getCostCenterLabel(activeFilters.cost_center_id) : 'Todos' }}
            </p>
          </article>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Conta bancária</p>
            <p class="text-lg font-semibold text-slate-200">
              {{ activeFilters ? accountLabel(activeFilters.financial_account_id) : 'Todos os bancos' }}
            </p>
          </article>
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Total de entradas</p>
            <p class="text-xl font-semibold text-emerald-300">
              {{ formatCurrency(totals.inflow) }}
            </p>
          </article>
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Total de saídas</p>
            <p class="text-xl font-semibold text-rose-300">
              {{ formatCurrency(totals.outflow) }}
            </p>
          </article>
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Saldo líquido</p>
            <p
              :class="[
                'text-2xl font-semibold',
                totals.net >= 0 ? 'text-emerald-300' : 'text-rose-300',
              ]"
            >
              {{ formatCurrency(totals.net) }}
            </p>
          </article>
        </div>

        <p v-if="errorMessage" class="rounded-lg border border-rose-500/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-200">
          {{ errorMessage }}
        </p>

        <div class="overflow-hidden rounded-2xl border border-slate-800">
          <table class="min-w-full table-fixed divide-y divide-slate-800 text-sm text-slate-100">
            <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th class="px-4 py-3 text-left">Movimento</th>
                <th class="px-4 py-3 text-left">Vencimento</th>
                <th class="px-4 py-3 text-left">Tipo</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-left">Fornecedor / Cliente</th>
                <th class="px-4 py-3 text-left">Descrição</th>
                <th class="px-4 py-3 text-left">Observação</th>
                <th class="px-4 py-3 text-left">Imóvel</th>
                <th class="px-4 py-3 text-left">Centro de custo</th>
                <th class="px-4 py-3 text-left">Conta bancária</th>
                <th class="px-4 py-3 text-left">Documento</th>
                <th class="px-4 py-3 text-right">Valor</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
              <tr v-if="loading">
                <td colspan="12" class="px-6 py-8 text-center text-slate-400">Carregando dados...</td>
              </tr>
              <tr v-else-if="!hasRows">
                <td colspan="12" class="px-6 py-8 text-center text-slate-400">
                  Nenhum lançamento encontrado para os filtros selecionados.
                </td>
              </tr>
              <tr v-for="row in rows" :key="row.id">
                <td class="px-4 py-3 text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                  {{ formatDate(row.movement_date) }}
                </td>
                <td class="px-4 py-3 text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                  {{ formatDate(row.due_date) }}
                </td>
                <td class="px-4 py-3 text-slate-200 whitespace-nowrap overflow-hidden text-ellipsis">
                  {{ row.type ? row.type.charAt(0).toUpperCase() + row.type.slice(1) : '—' }}
                </td>
                <td class="px-4 py-3 text-xs text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                  {{ row.status_label ?? '—' }}
                </td>
                <td class="px-4 py-3 text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                  {{ row.person?.nome ?? '—' }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap overflow-hidden text-ellipsis">
                  <span class="font-semibold text-white">{{ row.description ?? '—' }}</span>
                </td>
                <td class="px-4 py-3 text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                  {{ row.notes ?? '—' }}
                </td>
                <td class="px-4 py-3 text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                  {{ row.property?.nome ?? '—' }}
                </td>
                <td class="px-4 py-3 text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                  {{
                    row.cost_center
                      ? row.cost_center.codigo
                        ? `${row.cost_center.codigo} • ${row.cost_center.nome ?? ''}`
                        : row.cost_center.nome
                      : '—'
                  }}
                </td>
                <td class="px-4 py-3 text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                  {{ row.bank_account?.nome ?? '—' }}
                </td>
                <td class="px-4 py-3 text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                  {{ row.document ?? '—' }}
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap overflow-hidden text-ellipsis">
                  {{ formatCurrency(row.signed_amount ?? row.amount ?? 0) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <p v-if="totalRows > rows.length" class="mt-3 text-xs text-slate-400">
          Exibindo os primeiros {{ rows.length }} de {{ totalRows }} lançamentos. Utilize a exportação para consultar todos os registros.
        </p>

        <div v-if="props.canExport && hasRows" class="flex flex-wrap gap-3">
          <button
            type="button"
            class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-800"
            @click="exportReport('csv')"
          >
            Exportar CSV
          </button>
          <button
            type="button"
            class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-800"
            @click="exportReport('xlsx')"
          >
            Exportar XLSX
          </button>
          <button
            type="button"
            class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-800"
            @click="exportReport('pdf')"
          >
            Exportar PDF
          </button>
        </div>
      </template>
    </section>

    <transition name="fade">
      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-3 py-5 backdrop-blur"
        @keydown.esc.prevent.stop="showModal = false"
      >
        <div class="relative w-full max-w-4xl px-[1.5px] pb-[1.5px]">
          <div
            class="pointer-events-none absolute inset-0 -z-10 rounded-[28px] bg-gradient-to-br from-indigo-500/30 via-purple-500/20 to-emerald-400/25 blur-xl"
          ></div>
          <div
            class="relative max-h-[80vh] overflow-y-auto rounded-[26px] border border-white/10 bg-slate-950/85 shadow-[0_25px_60px_-25px_rgba(15,23,42,0.85)] backdrop-blur-2xl"
          >
            <header
              class="flex items-center justify-between gap-4 border-b border-white/5 px-6 py-5"
            >
              <div class="space-y-1.5">
                <span class="inline-flex items-center gap-2 rounded-full bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-indigo-200">
                  <span class="h-1.5 w-1.5 rounded-full bg-indigo-400"></span>
                  Relatórios
                </span>
                <h2 class="text-2xl font-semibold leading-tight text-white">
                  Configurar Relatório Geral Analítico
                </h2>
                <p class="max-w-xl text-sm text-slate-300/80">
                  Defina os filtros desejados e gere uma pré-visualização antes de exportar.
                </p>
              </div>
              <button
                type="button"
                class="rounded-full border border-white/10 p-2 text-slate-300 transition hover:border-white/30 hover:text-white"
                @click="showModal = false"
              >
                <span class="sr-only">Fechar</span>
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor">
                  <path d="M6 6l8 8M6 14l8-8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </button>
            </header>

            <form class="space-y-6 px-6 py-6" @submit.prevent="applyDraft">
              <section class="space-y-4">
                <h3 class="text-lg font-semibold text-white">Tipo e status</h3>
                <div class="flex flex-col gap-4">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                      Tipo do lançamento
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                      <label
                        v-for="option in typeOptions"
                        :key="option.value"
                        class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2 text-sm text-slate-200 transition hover:border-white/25 hover:bg-white/10"
                        :class="{ 'border-indigo-400/70 bg-indigo-500/20 text-white shadow-[0_8px_24px_-12px_rgba(99,102,241,0.9)]': draft.type === option.value }"
                      >
                        <input
                          type="radio"
                          class="h-3.5 w-3.5 accent-indigo-500"
                          name="report-type"
                          :value="option.value"
                          v-model="draft.type"
                        />
                        <span>{{ option.label }}</span>
                      </label>
                    </div>
                  </div>

                  <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                      Status do lançamento
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                      <label
                        v-for="option in statusOptions"
                        :key="option.value"
                        class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2 text-sm text-slate-200 transition hover:border-white/25 hover:bg-white/10"
                        :class="{ 'border-indigo-400/70 bg-indigo-500/20 text-white shadow-[0_8px_24px_-12px_rgba(99,102,241,0.9)]': draft.status === option.value }"
                      >
                        <input
                          type="radio"
                          class="h-3.5 w-3.5 accent-indigo-500"
                          name="report-status"
                          :value="option.value"
                          v-model="draft.status"
                        />
                        <span>{{ option.label }}</span>
                      </label>
                    </div>
                  </div>
                </div>
              </section>

              <section class="space-y-4">
                <h3 class="text-lg font-semibold text-white">Período e análise</h3>
                <div class="flex flex-col gap-4">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Analisar por</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                      <label
                        v-for="option in dateBasisOptions"
                        :key="option.value"
                        class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2 text-sm text-slate-200 transition hover:border-white/25 hover:bg-white/10"
                        :class="{ 'border-indigo-400/70 bg-indigo-500/20 text-white shadow-[0_8px_24px_-12px_rgba(99,102,241,0.9)]': draft.date_basis === option.value }"
                      >
                        <input
                          type="radio"
                          class="h-3.5 w-3.5 accent-indigo-500"
                          name="date-basis"
                          :value="option.value"
                          v-model="draft.date_basis"
                        />
                        <span>{{ option.label }}</span>
                      </label>
                    </div>
                  </div>
                  <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                      <label class="text-xs font-semibold text-slate-400">Data inicial</label>
                      <DatePicker v-model="draft.date_from" placeholder="dd/mm/aaaa" />
                    </div>
                    <div>
                      <label class="text-xs font-semibold text-slate-400">Data final</label>
                      <DatePicker v-model="draft.date_to" placeholder="dd/mm/aaaa" />
                    </div>
                  </div>
                </div>
              </section>
              <section class="space-y-4">
                <h3 class="text-lg font-semibold text-white">Filtros adicionais</h3>
                <div class="grid gap-4 md:grid-cols-2">
                  <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold text-slate-400">Descrição</label>
                    <input
                      v-model="draft.description"
                      type="text"
                      placeholder="Buscar por descrição"
                      class="mt-1 w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
                    />
                  </div>
                  <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold text-slate-400">Fornecedor / Cliente</label>
                    <SearchableSelect
                      v-model="draft.person_id"
                      :options="personOptions"
                      placeholder="Todos"
                      empty-label="Todos"
                      open-strategy="typing"
                      :show-toggle="false"
                      appearance="dark"
                    />
                  </div>
                  <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold text-slate-400">Imóvel</label>
                    <SearchableSelect
                      v-model="draft.property_id"
                      :options="propertyOptions"
                      placeholder="Todos"
                      empty-label="Todos"
                      appearance="dark"
                    />
                  </div>
                  <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold text-slate-400">Centro de custo</label>
                    <SearchableSelect
                      v-model="draft.cost_center_id"
                      :options="costCenterOptions"
                      placeholder="Todos"
                      empty-label="Todos"
                      appearance="dark"
                    />
                  </div>
                </div>
              </section>

              <section class="space-y-4">
                <h3 class="text-lg font-semibold text-white">Conta bancária</h3>
                <div class="flex flex-col gap-2">
                  <label class="text-xs font-semibold text-slate-400">Conta bancária</label>
                  <SearchableSelect
                    v-model="draft.financial_account_id"
                    :options="accountOptions"
                    placeholder="Todos os bancos"
                    empty-label="Todos os bancos"
                    appearance="dark"
                  />
                  <p class="text-xs text-slate-400">
                    Deixe sem seleção para considerar todas as contas cadastradas.
                  </p>
                </div>
              </section>

              <section class="space-y-3">
                <h3 class="text-lg font-semibold text-white">Ordenação</h3>
                <div class="flex flex-wrap items-center gap-3">
                  <div class="w-full sm:w-1/2 lg:w-1/3">
                    <label class="text-xs font-semibold text-slate-400">Ordenar por</label>
                    <select
                      v-model="draft.order_by"
                      class="mt-1 w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
                    >
                      <option v-for="option in orderOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                      </option>
                    </select>
                  </div>
                  <label
                    class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs text-slate-200 transition hover:border-white/25 hover:bg-white/10"
                  >
                    <input type="checkbox" v-model="draft.order_desc" class="h-3.5 w-3.5 accent-indigo-500" />
                    <span>Ordem decrescente</span>
                  </label>
                </div>
              </section>

              <p v-if="modalError" class="rounded-lg border border-rose-500/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-200">
                {{ modalError }}
              </p>

              <footer class="flex flex-wrap items-center justify-end gap-3 border-t border-white/5 pt-5">
                <button
                  type="button"
                  class="rounded-lg border border-white/15 px-4 py-2 text-sm text-slate-200 transition hover:border-white/30 hover:bg-white/10"
                  @click="showModal = false"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500"
                >
                  Aplicar filtros
                </button>
              </footer>
            </form>
          </div>
        </div>
      </div>
    </transition>
  </AuthenticatedLayout>
</template>
