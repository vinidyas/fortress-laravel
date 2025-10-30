<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import DatePicker from '@/Components/Form/DatePicker.vue';
import { computed, onMounted, reactive, ref, watch } from 'vue';

interface AccountOption {
  id: number;
  nome: string;
}

interface StatementCounts {
  total?: number | null;
  pending?: number | null;
  suggested?: number | null;
  confirmed?: number | null;
  ignored?: number | null;
}

interface StatementTotals {
  inflow?: number | null;
  outflow?: number | null;
  net?: number | null;
}

interface StatementLine {
  id: number;
  linha: number;
  transaction_date?: string | null;
  transaction_date_formatted?: string | null;
  description?: string | null;
  amount: number;
  match_status: string;
  match_status_label?: string | null;
  match_status_category?: string | null;
  journal_entry?: { id: number; description?: string | null } | null;
}

interface StatementRow {
  id: number;
  financial_account_id: number;
  reference: string;
  original_name?: string | null;
  imported_at?: string | null;
  imported_at_formatted?: string | null;
  status: string;
  status_label?: string | null;
  status_category?: string | null;
  counts: StatementCounts;
  totals?: StatementTotals | null;
  balances?: { opening?: number | null; closing?: number | null } | null;
  account?: { id: number; nome: string } | null;
  imported_by?: { id: number; name?: string | null } | null;
  lines?: StatementLine[];
  meta?: Record<string, any>;
}

interface SummaryPayload {
  statements: {
    total: number;
    status: Record<string, number>;
  };
  lines: {
    total: number;
    pending: number;
    suggested: number;
    confirmed: number;
    ignored: number;
  };
  totals: {
    inflow: number;
    outflow: number;
    net: number;
  };
}

const props = defineProps<{
  accounts: AccountOption[];
  canExport: boolean;
}>();

const filters = reactive({
  financial_account_id: '' as string | number,
  status: '',
  reference: '',
  imported_at_from: '',
  imported_at_to: '',
  with_lines: false,
  per_page: 25,
});

const loading = ref(false);
const errorMessage = ref('');
const summary = ref<SummaryPayload | null>(null);
const statements = ref<StatementRow[]>([]);
const pagination = ref<{ links: any; meta: any } | null>(null);
const expandedIds = ref<number[]>([]);

const statusOptions = [
  { value: '', label: 'Todos' },
  { value: 'open', label: 'Pendentes' },
  { value: 'processando', label: 'Processando' },
  { value: 'importado', label: 'Importado' },
  { value: 'conciliado', label: 'Conciliado' },
  { value: 'erro', label: 'Com erro' },
];

