<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import axios from '@/bootstrap';
import { useToast } from '@/composables/useToast';
import DatePicker from '@/Components/Form/DatePicker.vue';

interface AccountOption {
  id: number;
  nome: string;
}

interface StatementCounts {
  total?: number | null;
  pending?: number | null;
  confirmed?: number | null;
  suggested?: number | null;
  ignored?: number | null;
}

interface StatementTotals {
  inflow?: number | null;
  outflow?: number | null;
  net?: number | null;
}

interface StatementSummary {
  id: number;
  financial_account_id: number;
  reference: string;
  original_name: string;
  imported_at?: string | null;
  imported_at_formatted?: string | null;
  status: string;
  status_code?: string | null;
  status_label?: string | null;
  status_category?: 'processing' | 'open' | 'reconciled' | 'error' | string | null;
  counts: StatementCounts;
  totals?: StatementTotals | null;
  balances?: {
    opening?: number | null;
    closing?: number | null;
  } | null;
  account?: { id: number; nome: string };
  imported_by?: { id: number; name: string } | null;
  meta?: Record<string, any>;
}

interface StatementLineSuggestion {
  installment_id: number;
  journal_entry_id: number;
  confidence: number;
  journal_entry_description?: string | null;
  installment_due_date?: string | null;
  installment_number?: number | null;
}

interface StatementLine {
  id: number;
  linha: number;
  transaction_date?: string | null;
  transaction_date_formatted?: string | null;
  description: string | null;
  amount: number;
  amount_abs?: number | null;
  direction?: 'credit' | 'debit' | string | null;
  is_credit?: boolean;
  is_debit?: boolean;
  balance?: number | null;
  match_status: 'nao_casado' | 'sugerido' | 'confirmado' | 'ignorado';
  match_status_code?: string | null;
  match_status_label?: string | null;
  match_status_category?: 'open' | 'suggested' | 'matched' | 'ignored' | string | null;
  match_meta: {
    suggestions?: StatementLineSuggestion[];
    [key: string]: any;
  };
  matched_installment?: {
    id: number;
    journal_entry_id: number;
    numero_parcela: number;
    valor_total: number;
    status: string;
    status_label?: string | null;
  } | null;
  journal_entry?: {
    id: number;
    description?: string | null;
    status: string;
    type: string;
  } | null;
}

interface StatementDetails {
  id: number;
  financial_account_id: number;
  reference: string;
  status: string;
  status_label?: string | null;
  status_category?: 'processing' | 'open' | 'reconciled' | 'error' | string | null;
  imported_at?: string | null;
  imported_at_formatted?: string | null;
  totals?: StatementTotals | null;
  balances?: {
    opening?: number | null;
    closing?: number | null;
  } | null;
  lines: StatementLine[];
  meta?: Record<string, any>;
  account?: { id: number; nome: string } | null;
}

interface StatementPagination<T> {
  data: T[];
  links: Array<{ url: string | null; label: string; active: boolean }>;
  meta: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
}

interface ReconciliationSummary {
  id: number;
  financial_account_id: number;
  period_start?: string | null;
  period_end?: string | null;
  opening_balance: number;
  closing_balance: number;
  status: string;
  created_at?: string | null;
}

const props = defineProps<{
  accounts: AccountOption[];
  statements: StatementPagination<StatementSummary>;
  reconciliations: ReconciliationSummary[];
  filters: Record<string, any>;
  can: { upload: boolean; reconcile: boolean };
}>();

const toast = useToast();

const filters = reactive({
  accountId: props.filters.financial_account_id ?? null,
  status: props.filters.status ?? '',
  reference: props.filters.reference ?? '',
  perPage: props.statements.meta?.per_page ?? 15,
});

const reconciliationRoute = () => {
  try {
    return route('financeiro.reconciliation');
  } catch {
    return '/financeiro/conciliacao';
  }
};

const filterStatuses = [
  { value: '', label: 'Todos' },
  { value: 'open', label: 'Pendentes' },
  { value: 'importado', label: 'Importado' },
  { value: 'processando', label: 'Processando' },
  { value: 'conciliado', label: 'Conciliado' },
  { value: 'erro', label: 'Com erro' },
];

