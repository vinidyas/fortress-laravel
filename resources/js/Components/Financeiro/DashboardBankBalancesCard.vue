<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import axios from '@/bootstrap';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

type AccountAlert = {
  ativo: boolean;
  limite: number | null;
};

type AccountItem = {
  id: number;
  nome: string;
  apelido?: string | null;
  categoria?: string | null;
  moeda: string;
  saldo_inicial: number;
  saldo_atual: number;
  saldo_projetado: number;
  pendente_delta: number;
  pendente_entradas: number;
  pendente_saidas: number;
  ultima_movimentacao: string | null;
  alerta: AccountAlert;
};

type BalanceSummary = {
  total_current: number;
  total_projected: number;
  pending_delta: number;
  status: 'positive' | 'negative' | 'neutral';
};

type HistoryPoint = {
  date: string;
  balance: number;
};

type BalanceHistory = {
  points: HistoryPoint[];
  min: number;
  max: number;
};

type AlertItem = {
  account_id: number;
  account_name: string;
  current_balance: number;
  threshold: number;
  message: string;
};

type BalancePayload = {
  summary: BalanceSummary;
  top_positive: AccountItem[];
  top_negative: AccountItem[];
  accounts: AccountItem[];
  history: BalanceHistory;
  alerts: AlertItem[];
  meta: {
    available_categories: string[];
    applied_filters: Record<string, unknown>;
  };
};

interface CostCenterOption {
  id: number;
  nome: string;
}

const props = withDefaults(
  defineProps<{
    mode?: 'compact' | 'expanded';
    endpoint?: string;
    costCenters?: CostCenterOption[];
    initialFilters?: {
      category?: string | null;
      cost_center_id?: number | null;
      include_inactive?: boolean;
    };
    lazy?: boolean;
  }>(),
  {
    mode: 'compact',
    endpoint: '/api/financeiro/account-balances',
    lazy: true,
  }
);

const cardRef = ref<HTMLElement | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const payload = ref<BalancePayload | null>(null);
const hasLoaded = ref(false);
const lastUpdatedAt = ref<Date | null>(null);

const filters = reactive({
  category: props.initialFilters?.category ?? null,
  cost_center_id: props.initialFilters?.cost_center_id ?? null,
  include_inactive: props.initialFilters?.include_inactive ?? false,
});

const isExpanded = computed(() => props.mode === 'expanded');

const categoryModel = computed({
  get: () => filters.category ?? '',
  set: (value: string) => {
    filters.category = value === '' ? null : value;
  },
});

const costCenterModel = computed({
  get: () => (filters.cost_center_id ? String(filters.cost_center_id) : ''),
  set: (value: string) => {
    filters.cost_center_id = value === '' ? null : Number(value);
  },
});

const summary = computed(() => payload.value?.summary ?? null);
const accounts = computed(() => payload.value?.accounts ?? []);
const alerts = computed(() => payload.value?.alerts ?? []);
const topPositive = computed(() => payload.value?.top_positive ?? []);
const topNegative = computed(() => payload.value?.top_negative ?? []);
const history = computed(() => payload.value?.history ?? { points: [], min: 0, max: 0 });
const lastHistoryBalance = computed(() => {
  const points = history.value.points ?? [];
  if (!points.length) {
    return null;
  }

  const lastPoint = points[points.length - 1];
  return typeof lastPoint?.balance === 'number' ? lastPoint.balance : null;
});
const categories = computed(() => payload.value?.meta?.available_categories ?? []);
const costCenterOptions = computed(() => props.costCenters ?? []);

const statusColors = computed(() => {
  const status = summary.value?.status ?? 'neutral';

  return status === 'positive'
    ? 'text-emerald-300'
    : status === 'negative'
      ? 'text-rose-300'
      : 'text-slate-200';
});

const totalCurrentDisplay = computed(() =>
  summary.value ? formatCurrency(summary.value.total_current) : '--'
);

const pendingDeltaDisplay = computed(() =>
  summary.value ? formatCurrency(summary.value.pending_delta) : '--'
);

const pendingDeltaLabel = computed(() => {
  if (!summary.value) {
    return '';
  }

  const value = summary.value.pending_delta;
  if (value === 0) {
    return 'Sem variação prevista';
  }

  return value > 0 ? 'Tendência positiva' : 'Tendência negativa';
});