const formatCurrency = (value?: number | string | null) => {
  const numeric =
    typeof value === 'string'
      ? Number.parseFloat(value || '0')
      : typeof value === 'number'
        ? value
        : 0;

  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(
    Number.isFinite(numeric) ? numeric : 0,
  );
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

const selectedAccount = computed(() => {
  if (!filters.financial_account_id) return null;
  const id = Number(filters.financial_account_id);
  return props.accounts.find((account) => account.id === id) ?? null;
});

const buildParams = (page?: number) => {
  const params: Record<string, any> = {};

  if (filters.financial_account_id) {
    params.financial_account_id = filters.financial_account_id;
  }
  if (filters.status) {
    params.status = filters.status;
  }
  if (filters.reference) {
    params.reference = filters.reference;
  }
  if (filters.imported_at_from) {
    params.imported_at_from = filters.imported_at_from;
  }
  if (filters.imported_at_to) {
    params.imported_at_to = filters.imported_at_to;
  }
  if (filters.with_lines) {
    params.with_lines = 1;
  }
  params.per_page = filters.per_page;
  if (page) {
    params.page = page;
  }

  return params;
};

const loadReport = async (page = 1) => {
  loading.value = true;
  errorMessage.value = '';

  try {
    const params = buildParams(page);
    const { data } = await axios.get('/api/reports/bank-statements', { params });

    summary.value = data.summary ?? null;
    statements.value = data.data ?? [];
    pagination.value = {
      links: data.links ?? [],
      meta: data.meta ?? null,
    };
  } catch (error: any) {
    errorMessage.value =
      error?.response?.data?.message ?? 'Não foi possível carregar o relatório de extratos.';
  } finally {
    loading.value = false;
  }
};

const submitFilters = () => {
  expandedIds.value = [];
  loadReport(1);
};

const resetFilters = () => {
  filters.financial_account_id = '';
  filters.status = '';
  filters.reference = '';
  filters.imported_at_from = '';
  filters.imported_at_to = '';
  filters.with_lines = false;
  filters.per_page = 25;
  expandedIds.value = [];
  submitFilters();
};

const handlePagination = (link: { url: string | null }) => {
  if (!link.url || loading.value) {
    return;
  }

  const target = new URL(link.url, window.location.origin);
  const page = Number.parseInt(target.searchParams.get('page') ?? '1', 10);
  loadReport(page);
};

const toggleExpanded = (statementId: number) => {
  if (!filters.with_lines) {
    return;
  }

  const index = expandedIds.value.indexOf(statementId);
  if (index >= 0) {
    expandedIds.value.splice(index, 1);
  } else {
    expandedIds.value.push(statementId);
  }
};

const openTransaction = (journalEntryId?: number | null) => {
  if (!journalEntryId) {
    return;
  }

  window.open(`/financeiro/lancamentos/${journalEntryId}`, '_blank', 'noopener');
};

const exportReport = () => {
  if (!props.canExport) {
    return;
  }

  const params = new URLSearchParams();
  const query = buildParams();
  Object.entries(query).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      params.append(key, String(value));
    }
  });
  params.append('format', 'csv');

  window.location.href = `/api/reports/bank-statements/export?${params.toString()}`;
};

watch(
  () => filters.with_lines,
  () => {
    if (!filters.with_lines) {
      expandedIds.value = [];
    }
  },
);

onMounted(() => {
  loadReport();
});
</script>