const formatCurrency = (value?: number | string | null) => {
  const numeric = typeof value === 'string' ? Number.parseFloat(value) : value ?? 0;
  if (Number.isNaN(numeric) || !Number.isFinite(numeric as number)) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(0);
  }

  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(numeric as number);
};

const resolveStatementStatusClasses = (category?: string | null) => {
  switch (category) {
    case 'reconciled':
      return 'bg-emerald-500/10 text-emerald-200 border border-emerald-400/40';
    case 'processing':
      return 'bg-sky-500/10 text-sky-200 border border-sky-400/40';
    case 'error':
      return 'bg-rose-500/10 text-rose-200 border border-rose-400/40';
    default:
      return 'bg-amber-500/10 text-amber-200 border border-amber-400/40';
  }
};

const resolveLineStatusClasses = (category?: string | null) => {
  switch (category) {
    case 'matched':
      return 'text-emerald-300';
    case 'suggested':
      return 'text-amber-300';
    case 'ignored':
      return 'text-rose-300';
    default:
      return 'text-slate-200';
  }
};

const submitFilters = () => {
  router.get(
    reconciliationRoute(),
    {
      'filter[financial_account_id]': filters.accountId || null,
      'filter[status]': filters.status || null,
      'filter[reference]': filters.reference || null,
      per_page: filters.perPage,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    },
  );
};

const resetFilters = () => {
  filters.accountId = null;
  filters.status = '';
  filters.reference = '';
  filters.perPage = 15;
  submitFilters();
};

const paginationLinks = computed(() => props.statements.links ?? []);

const handlePaginate = (link: { url: string | null }) => {
  if (!link.url) {
    return;
  }

  router.visit(link.url, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  });
};

// Upload modal state
const uploadModalVisible = ref(false);
const uploadForm = reactive<{ accountId: number | null; file: File | null; loading: boolean }>({
  accountId: filters.accountId,
  file: null,
  loading: false,
});

const openUploadModal = () => {
  uploadForm.accountId = filters.accountId;
  uploadForm.file = null;
  uploadForm.loading = false;
  uploadModalVisible.value = true;
};

const closeUploadModal = () => {
  if (uploadForm.loading) return;
  uploadModalVisible.value = false;
};

const handleFileInput = (event: Event) => {
  const input = event.target as HTMLInputElement;
  uploadForm.file = input.files?.[0] ?? null;
};