const sparklineId = `spark-balance-${Math.random().toString(36).slice(2, 10)}`;
const sparklinePath = computed(() => {
  const points = history.value.points ?? [];

  if (!points.length) {
    return '';
  }

  const max = history.value.max ?? 0;
  const min = history.value.min ?? 0;
  const range = Math.max(max - min, 1);
  const denominator = Math.max(points.length - 1, 1);

  return points
    .map((point, index) => {
      const x = (index / denominator) * 100;
      const normalized = points.length === 1 ? 0.5 : (point.balance - min) / range;
      const y = 100 - normalized * 100;

      return `${index === 0 ? 'M' : 'L'} ${x.toFixed(2)} ${y.toFixed(2)}`;
    })
    .join(' ');
});

const sparklineAreaPath = computed(() => {
  const points = history.value.points ?? [];

  if (!points.length) {
    return '';
  }

  const max = history.value.max ?? 0;
  const min = history.value.min ?? 0;
  const range = Math.max(max - min, 1);
  const denominator = Math.max(points.length - 1, 1);

  const linePoints = points.map((point, index) => {
    const x = (index / denominator) * 100;
    const normalized = points.length === 1 ? 0.5 : (point.balance - min) / range;
    const y = 100 - normalized * 100;

    return { x, y };
  });

  if (!linePoints.length) {
    return '';
  }

  const first = linePoints[0];
  const last = linePoints[linePoints.length - 1];
  const line = linePoints
    .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`)
    .join(' ');

  return `${line} L ${last.x.toFixed(2)} 100 L ${first.x.toFixed(2)} 100 Z`;
});

const hasHistory = computed(() => (history.value.points ?? []).length > 1);

const lastUpdatedDisplay = computed(() => {
  if (!lastUpdatedAt.value) {
    return null;
  }

  return lastUpdatedAt.value.toLocaleTimeString('pt-BR', {
    hour: '2-digit',
    minute: '2-digit',
  });
});

const formatCurrency = (value: number) =>
  Number(value ?? 0).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 2,
  });

const formatDate = (value: string | null) => {
  if (!value) {
    return '—';
  }

  const [year, month, day] = value.split('-');
  if (!year || !month || !day) {
    return value;
  }

  return `${day.padStart(2, '0')}/${month.padStart(2, '0')}/${year}`;
};

const navigateToTransactions = (accountId: number) => {
  router.get(
    route('financeiro.index'),
    {
      'filter[account_id]': accountId,
    },
    {
      preserveState: false,
      preserveScroll: false,
    }
  );
};

const buildFilterParams = () => {
  const params: Record<string, unknown> = {};

  if (filters.category) {
    params['filter[category]'] = filters.category;
  }

  if (filters.cost_center_id) {
    params['filter[cost_center_id]'] = filters.cost_center_id;
  }

  if (filters.include_inactive) {
    params['filter[include_inactive]'] = 1;
  }

  return params;
};

const fetchBalances = async () => {
  if (loading.value) {
    return;
  }

  loading.value = true;
  error.value = null;

  try {
    const { data } = await axios.get(props.endpoint, {
      params: buildFilterParams(),
    });

    payload.value = data?.data ?? null;
    lastUpdatedAt.value = new Date();
  } catch (err: any) {
    console.error(err);
    error.value =
      err?.response?.data?.message ??
      'Não foi possível carregar os saldos financeiros. Tente novamente.';
  } finally {
    loading.value = false;
  }
};

let observer: IntersectionObserver | null = null;
let fetchTimer: ReturnType<typeof setTimeout> | null = null;

const triggerLoad = () => {
  if (hasLoaded.value) {
    return;
  }

  hasLoaded.value = true;
  void fetchBalances();
};

const scheduleFetch = () => {
  if (!hasLoaded.value) {
    return;
  }

  if (fetchTimer) {
    clearTimeout(fetchTimer);
  }

  fetchTimer = setTimeout(() => {
    void fetchBalances();
    fetchTimer = null;
  }, 250);
};

onMounted(() => {
  if (!props.lazy) {
    triggerLoad();
    return;
  }

  if ('IntersectionObserver' in window) {
    observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
          triggerLoad();
          observer?.disconnect();
          observer = null;
        }
      },
      { threshold: 0.2 }
    );

    if (cardRef.value) {
      observer.observe(cardRef.value);
    }
  } else {
    triggerLoad();
  }
});

onBeforeUnmount(() => {
  observer?.disconnect();
  if (fetchTimer) {
    clearTimeout(fetchTimer);
  }
});

watch(
  () => [filters.category, filters.cost_center_id, filters.include_inactive],
  () => {
    scheduleFetch();
  }
);

const refresh = () => {
  if (!hasLoaded.value) {
    triggerLoad();
    return;
  }

  void fetchBalances();
};
</script>

<template>
  <section
    ref="cardRef"
    class="relative rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
  >
    <div
      v-if="loading"
      class="absolute inset-0 z-20 flex items-center justify-center rounded-2xl bg-slate-900/80 backdrop-blur-sm"
    >
      <div class="flex items-center gap-3 text-sm font-medium text-slate-200">
        <svg class="h-5 w-5 animate-spin text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3v3m0 12v3m9-9h-3M6 12H3m15.364 6.364l-2.121-2.121M8.757 8.757 6.636 6.636m0 10.728L8.757 15.243m8.486-8.486L19.364 6.636" />
        </svg>
        Carregando saldos...
      </div>
    </div>

    <div v-if="error" class="space-y-4">
      <div class="rounded-xl border border-rose-500/40 bg-rose-500/10 p-4 text-sm text-rose-100">
        {{ error }}
      </div>
      <button
        type="button"
        class="rounded-lg border border-indigo-500/40 bg-indigo-500/20 px-3 py-2 text-sm font-semibold text-indigo-100 transition hover:border-indigo-400 hover:bg-indigo-500/30"
        @click="refresh"
      >
        Tentar novamente
      </button>
    </div>

    <div v-else>
      <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <p class="text-sm font-semibold text-slate-300">Saldo consolidado</p>
          <p :class="['mt-2 text-3xl font-semibold', statusColors]">{{ totalCurrentDisplay }}</p>
          <p class="text-xs text-slate-500">
            {{ pendingDeltaLabel }}
            <span class="font-semibold text-slate-200">{{ pendingDeltaDisplay }}</span>
          </p>
        </div>

        <div class="flex flex-col items-start gap-2 text-xs text-slate-400 lg:items-end">
          <div class="flex items-center gap-3">
            <button
              type="button"
              class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:border-indigo-500 hover:text-white"
              @click="refresh"
            >
              Atualizar
            </button>
          </div>
          <p v-if="lastUpdatedDisplay" class="text-2xs text-slate-500">
            Atualizado {{ lastUpdatedDisplay }}
          </p>
        </div>
      </header>

      <div v-if="alerts.length" class="mt-4 space-y-2">
        <article
          v-for="alert in alerts"
          :key="`alert-${alert.account_id}`"
          class="rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-100 shadow-inner shadow-rose-500/10"
        >
          <p class="font-semibold text-rose-200">{{ alert.account_name }}</p>
          <p class="mt-1 text-xs text-rose-100/90">{{ alert.message }}</p>
        </article>
      </div>

      <div v-if="isExpanded" class="mt-6 space-y-6">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          <div>
            <label class="text-xs font-semibold text-slate-400">Categoria</label>
            <select
              v-model="categoryModel"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
            >
              <option value="">Todas</option>
              <option
                v-for="category in categories"
                :key="category"
                :value="category"
              >
                {{ category || 'Sem categoria' }}
              </option>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-400">Centro de custo</label>
            <select
              v-model="costCenterModel"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
            >
              <option value="">Todos</option>
              <option
                v-for="center in costCenterOptions"
                :key="center.id"
                :value="String(center.id)"
              >
                {{ center.nome }}
              </option>
            </select>
          </div>
          <div class="flex items-center gap-2 pt-6">
            <input
              id="include-inactive-balances"
              v-model="filters.include_inactive"
              type="checkbox"
              class="h-4 w-4 rounded border-slate-700 bg-slate-900 text-indigo-500 focus:ring-indigo-500"
            />
            <label for="include-inactive-balances" class="text-xs font-medium text-slate-300">
              Incluir contas inativas
            </label>
          </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/70">
          <table class="min-w-full divide-y divide-slate-800 text-sm">
            <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th class="px-4 py-3 text-left">Conta</th>
                <th class="px-4 py-3 text-right">Saldo atual</th>
                <th class="px-4 py-3 text-right">Variação prevista</th>
                <th class="px-4 py-3 text-left">Última movimentação</th>
                <th class="px-4 py-3 text-right">Ações</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/80">
              <tr
                v-for="account in accounts"
                :key="account.id"
                :class="account.alerta?.ativo ? 'bg-rose-500/5' : ''"
              >
                <td class="px-4 py-3 align-top">
                  <div class="space-y-1">
                    <p class="font-semibold text-white">{{ account.nome }}</p>
                    <p v-if="account.apelido" class="text-xs text-slate-400">{{ account.apelido }}</p>
                    <span
                      v-if="account.alerta?.ativo"
                      class="inline-flex items-center rounded-full bg-rose-500/20 px-2 py-0.5 text-2xs font-semibold text-rose-200"
                    >
                      Abaixo do limite
                    </span>
                  </div>
                </td>
                <td class="px-4 py-3 text-right font-semibold text-slate-100">
                  {{ formatCurrency(account.saldo_atual) }}
                </td>
                <td
                  class="px-4 py-3 text-right"
                  :class="account.pendente_delta >= 0 ? 'text-emerald-300' : 'text-rose-300'"
                >
                  {{ formatCurrency(account.pendente_delta) }}
                </td>
                <td class="px-4 py-3 text-slate-300">
                  {{ formatDate(account.ultima_movimentacao) }}
                </td>
                <td class="px-4 py-3 text-right">
                  <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-indigo-500/40 bg-indigo-500/20 px-3 py-1.5 text-xs font-semibold text-indigo-100 transition hover:border-indigo-400 hover:bg-indigo-500/30"
                    @click="navigateToTransactions(account.id)"
                  >
                    Ver lançamentos
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                  </button>
                </td>
              </tr>
              <tr v-if="!accounts.length && !loading">
                <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">
                  Nenhuma conta encontrada para os filtros selecionados.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-else class="mt-6 grid gap-4 lg:grid-cols-3">
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <header class="mb-2 flex items-center justify-between">
            <p class="font-semibold text-slate-300">Top saldos</p>
            <span class="text-2xs text-slate-500">Top 3</span>
          </header>
          <ul class="space-y-2 text-sm text-slate-200">
            <li v-for="account in topPositive" :key="`positive-${account.id}`" class="flex justify-between">
              <span class="truncate pr-3">{{ account.nome }}</span>
              <span>{{ formatCurrency(account.saldo_atual) }}</span>
            </li>
            <li v-if="!topPositive.length" class="text-xs text-slate-500">Sem registros.</li>
          </ul>
        </article>

        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <header class="mb-2 flex items-center justify-between">
            <p class="font-semibold text-slate-300">Maiores exposições</p>
            <span class="text-2xs text-slate-500">Top 3</span>
          </header>
          <ul class="space-y-2 text-sm text-slate-200">
            <li v-for="account in topNegative" :key="`negative-${account.id}`" class="flex justify-between">
              <span class="truncate pr-3">{{ account.nome }}</span>
              <span class="text-rose-300">{{ formatCurrency(account.saldo_atual) }}</span>
            </li>
            <li v-if="!topNegative.length" class="text-xs text-slate-500">Sem exposições negativas.</li>
          </ul>
        </article>

        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4">
          <p class="text-sm font-semibold text-slate-300">Histórico (7 dias)</p>
          <div class="mt-3 h-24 w-full overflow-hidden rounded-lg border border-slate-800 bg-slate-950/70 p-2">
            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="h-full w-full">
              <defs>
                <linearGradient :id="`${sparklineId}-gradient`" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#6366f1" stop-opacity="0.4" />
                  <stop offset="100%" stop-color="#6366f1" stop-opacity="0" />
                </linearGradient>
              </defs>
              <rect x="0" y="0" width="100" height="100" fill="none" />
              <path
                v-if="hasHistory"
                :d="sparklineAreaPath"
                :fill="`url(#${sparklineId}-gradient)`"
                stroke="none"
                opacity="0.7"
              />
              <path
                v-if="sparklinePath"
                :d="sparklinePath"
                stroke="#818cf8"
                stroke-width="1.5"
                fill="none"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </div>
          <p class="mt-2 text-xs text-slate-500">
            <span v-if="history.points?.length && lastHistoryBalance !== null">
              Último: {{ formatCurrency(lastHistoryBalance ?? 0) }}
            </span>
            <span v-else>Sem histórico recente.</span>
          </p>
        </article>
      </div>
    </div>
  </section>
</template>

<style scoped>
.text-2xs {
  font-size: 0.625rem;
}
</style>