<template>
  <AuthenticatedLayout title="Relatório de extratos bancários">
    <Head title="Relatório de extratos bancários" />

    <section
      class="space-y-6 rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
    >
      <header class="flex flex-col gap-1">
        <h1 class="text-xl font-semibold text-white">Relatório de extratos bancários</h1>
        <p class="text-sm text-slate-400">
          Analise entradas e saídas importadas dos bancos e acompanhe o status das conciliações.
        </p>
      </header>

      <form class="grid gap-4 md:grid-cols-6" @submit.prevent="submitFilters">
        <div>
          <label class="text-xs font-semibold text-slate-400">Conta</label>
          <select
            v-model="filters.financial_account_id"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
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
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
          >
            <option v-for="option in statusOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>

        <div>
          <label class="text-xs font-semibold text-slate-400">Referência</label>
          <input
            v-model="filters.reference"
            type="text"
            placeholder="Texto ou código"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
          />
        </div>

        <div>
          <label class="text-xs font-semibold text-slate-400">Importado a partir de</label>
          <DatePicker v-model="filters.imported_at_from" placeholder="dd/mm/aaaa" />
        </div>

        <div>
          <label class="text-xs font-semibold text-slate-400">Até</label>
          <DatePicker v-model="filters.imported_at_to" placeholder="dd/mm/aaaa" />
        </div>

        <div>
          <label class="text-xs font-semibold text-slate-400">Registros / página</label>
          <select
            v-model.number="filters.per_page"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
          >
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </div>

        <div class="flex items-center gap-2 md:col-span-2">
          <input
            id="with_lines"
            v-model="filters.with_lines"
            type="checkbox"
            class="h-4 w-4 rounded border-slate-600 bg-slate-900 text-indigo-500 focus:ring-indigo-500"
          />
          <label class="text-xs font-semibold text-slate-400" for="with_lines">
            Incluir detalhes das linhas (impacta desempenho)
          </label>
        </div>

        <div class="flex items-end gap-3 md:col-span-2">
          <button
            type="submit"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 disabled:opacity-60"
            :disabled="loading"
          >
            {{ loading ? 'Carregando...' : 'Aplicar filtros' }}
          </button>
          <button
            type="button"
            class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-800 disabled:opacity-60"
            :disabled="loading"
            @click="resetFilters"
          >
            Limpar
          </button>
          <button
            v-if="props.canExport"
            type="button"
            class="rounded-lg border border-emerald-600 px-4 py-2 text-sm text-emerald-200 transition hover:bg-emerald-600/20 disabled:opacity-60"
            :disabled="loading"
            @click="exportReport"
          >
            Exportar CSV
          </button>
        </div>
      </form>

      <p
        v-if="errorMessage"
        class="rounded-lg border border-rose-500/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-200"
      >
        {{ errorMessage }}
      </p>

      <div v-if="summary" class="grid gap-4 lg:grid-cols-4">
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Extratos filtrados</p>
          <p class="text-2xl font-semibold text-white">{{ summary.statements.total }}</p>
          <p class="mt-2 text-xs text-slate-500">
            Conta selecionada:
            <strong>{{ selectedAccount?.nome ?? 'Todas' }}</strong>
          </p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Entradas totais</p>
          <p class="text-2xl font-semibold text-emerald-300">
            {{ formatCurrency(summary.totals.inflow) }}
          </p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Saídas totais</p>
          <p class="text-2xl font-semibold text-rose-300">
            {{ formatCurrency(summary.totals.outflow) }}
          </p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Saldo líquido</p>
          <p
            :class="[
              'text-2xl font-semibold',
              summary.totals.net >= 0 ? 'text-emerald-300' : 'text-rose-300',
            ]"
          >
            {{ formatCurrency(summary.totals.net) }}
          </p>
        </article>
      </div>

      <div class="overflow-hidden rounded-2xl border border-slate-800">
        <table class="min-w-full divide-y divide-slate-800 text-sm text-slate-100">
          <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
            <tr>
              <th class="px-4 py-3 text-left">Extrato</th>
              <th class="px-4 py-3 text-left">Conta</th>
              <th class="px-4 py-3 text-left">Importação</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-left">Totais</th>
              <th class="px-4 py-3 text-right">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-if="!statements.length && !loading">
              <td colspan="6" class="px-6 py-8 text-center text-slate-400">
                Nenhum extrato encontrado com os filtros atuais.
              </td>
            </tr>

            <template v-for="statement in statements" :key="statement.id">
              <tr>
              <td class="px-4 py-3">
                <p class="font-semibold text-white">{{ statement.reference }}</p>
                <p class="text-xs text-slate-400">{{ statement.original_name ?? '-' }}</p>
              </td>
              <td class="px-4 py-3 text-slate-300">
                {{ statement.account?.nome ?? `Conta #${statement.financial_account_id}` }}
              </td>
              <td class="px-4 py-3 text-slate-300">
                {{
                  statement.imported_at_formatted
                    ?? (statement.imported_at
                        ? new Date(statement.imported_at).toLocaleString('pt-BR')
                        : '-')
                }}
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                  :class="resolveStatementStatusClasses(statement.status_category)"
                >
                  {{ statement.status_label ?? statement.status }}
                </span>
                <p class="mt-1 text-[11px] text-slate-400">
                  Pendentes:
                  <strong class="text-amber-200">{{ statement.counts.pending ?? 0 }}</strong>
                  · Sugestões:
                  <strong class="text-sky-200">{{ statement.counts.suggested ?? 0 }}</strong>
                  · Confirmados:
                  <strong class="text-emerald-200">{{ statement.counts.confirmed ?? 0 }}</strong>
                  · Ignorados:
                  <strong class="text-slate-200">{{ statement.counts.ignored ?? 0 }}</strong>
                </p>
              </td>
              <td class="px-4 py-3">
                <div class="flex flex-col text-xs text-slate-300">
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
                    <strong
                      :class="[
                        'text-slate-200',
                        (statement.totals?.net ?? 0) >= 0 ? 'text-emerald-200' : 'text-rose-200',
                      ]"
                    >
                      {{ formatCurrency(statement.totals?.net ?? 0) }}
                    </strong>
                  </span>
                  <span v-if="statement.balances?.closing !== undefined">
                    Saldo final:
                    <strong class="text-slate-200">
                      {{ formatCurrency(statement.balances?.closing ?? 0) }}
                    </strong>
                  </span>
                </div>
              </td>
              <td class="px-4 py-3 text-right text-xs">
                <button
                  v-if="filters.with_lines"
                  type="button"
                  class="rounded border border-slate-700 px-3 py-1 text-slate-200 transition hover:bg-slate-800"
                  @click="toggleExpanded(statement.id)"
                >
                  {{ expandedIds.includes(statement.id) ? 'Ocultar linhas' : 'Exibir linhas' }}
                </button>
              </td>
            </tr>
            <tr
              v-if="filters.with_lines && expandedIds.includes(statement.id)"
              :key="`${statement.id}-lines`"
            >
              <td colspan="6" class="bg-slate-950/60 px-6 py-4">
                <div v-if="statement.lines?.length" class="space-y-3 text-xs text-slate-300">
                  <article
                    v-for="line in statement.lines"
                    :key="line.id"
                    class="rounded-lg border border-slate-800 bg-slate-900/70 p-3"
                  >
                    <header class="flex flex-wrap items-center justify-between gap-2">
                      <div>
                        <p class="text-sm font-semibold text-white">
                          {{ line.transaction_date_formatted ?? line.transaction_date ?? '-' }} ·
                          {{ line.description ?? 'Sem descrição' }}
                        </p>
                        <p class="text-[11px] text-slate-500">Linha #{{ line.linha }}</p>
                      </div>
                      <div
                        class="text-sm font-semibold"
                        :class="line.amount >= 0 ? 'text-emerald-300' : 'text-rose-300'"
                      >
                        {{ formatCurrency(line.amount ?? 0) }}
                      </div>
                    </header>
                    <div class="mt-2 flex flex-wrap items-start justify-between gap-2">
                      <span :class="resolveLineStatusClasses(line.match_status_category)">
                        {{ line.match_status_label ?? line.match_status }}
                      </span>
                      <span v-if="line.journal_entry" class="text-slate-400">
                        Lançamento vinculado:
                        <button
                          type="button"
                          class="ml-1 inline-flex items-center gap-1 rounded border border-indigo-500/40 px-2 py-1 text-xs text-indigo-200 hover:bg-indigo-500/10"
                          @click="openTransaction(line.journal_entry?.id)"
                        >
                          <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5H5.25A2.25 2.25 0 0 0 3 6.75v11.25A2.25 2.25 0 0 0 5.25 20.25h11.25A2.25 2.25 0 0 0 18.75 18V9.75L13.5 4.5z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5V9.75H18.75" />
                          </svg>
                          #{{ line.journal_entry?.id }}
                        </button>
                      </span>
                    </div>
                  </article>
                </div>
                <p v-else class="text-center text-slate-400">
                  Extrato sem linhas carregadas ou nenhuma movimentação encontrada.
                </p>
              </td>
            </tr>
            </template>
          </tbody>
        </table>
      </div>

      <div
        v-if="pagination?.links?.length"
        class="flex flex-wrap items-center justify-center gap-2 border-t border-slate-800 px-6 pt-4"
      >
        <button
          v-for="link in pagination.links"
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
          @click="handlePagination(link)"
        />
      </div>
    </section>
  </AuthenticatedLayout>
</template>