const submitUpload = async () => {
  if (!uploadForm.accountId || !uploadForm.file) {
    toast.error('Selecione a conta financeira e o arquivo do extrato.');
    return;
  }

  uploadForm.loading = true;
  const payload = new FormData();
  payload.append('financial_account_id', String(uploadForm.accountId));
  payload.append('file', uploadForm.file);

  try {
    await axios.post('/api/financeiro/bank-statements', payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    toast.success('Extrato importado com sucesso.');
    uploadModalVisible.value = false;
    submitFilters();
  } catch (error: any) {
    const message = error?.response?.data?.message ?? 'Falha ao importar o extrato.';
    toast.error(message);
  } finally {
    uploadForm.loading = false;
  }
};

// Statement detail handling
const statementDetails = ref<StatementDetails | null>(null);
const loadingStatement = ref(false);
const selectedStatementId = ref<number | null>(null);

const loadStatementDetails = async (statementId: number) => {
  selectedStatementId.value = statementId;
  loadingStatement.value = true;
  try {
    const { data } = await axios.get(`/api/financeiro/bank-statements/${statementId}`);
    statementDetails.value = data?.data ?? null;
  } catch (error: any) {
    toast.error(error?.response?.data?.message ?? 'Não foi possível carregar o extrato.');
    statementDetails.value = null;
  } finally {
    loadingStatement.value = false;
  }
};

const openStatementDetails = async (statement: StatementSummary) => {
  await loadStatementDetails(statement.id);
};

const closeStatementDetails = () => {
  if (loadingStatement.value) return;
  selectedStatementId.value = null;
  statementDetails.value = null;
};

const runSuggestions = async (statementId: number) => {
  try {
    await axios.post(`/api/financeiro/bank-statements/${statementId}/suggest-matches`);
    toast.success('Sugestões atualizadas.');
    if (statementDetails.value?.id === statementId) {
      await loadStatementDetails(statementId);
    }
    submitFilters();
  } catch (error: any) {
    toast.error(error?.response?.data?.message ?? 'Não foi possível gerar sugestões.');
  }
};

const confirmingLineId = ref<number | null>(null);
const ignoringLineId = ref<number | null>(null);

const confirmLine = async (line: StatementLine, suggestion: StatementLineSuggestion | null) => {
  if (!statementDetails.value) return;

  const installmentId = suggestion?.installment_id ?? line.matched_installment?.id;
  if (!installmentId) {
    toast.error('Selecione uma parcela para confirmar o match.');
    return;
  }

  confirmingLineId.value = line.id;

  try {
    const payload = {
      installment_id: installmentId,
      payment_date: line.transaction_date ?? new Date().toISOString().slice(0, 10),
    };
    const { data } = await axios.post(
      `/api/financeiro/bank-statements/${statementDetails.value.id}/lines/${line.id}/confirm`,
      payload,
    );

    const updated = data?.data;
    statementDetails.value.lines = statementDetails.value.lines.map((item) =>
      item.id === updated.id ? updated : item,
    );
    toast.success('Lançamento conciliado.');
    submitFilters();
  } catch (error: any) {
    toast.error(error?.response?.data?.message ?? 'Não foi possível confirmar a conciliação.');
  } finally {
    confirmingLineId.value = null;
  }
};

const ignoreLine = async (line: StatementLine, reason?: string) => {
  if (!statementDetails.value) return;
  ignoringLineId.value = line.id;

  try {
    const payload = reason ? { reason } : {};
    const { data } = await axios.post(
      `/api/financeiro/bank-statements/${statementDetails.value.id}/lines/${line.id}/ignore`,
      payload,
    );
    const updated = data?.data;
    statementDetails.value.lines = statementDetails.value.lines.map((item) =>
      item.id === updated.id ? updated : item,
    );
    toast.success('Linha marcada como ignorada.');
    submitFilters();
  } catch (error: any) {
    toast.error(error?.response?.data?.message ?? 'Não foi possível ignorar a linha.');
  } finally {
    ignoringLineId.value = null;
  }
};

// Reconciliation closing form
const closingForm = reactive({
  accountId: filters.accountId ?? null,
  periodStart: '',
  periodEnd: '',
  openingBalance: '',
  closingBalance: '',
  statementIds: [] as number[],
  loading: false,
});

const toggleStatementSelection = (statementId: number) => {
  const index = closingForm.statementIds.indexOf(statementId);
  if (index >= 0) {
    closingForm.statementIds.splice(index, 1);
  } else {
    closingForm.statementIds.push(statementId);
  }
};

const submitClosing = async () => {
  if (!closingForm.accountId) {
    toast.error('Selecione a conta para fechar a conciliação.');
    return;
  }

  if (!closingForm.periodStart || !closingForm.periodEnd) {
    toast.error('Informe o período inicial e final.');
    return;
  }

  closingForm.loading = true;

  try {
    await axios.post('/api/financeiro/reconciliations', {
      financial_account_id: closingForm.accountId,
      period_start: closingForm.periodStart,
      period_end: closingForm.periodEnd,
      opening_balance: Number.parseFloat(closingForm.openingBalance || '0'),
      closing_balance: Number.parseFloat(closingForm.closingBalance || '0'),
      statement_ids: closingForm.statementIds,
    });

    toast.success('Conciliação fechada com sucesso.');
    closingForm.periodStart = '';
    closingForm.periodEnd = '';
    closingForm.openingBalance = '';
    closingForm.closingBalance = '';
    closingForm.statementIds = [];
    submitFilters();
  } catch (error: any) {
    if (error?.response?.status === 422) {
      const validation = error.response.data?.errors ?? {};
      const messages = Object.values(validation)
        .flat()
        .filter((message): message is string => typeof message === 'string');
      toast.error(messages[0] ?? 'Falha na validação dos dados.');
    } else {
      toast.error(error?.response?.data?.message ?? 'Falha ao fechar conciliação.');
    }
  } finally {
    closingForm.loading = false;
  }
};

const deleteReconciliation = async (reconciliationId: number) => {
  if (!confirm('Deseja remover esta reconciliação?')) {
    return;
  }

  try {
    await axios.delete(`/api/financeiro/reconciliations/${reconciliationId}`);
    toast.success('Reconciliação removida.');
    submitFilters();
  } catch (error: any) {
    toast.error(error?.response?.data?.message ?? 'Não foi possível remover a reconciliação.');
  }
};

const exportReconciliations = (params: Record<string, any>) => {
  const query = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== null && value !== '' && value !== undefined) {
      query.append(key, String(value));
    }
  });
  window.open(`/api/financeiro/reconciliations/export?${query.toString()}`, '_blank');
};

watch(
  () => filters.accountId,
  (value) => {
    if (value === null || value === undefined) {
      closingForm.accountId = null;
      return;
    }
    closingForm.accountId = Number(value);
  },
  { immediate: true },
);
</script>

<template>
  <AuthenticatedLayout title="Conciliação Bancária">
    <Head title="Conciliação Bancária" />

    <div class="space-y-6">
      <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
        <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="text-lg font-semibold text-white">Conciliação Bancária</h1>
            <p class="text-xs text-slate-400">
              Importe extratos OFX/CSV, revise sugestões de correspondência e feche períodos conciliados.
            </p>
          </div>
          <button
            v-if="props.can.upload"
            type="button"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500"
            @click="openUploadModal"
          >
            Importar extrato
          </button>
        </header>

        <form class="grid gap-4 lg:grid-cols-5" @submit.prevent="submitFilters">
          <div>
            <label class="text-xs font-semibold text-slate-400">Conta financeira</label>
            <select
              v-model="filters.accountId"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 text-sm text-white focus:border-indigo-500 focus:outline-none"
            >
              <option :value="null">Todas</option>
              <option v-for="account in props.accounts" :key="account.id" :value="account.id">
                {{ account.nome }}
              </option>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-400">Status do extrato</label>
            <select
              v-model="filters.status"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 text-sm text-white focus:border-indigo-500 focus:outline-none"
            >
              <option v-for="status in filterStatuses" :key="status.value" :value="status.value">
                {{ status.label }}
              </option>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-400">Referência</label>
            <input
              v-model="filters.reference"
              type="search"
              placeholder="Buscar por referência"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
            />
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-400">Registros / página</label>
            <select
              v-model.number="filters.perPage"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 text-sm text-white focus:border-indigo-500 focus:outline-none"
            >
              <option :value="10">10</option>
              <option :value="15">15</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
            </select>
          </div>
          <div class="flex items-end gap-3">
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
      </section>

      <section class="rounded-2xl border border-slate-800 bg-slate-900/70 shadow-xl shadow-black/40">
        <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
          <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Extratos importados</h2>
          <div class="text-xs text-slate-400">
            {{ props.statements.meta?.total ?? 0 }} registros
          </div>
        </header>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-800 text-sm text-slate-100">
            <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th class="px-4 py-3 text-left">Referência</th>
                <th class="px-4 py-3 text-left">Conta</th>
                <th class="px-4 py-3 text-left">Importado em</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-left">Pendentes</th>
                <th class="px-4 py-3 text-right">Ações</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
              <tr v-if="props.statements.data.length === 0">
                <td colspan="6" class="px-6 py-8 text-center text-slate-400">
                  Nenhum extrato encontrado para os filtros selecionados.
                </td>
              </tr>
              <tr
                v-for="statement in props.statements.data"
                :key="statement.id"
                class="hover:bg-slate-900/50"
              >
                <td class="px-4 py-3">
                  <p class="font-semibold text-white">{{ statement.reference }}</p>
                  <p class="text-xs text-slate-400">{{ statement.original_name }}</p>
                </td>
                <td class="px-4 py-3 text-slate-300">
                  {{ statement.account?.nome ?? 'Conta removida' }}
                </td>
                <td class="px-4 py-3 text-slate-300">
                  {{
                    statement.imported_at_formatted
                      ?? (statement.imported_at ? new Date(statement.imported_at).toLocaleString('pt-BR') : '-')
                  }}
                </td>
                <td class="px-4 py-3">
                  <span
                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                    :class="resolveStatementStatusClasses(statement.status_category)"
                  >
                    {{ statement.status_label ?? statement.status ?? 'Sem status' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-slate-300">
                  <div class="flex flex-col text-xs">
                    <span>
                      Pendentes:
                      <strong class="text-amber-200">{{ statement.counts.pending ?? 0 }}</strong>
                    </span>
                    <span>
                      Confirmados:
                      <strong class="text-emerald-200">{{ statement.counts.confirmed ?? 0 }}</strong>
                    </span>
                    <span>Total: {{ statement.counts.total ?? 0 }}</span>
                    <span>
                      Sugestões:
                      <strong class="text-sky-200">{{ statement.counts.suggested ?? 0 }}</strong>
                    </span>
                    <span>
                      Ignorados:
                      <strong class="text-slate-300">{{ statement.counts.ignored ?? 0 }}</strong>
                    </span>
                  </div>
                  <div class="mt-2 flex flex-col text-xs text-slate-400">
                    <span>
                      Entradas:
                      <strong class="text-emerald-200">
                        {{ formatCurrency(statement.totals?.inflow ?? 0) }}
                      </strong>
                    </span>
                    <span>
                      Saídas:
                      <strong class="text-rose-200">
                        {{ formatCurrency(statement.totals?.outflow ?? 0) }}
                      </strong>
                    </span>
                    <span>
                      Saldo líquido:
                      <strong class="text-slate-200">
                        {{ formatCurrency(statement.totals?.net ?? 0) }}
                      </strong>
                    </span>
                    <span
                      v-if="
                        statement.balances?.closing !== undefined && statement.balances?.closing !== null
                      "
                    >
                      Saldo final:
                      <strong class="text-slate-200">
                        {{ formatCurrency(statement.balances?.closing ?? 0) }}
                      </strong>
                    </span>
                  </div>
                </td>
                <td class="px-4 py-3 text-right text-xs">
                  <div class="flex flex-wrap items-center justify-end gap-2">
                    <button
                      v-if="props.can.reconcile"
                      type="button"
                      class="rounded border border-slate-600 px-3 py-1 text-slate-200 transition hover:bg-slate-800"
                      @click="() => openStatementDetails(statement)"
                    >
                      Revisar
                    </button>
                    <button
                      v-if="props.can.reconcile"
                      type="button"
                      class="rounded border border-indigo-600 px-3 py-1 text-indigo-200 transition hover:bg-indigo-600/20"
                      @click="() => runSuggestions(statement.id)"
                    >
                      Sugestões
                    </button>
                    <label class="inline-flex items-center gap-2 text-slate-400">
                      <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-600 bg-slate-900 text-indigo-500 focus:ring-indigo-500"
                        :checked="closingForm.statementIds.includes(statement.id)"
                        @change="() => toggleStatementSelection(statement.id)"
                      />
                      <span class="text-xs">Fechar período</span>
                    </label>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <footer
          v-if="paginationLinks.length > 1"
          class="flex flex-wrap items-center justify-center gap-2 border-t border-slate-800 px-6 py-4"
        >
          <button
            v-for="link in paginationLinks"
            :key="link.label"
            type="button"
            class="rounded-md px-3 py-1 text-xs transition"
            :class="
              link.active
                ? 'bg-indigo-600 text-white'
                : link.url
                  ? 'text-slate-300 hover:bg-slate-800'
                  : 'text-slate-600 cursor-default'
            "
            v-html="link.label"
            @click="handlePaginate(link)"
          />
        </footer>
      </section>

      <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-xl shadow-black/40">
        <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Fechamento de conciliação</h2>
          <button
            v-if="props.can.reconcile"
            type="button"
            class="rounded border border-slate-700 px-3 py-1 text-xs text-slate-200 transition hover:bg-slate-800"
            @click="() => exportReconciliations({ financial_account_id: filters.accountId })"
          >
            Exportar CSV
          </button>
        </header>

        <div class="grid gap-4 lg:grid-cols-5">
          <div class="lg:col-span-2">
            <label class="text-xs font-semibold text-slate-400">Conta</label>
            <select
              v-model="closingForm.accountId"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 text-sm text-white focus:border-indigo-500 focus:outline-none"
            >
              <option :value="null">Selecione</option>
              <option v-for="account in props.accounts" :key="account.id" :value="account.id">
                {{ account.nome }}
              </option>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-400">Período inicial</label>
            <DatePicker v-model="closingForm.periodStart" placeholder="dd/mm/aaaa" />
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-400">Período final</label>
            <DatePicker v-model="closingForm.periodEnd" placeholder="dd/mm/aaaa" />
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-400">Saldo inicial</label>
            <input
              v-model="closingForm.openingBalance"
              type="number"
              step="0.01"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
            />
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-400">Saldo final</label>
            <input
              v-model="closingForm.closingBalance"
              type="number"
              step="0.01"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
            />
          </div>
        </div>

        <div class="mt-4 flex items-center justify-between">
          <p class="text-xs text-slate-400">
            Selecionados para fechamento: <strong>{{ closingForm.statementIds.length }}</strong> extrato(s).
          </p>
          <button
            v-if="props.can.reconcile"
            type="button"
            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-500 disabled:opacity-60"
            :disabled="closingForm.loading"
            @click="submitClosing"
          >
            Fechar conciliação
          </button>
        </div>

        <div class="mt-6">
          <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Histórico recente</h3>
          <div class="overflow-x-auto rounded-xl border border-slate-800">
            <table class="min-w-full divide-y divide-slate-800 text-sm text-slate-100">
              <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th class="px-4 py-3 text-left">Conta</th>
                  <th class="px-4 py-3 text-left">Período</th>
                  <th class="px-4 py-3 text-left">Saldo inicial</th>
                  <th class="px-4 py-3 text-left">Saldo final</th>
                  <th class="px-4 py-3 text-left">Status</th>
                  <th class="px-4 py-3 text-right">Ações</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800">
                <tr v-if="props.reconciliations.length === 0">
                  <td colspan="6" class="px-4 py-5 text-center text-slate-400">
                    Nenhuma reconciliação registrada ainda.
                  </td>
                </tr>
                <tr v-for="item in props.reconciliations" :key="item.id">
                  <td class="px-4 py-3 text-slate-300">
                    {{
                      props.accounts.find((account) => account.id === item.financial_account_id)?.nome ??
                      `Conta #${item.financial_account_id}`
                    }}
                  </td>
                  <td class="px-4 py-3 text-slate-300">
                    {{ item.period_start ?? '-' }} — {{ item.period_end ?? '-' }}
                  </td>
                  <td class="px-4 py-3 text-emerald-200">
                    {{ new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(item.opening_balance) }}
                  </td>
                  <td class="px-4 py-3 text-emerald-200">
                    {{ new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(item.closing_balance) }}
                  </td>
                  <td class="px-4 py-3">
                    <span
                      class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                      :class="
                        item.status === 'fechado'
                          ? 'bg-emerald-500/10 text-emerald-200 border border-emerald-400/40'
                          : 'bg-amber-500/10 text-amber-200 border border-amber-400/40'
                      "
                    >
                      {{ item.status }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right text-xs">
                    <div class="flex justify-end gap-2">
                      <button
                        type="button"
                        class="rounded border border-slate-700 px-3 py-1 text-slate-200 transition hover:bg-slate-800"
                        @click="() =>
                          exportReconciliations({
                            financial_account_id: item.financial_account_id,
                            period_start: item.period_start,
                            period_end: item.period_end,
                          })"
                      >
                        Exportar
                      </button>
                      <button
                        v-if="props.can.reconcile"
                        type="button"
                        class="rounded border border-rose-600 px-3 py-1 text-rose-200 transition hover:bg-rose-600/20"
                        @click="() => deleteReconciliation(item.id)"
                      >
                        Remover
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>

    <!-- Upload Modal -->
    <transition name="fade">
      <div
        v-if="uploadModalVisible"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur"
        @keydown.esc.prevent.stop="closeUploadModal"
      >
        <div class="w-full max-w-xl rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40">
          <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
            <div>
              <h2 class="text-lg font-semibold text-white">Importar extrato bancário</h2>
              <p class="text-xs text-slate-400">Selecione a conta e envie um arquivo OFX ou CSV.</p>
            </div>
            <button type="button" class="rounded-md p-2 text-slate-400 transition hover:text-white" @click="closeUploadModal">
              <span class="sr-only">Fechar</span>
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </header>
          <div class="px-6 py-5 space-y-4 text-sm text-slate-200">
            <div>
              <label class="text-xs font-semibold text-slate-400">Conta</label>
              <select
                v-model="uploadForm.accountId"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 text-sm text-white focus:border-indigo-500 focus:outline-none"
              >
                <option :value="null">Selecione</option>
                <option v-for="account in props.accounts" :key="account.id" :value="account.id">
                  {{ account.nome }}
                </option>
              </select>
            </div>
            <div>
              <label class="text-xs font-semibold text-slate-400">Arquivo OFX / CSV</label>
              <input
                type="file"
                accept=".ofx,.qfx,.csv,.txt"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
                @change="handleFileInput"
              />
              <p class="mt-1 text-xs text-slate-500">Tamanho máximo: 5 MB.</p>
            </div>
          </div>
          <footer class="flex justify-end gap-3 border-t border-slate-800 px-6 py-4">
            <button
              type="button"
              class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-800 disabled:opacity-60"
              :disabled="uploadForm.loading"
              @click="closeUploadModal"
            >
              Cancelar
            </button>
            <button
              type="button"
              class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 disabled:opacity-60"
              :disabled="uploadForm.loading"
              @click="submitUpload"
            >
              Importar
            </button>
          </footer>
        </div>
      </div>
    </transition>

    <!-- Statement detail modal -->
    <transition name="fade">
      <div
        v-if="selectedStatementId !== null"
        class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-slate-950/60 px-4 py-8 backdrop-blur"
      >
        <div class="w-full max-w-6xl rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40">
          <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
            <div class="space-y-1.5">
              <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-semibold text-white">
                  Extrato #{{ statementDetails?.reference ?? selectedStatementId }}
                </h2>
                <span
                  v-if="statementDetails"
                  class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                  :class="resolveStatementStatusClasses(statementDetails.status_category)"
                >
                  {{ statementDetails.status_label ?? statementDetails.status ?? 'Sem status' }}
                </span>
              </div>
              <p class="text-xs text-slate-400">
                {{
                  statementDetails?.imported_at_formatted
                    ?? (statementDetails?.imported_at
                        ? `Importado em ${new Date(statementDetails.imported_at).toLocaleString('pt-BR')}`
                        : 'Sem informação de importação')
                }}
              </p>
              <p v-if="statementDetails?.account" class="text-xs text-slate-500">
                Conta: {{ statementDetails.account.nome ?? 'Conta removida' }}
              </p>
            </div>
            <button
              type="button"
              class="rounded-md p-2 text-slate-400 transition hover:text-white"
              @click="closeStatementDetails"
            >
              <span class="sr-only">Fechar</span>
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </header>

          <div class="max-h-[75vh] overflow-y-auto px-6 py-5">
            <div v-if="loadingStatement" class="py-8 text-center text-slate-400">
              Carregando extrato...
            </div>

            <div v-else-if="statementDetails && statementDetails.lines.length > 0" class="space-y-4">
              <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
                  <p class="text-slate-400">Entradas</p>
                  <p class="text-lg font-semibold text-emerald-300">
                    {{ formatCurrency(statementDetails.totals?.inflow ?? 0) }}
                  </p>
                </article>
                <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
                  <p class="text-slate-400">Saídas</p>
                  <p class="text-lg font-semibold text-rose-300">
                    {{ formatCurrency(statementDetails.totals?.outflow ?? 0) }}
                  </p>
                </article>
                <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
                  <p class="text-slate-400">Saldo líquido</p>
                  <p class="text-lg font-semibold text-slate-200">
                    {{ formatCurrency(statementDetails.totals?.net ?? 0) }}
                  </p>
                </article>
                <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
                  <p class="text-slate-400">Saldos</p>
                  <p class="text-sm text-slate-300">
                    Inicial:
                    <span class="font-semibold">
                      {{ formatCurrency(statementDetails.balances?.opening ?? 0) }}
                    </span>
                  </p>
                  <p class="text-sm text-slate-300">
                    Final:
                    <span class="font-semibold">
                      {{ formatCurrency(statementDetails.balances?.closing ?? statementDetails.totals?.net ?? 0) }}
                    </span>
                  </p>
                </article>
              </div>

              <article
                v-for="line in statementDetails.lines"
                :key="line.id"
                class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 transition hover:border-indigo-600/50"
              >
                <header class="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold text-white">
                      {{ line.transaction_date_formatted ?? line.transaction_date ?? '-' }} ·
                      {{ line.description ?? 'Sem descrição' }}
                    </p>
                    <p class="text-xs text-slate-400">Linha #{{ line.linha }}</p>
                  </div>
                  <div
                    class="text-sm font-semibold"
                    :class="line.direction === 'debit' ? 'text-rose-300' : 'text-emerald-300'"
                  >
                    {{ formatCurrency(line.amount) }}
                  </div>
                </header>

                <div class="mt-3 flex flex-wrap items-start justify-between gap-3 text-xs text-slate-400">
                  <div class="flex flex-col gap-1">
                    <span>
                      Status:
                      <strong :class="resolveLineStatusClasses(line.match_status_category)">
                        {{ line.match_status_label ?? line.match_status }}
                      </strong>
                    </span>
                    <span v-if="line.balance !== null">
                      Saldo após movimento:
                      {{ formatCurrency(line.balance ?? 0) }}
                    </span>
                  </div>

                  <div v-if="line.journal_entry" class="rounded-lg border border-slate-700 px-3 py-2 text-slate-300">
                    <p class="text-xs font-semibold text-slate-200">
                      Lançamento #{{ line.journal_entry.id }} · {{ line.journal_entry.type }}
                    </p>
                    <p class="text-xs text-slate-400">
                      {{ line.journal_entry.description ?? 'Sem descrição' }}
                    </p>
                    <p class="text-xs text-slate-500">Status: {{ line.journal_entry.status }}</p>
                  </div>

                  <div
                    v-if="line.matched_installment"
                    class="rounded-lg border border-slate-700 px-3 py-2 text-slate-300"
                  >
                    <p class="text-xs font-semibold text-slate-200">
                      Parcela #{{ line.matched_installment.numero_parcela }}
                    </p>
                    <p class="text-xs text-slate-400">
                      Valor: {{ formatCurrency(line.matched_installment.valor_total ?? 0) }}
                    </p>
                    <p class="text-xs text-slate-500">
                      Status: {{ line.matched_installment.status_label ?? line.matched_installment.status }}
                    </p>
                  </div>
                </div>

                <div v-if="line.match_status === 'sugerido' && line.match_meta?.suggestions?.length" class="mt-4 space-y-2">
                  <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                    Sugestões de correspondência
                  </p>
                  <div
                    v-for="suggestion in line.match_meta.suggestions"
                    :key="suggestion.installment_id"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-xs text-slate-200"
                  >
                    <div>
                      <p class="font-semibold text-white">
                        Parcela #{{ suggestion.installment_number ?? '—' }} · Lançamento #{{ suggestion.journal_entry_id }}
                      </p>
                      <p class="text-slate-400">{{ suggestion.journal_entry_description ?? 'Sem descrição' }}</p>
                      <p class="text-slate-500">
                        Vencimento: {{ suggestion.installment_due_date ?? '-' }} · Confiança:
                        <span class="text-emerald-300">{{ suggestion.confidence }}%</span>
                      </p>
                    </div>
                    <button
                      type="button"
                      class="rounded border border-emerald-600 px-3 py-1 text-emerald-200 transition hover:bg-emerald-600/20 disabled:opacity-60"
                      :disabled="confirmingLineId === line.id"
                      @click="() => confirmLine(line, suggestion)"
                    >
                      Confirmar
                    </button>
                  </div>
                </div>

                <div v-if="props.can.reconcile" class="mt-4 flex flex-wrap items-center gap-2">
                  <button
                    type="button"
                    class="rounded border border-emerald-500 px-3 py-1 text-emerald-200 transition hover:bg-emerald-500/20 disabled:opacity-60"
                    :disabled="confirmingLineId === line.id"
                    @click="() => confirmLine(line, line.match_meta?.suggestions?.[0] ?? null)"
                  >
                    Confirmar com melhor sugestão
                  </button>
                  <button
                    type="button"
                    class="rounded border border-rose-500 px-3 py-1 text-rose-200 transition hover:bg-rose-500/20 disabled:opacity-60"
                    :disabled="ignoringLineId === line.id"
                    @click="() => ignoreLine(line)"
                  >
                    Ignorar
                  </button>
                </div>
              </article>
            </div>

            <div v-else class="py-10 text-center text-slate-400">
              Nenhuma movimentação encontrada neste extrato.
            </div>
          </div>
        </div>
      </div>
    </transition>
  </AuthenticatedLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
