<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { formatDate } from '@/utils/date';
import { useNotificationStore } from '@/Stores/notifications';

interface Metrics {
  propertiesTotal: number;
  propertiesAvailable: number;
  propertiesUnavailable: number;
  activeContracts: number;
  contractsExpiringSoon: number;
  openInvoices: number;
  overdueInvoices: number;
  openAmount: number;
  paidThisMonth: number;
}

interface ExpiringContract {
  id: number;
  code: string | null;
  imovel: string | null;
  endsAt: string | null;
  daysLeft: number | null;
}

interface OpenInvoice {
  id: number;
  competencia: string | null;
  dueDate: string | null;
  contract: string | null;
  property: string | null;
  amount: number;
  lateDays: number | null;
}

interface RecentPerson {
  id: number;
  name: string;
  document: string | null;
  type: string;
  roles: string[];
  createdAt: string | null;
}

interface AlertAction {
  label: string;
  href: string;
}

type AlertType = 'danger' | 'warning' | 'info';

interface AlertItem {
  type: AlertType;
  title: string;
  message: string;
  action?: AlertAction;
  key: string;
}

interface WidgetSettings {
  key: string;
  label: string;
  hidden: boolean;
  position: number;
}

interface FinancialTrendPoint {
  key: string;
  label: string;
  credit: number;
  debit: number;
}

interface DelinquencySummary {
  openAmount: number;
  paidThisMonth: number;
  rate: number;
}

interface ChartPoint {
  x: number;
  y: number;
  value: number;
  label: string;
}

const props = defineProps<{
  metrics?: Partial<Metrics>;
  expiringContracts: ExpiringContract[];
  openInvoices: OpenInvoice[];
  recentPeople: RecentPerson[];
  alerts?: AlertItem[];
  widgets?: Array<{ key: string; label: string; hidden?: boolean; position?: number }>;
  financialTrend?: FinancialTrendPoint[];
  delinquency?: Partial<DelinquencySummary>;
}>();

const defaultMetrics: Metrics = {
  propertiesTotal: 0,
  propertiesAvailable: 0,
  propertiesUnavailable: 0,
  activeContracts: 0,
  contractsExpiringSoon: 0,
  openInvoices: 0,
  overdueInvoices: 0,
  openAmount: 0,
  paidThisMonth: 0,
};

const metrics = computed<Metrics>(() => ({
  ...defaultMetrics,
  ...(props.metrics ?? {}),
}));

const formatCurrency = (value: number) =>
  Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const formatDays = (value: number | null) => {
  if (value === null) {
    return '-';
  }

  if (value === 0) {
    return 'vence hoje';
  }

  if (value > 0) {
    return `em ${value} dias`;
  }

  return `${Math.abs(value)} dias em atraso`;
};

const propertyOccupancy = computed(() => {
  const total = metrics.value.propertiesTotal || 1;
  const available = metrics.value.propertiesAvailable;
  const unavailable = metrics.value.propertiesUnavailable;

  return {
    availablePercent: Math.round((available / total) * 100),
    unavailablePercent: Math.round((unavailable / total) * 100),
  };
});

const financialTrend = computed<FinancialTrendPoint[]>(() => props.financialTrend ?? []);

const delinquency = computed<DelinquencySummary>(() => ({
  openAmount: Number(props.delinquency?.openAmount ?? 0),
  paidThisMonth: Number(props.delinquency?.paidThisMonth ?? 0),
  rate: Number(props.delinquency?.rate ?? 0),
}));

const trendMaxValue = computed(() => {
  const values = financialTrend.value.flatMap((point) => [
    Number(point.credit ?? 0),
    Number(point.debit ?? 0),
  ]);

  if (!values.length) {
    return 1;
  }

  const max = Math.max(...values, 0);

  return max > 0 ? max : 1;
});

const chartPadding = 10;
const chartWidth = 100 - chartPadding * 2;
const chartHeight = 100 - chartPadding * 2;
const chartBaseline = 100 - chartPadding;

const buildPoints = (key: 'credit' | 'debit'): ChartPoint[] => {
  if (!financialTrend.value.length) {
    return [];
  }

  const length = financialTrend.value.length;
  const step = length > 1 ? chartWidth / (length - 1) : 0;

  return financialTrend.value.map((point, index) => {
    const value = Number(point[key] ?? 0);
    const normalized = trendMaxValue.value > 0 ? value / trendMaxValue.value : 0;
    const x = chartPadding + step * index;
    const y = chartBaseline - normalized * chartHeight;

    return {
      x: Number(x.toFixed(2)),
      y: Number(y.toFixed(2)),
      value,
      label: point.label,
    };
  });
};

const makePath = (points: ChartPoint[]) => {
  if (!points.length) {
    return '';
  }

  return points
    .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`)
    .join(' ');
};

const makeAreaPath = (points: ChartPoint[]) => {
  if (!points.length) {
    return '';
  }

  const first = points[0];
  const last = points[points.length - 1];
  const line = makePath(points);

  return `${line} L ${last.x} ${chartBaseline} L ${first.x} ${chartBaseline} Z`;
};

const creditPoints = computed<ChartPoint[]>(() => buildPoints('credit'));
const debitPoints = computed<ChartPoint[]>(() => buildPoints('debit'));

const creditPath = computed(() => makePath(creditPoints.value));
const debitPath = computed(() => makePath(debitPoints.value));
const creditAreaPath = computed(() => makeAreaPath(creditPoints.value));
const debitAreaPath = computed(() => makeAreaPath(debitPoints.value));

const totalCredit = computed(() =>
  financialTrend.value.reduce((sum, point) => sum + Number(point.credit ?? 0), 0)
);

const totalDebit = computed(() =>
  financialTrend.value.reduce((sum, point) => sum + Number(point.debit ?? 0), 0)
);

const chartGridStyle = computed(() => ({
  gridTemplateColumns: `repeat(${Math.max(financialTrend.value.length, 1)}, minmax(0, 1fr))`,
}));

const trendHasData = computed(() =>
  financialTrend.value.some(
    (point) => Number(point.credit ?? 0) > 0 || Number(point.debit ?? 0) > 0
  )
);

const delinquencyRate = computed(() => {
  const rate = Number(delinquency.value.rate ?? 0);
  if (!Number.isFinite(rate)) {
    return 0;
  }

  return Math.min(100, Math.max(0, rate));
});

const delinquencyRateDisplay = computed(() => `${delinquencyRate.value.toFixed(1)}%`);
const delinquencyOpenValue = computed(() => formatCurrency(delinquency.value.openAmount ?? 0));
const delinquencyPaidValue = computed(() => formatCurrency(delinquency.value.paidThisMonth ?? 0));

const defaultWidgetDefinitions: Record<string, string> = {
  metrics: 'Indicadores gerais',
  financial_overview: 'Painel financeiro',
  expiring_contracts: 'Contratos a vencer',
  open_invoices: 'Faturas em aberto',
  recent_people: 'Pessoas recentes',
};

const allWidgets = ref<WidgetSettings[]>([]);
const widgetSettingsButtonRef = ref<HTMLElement | null>(null);
const widgetSettingsPanelRef = ref<HTMLElement | null>(null);
const showWidgetSettingsPanel = ref(false);
const draggedWidgetKey = ref<string | null>(null);
const savingWidgets = ref(false);
let saveWidgetsTimer: ReturnType<typeof setTimeout> | null = null;

const hydrateWidgets = (
  value?: Array<{ key: string; label?: string; hidden?: boolean; position?: number }>
) => {
  const normalized = (value && value.length
    ? value
    : Object.entries(defaultWidgetDefinitions).map(([key, label], index) => ({
        key,
        label,
        hidden: false,
        position: index,
      }))
  ).map((item, index) => ({
    key: item.key,
    label: item.label ?? defaultWidgetDefinitions[item.key] ?? item.key,
    hidden: Boolean(item.hidden ?? false),
    position: item.position ?? index,
  }));

  normalized.sort((a, b) => a.position - b.position);
  normalized.forEach((widget, index) => {
    widget.position = index;
  });

  allWidgets.value = normalized;
};

watch(
  () => props.widgets,
  (value) => {
    hydrateWidgets(value);
  },
  { immediate: true }
);

const sortedWidgets = computed(() => [...allWidgets.value].sort((a, b) => a.position - b.position));
const visibleWidgets = computed(() => sortedWidgets.value.filter((widget) => !widget.hidden));
const visibleWidgetCount = computed(() => visibleWidgets.value.length);
const hiddenWidgets = computed(() => sortedWidgets.value.filter((widget) => widget.hidden));

const normalizePositions = () => {
  const visible = allWidgets.value
    .filter((widget) => !widget.hidden)
    .sort((a, b) => a.position - b.position)
    .map((widget, index) => ({ ...widget, position: index }));

  const hidden = allWidgets.value
    .filter((widget) => widget.hidden)
    .sort((a, b) => a.position - b.position)
    .map((widget, index) => ({ ...widget, position: visible.length + index }));

  allWidgets.value = [...visible, ...hidden];
};

const saveWidgets = async () => {
  if (savingWidgets.value) {
    return;
  }

  savingWidgets.value = true;

  try {
    await axios.post('/api/dashboard/widgets', {
      widgets: allWidgets.value.map((widget, index) => ({
        key: widget.key,
        hidden: widget.hidden,
        position: index,
      })),
    });
  } catch (error) {
    console.error(error);
    notificationStore.error('Não foi possível salvar preferências do dashboard.');
  } finally {
    savingWidgets.value = false;
  }
};

const scheduleSave = () => {
  if (saveWidgetsTimer) {
    clearTimeout(saveWidgetsTimer);
  }

  saveWidgetsTimer = setTimeout(() => {
    void saveWidgets();
  }, 500);
};

const reorderVisibleWidgets = (orderedKeys: string[]) => {
  const orderMap = new Map<string, number>();
  orderedKeys.forEach((key, index) => orderMap.set(key, index));

  allWidgets.value = allWidgets.value.map((widget) =>
    orderMap.has(widget.key)
      ? { ...widget, position: orderMap.get(widget.key)! }
      : { ...widget }
  );

  normalizePositions();
};

const toggleWidgetVisibility = (key: string, visible: boolean) => {
  allWidgets.value = allWidgets.value.map((widget) =>
    widget.key === key ? { ...widget, hidden: !visible } : { ...widget }
  );

  normalizePositions();
  scheduleSave();
};

const onToggleVisibility = (widget: WidgetSettings, event: Event) => {
  const input = event.target as HTMLInputElement;
  const shouldBeVisible = input.checked;

  if (!shouldBeVisible && visibleWidgetCount.value <= 1) {
    input.checked = true;
    notificationStore.info('Mantenha pelo menos um widget visível.');

    return;
  }

  toggleWidgetVisibility(widget.key, shouldBeVisible);
};

const startDrag = (key: string) => {
  draggedWidgetKey.value = key;
};

const endDrag = () => {
  draggedWidgetKey.value = null;
};

const handleDrop = (targetKey: string) => {
  const sourceKey = draggedWidgetKey.value;

  if (!sourceKey || sourceKey === targetKey) {
    draggedWidgetKey.value = null;

    return;
  }

  const order = visibleWidgets.value.map((widget) => widget.key);
  const sourceIndex = order.indexOf(sourceKey);
  const targetIndex = order.indexOf(targetKey);

  if (sourceIndex === -1 || targetIndex === -1) {
    draggedWidgetKey.value = null;

    return;
  }

  const newOrder = [...order];
  const [moved] = newOrder.splice(sourceIndex, 1);
  newOrder.splice(targetIndex, 0, moved);

  reorderVisibleWidgets(newOrder);
  scheduleSave();
  draggedWidgetKey.value = null;
};

const flushPendingSave = () => {
  if (saveWidgetsTimer) {
    clearTimeout(saveWidgetsTimer);
    saveWidgetsTimer = null;
    void saveWidgets();
  }
};

const closeWidgetSettingsPanel = () => {
  if (!showWidgetSettingsPanel.value) {
    return;
  }

  showWidgetSettingsPanel.value = false;
  flushPendingSave();
};

const toggleWidgetSettingsPanel = () => {
  if (showWidgetSettingsPanel.value) {
    closeWidgetSettingsPanel();

    return;
  }

  showWidgetSettingsPanel.value = true;
  showAlertPanel.value = false;
};

const alertList = ref<AlertItem[]>(props.alerts ? [...props.alerts] : []);
const showAlertPanel = ref(false);
const notificationButtonRef = ref<HTMLElement | null>(null);
const alertPanelRef = ref<HTMLElement | null>(null);
const notificationStore = useNotificationStore();
const clearingAlerts = ref(false);

const toggleAlertPanel = () => {
  if (!alertList.value.length) {
    return;
  }

  showAlertPanel.value = !showAlertPanel.value;
};

const closeAlertPanel = () => {
  showAlertPanel.value = false;
};

const handleClickOutside = (event: MouseEvent) => {
  const target = event.target as Node | null;

  if (
    showAlertPanel.value &&
    target &&
    !notificationButtonRef.value?.contains(target) &&
    !alertPanelRef.value?.contains(target)
  ) {
    closeAlertPanel();
  }

  if (
    showWidgetSettingsPanel.value &&
    target &&
    !widgetSettingsButtonRef.value?.contains(target) &&
    !widgetSettingsPanelRef.value?.contains(target)
  ) {
    closeWidgetSettingsPanel();
  }
};

const clearAlerts = async () => {
  if (clearingAlerts.value) {
    return;
  }

  const keys = alertList.value.map((alert) => alert.key).filter(Boolean);

  if (!keys.length) {
    closeAlertPanel();

    return;
  }

  clearingAlerts.value = true;

  try {
    await axios.post('/api/alerts/dismiss', { keys });
    alertList.value = [];
    closeAlertPanel();
  } catch (error) {
    console.error(error);
    notificationStore.error('Não foi possível limpar os alertas. Tente novamente.');
  } finally {
    clearingAlerts.value = false;
  }
};

watch(
  () => props.alerts,
  (value) => {
    alertList.value = value ? [...value] : [];
    if (!alertList.value.length) {
      closeAlertPanel();
    }
  },
  { immediate: true }
);

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside);
});

const alertClasses = (type: AlertType) => {
  switch (type) {
    case 'danger':
      return 'border-rose-500/40 bg-rose-500/10 text-rose-100';
    case 'warning':
      return 'border-amber-400/50 bg-amber-400/10 text-amber-100';
    default:
      return 'border-indigo-500/30 bg-indigo-500/10 text-indigo-100';
  }
};
</script>

<template>
  <AuthenticatedLayout title="Dashboard">
    <template #header-actions>
      <div class="flex items-center gap-3">
        <div class="relative">
          <button
            ref="widgetSettingsButtonRef"
            type="button"
            class="inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-900 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:border-indigo-500 hover:bg-indigo-500/20 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            @click.stop="toggleWidgetSettingsPanel"
          >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h12M4 17h16" />
            </svg>
            <span>Widgets</span>
          </button>

          <transition name="fade">
            <div
              v-if="showWidgetSettingsPanel"
              ref="widgetSettingsPanelRef"
              class="absolute right-0 z-40 mt-3 w-80 rounded-2xl border border-slate-700 bg-slate-900/95 p-4 text-slate-100 shadow-2xl shadow-black/40 backdrop-blur"
            >
              <header class="mb-4 flex items-start justify-between gap-3">
                <div>
                  <h3 class="text-sm font-semibold text-white">Widgets do dashboard</h3>
                  <p class="text-xs text-slate-400">
                    Arraste para ordenar e escolha quais cards exibir.
                  </p>
                </div>
                <button
                  type="button"
                  class="rounded-md p-1 text-slate-400 transition hover:text-white"
                  @click="closeWidgetSettingsPanel"
                >
                  <span class="sr-only">Fechar</span>
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </header>

              <section class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                  Visíveis
                </p>
                <p
                  v-if="!visibleWidgets.length"
                  class="rounded-lg border border-dashed border-slate-700 px-3 py-3 text-xs text-slate-400"
                >
                  Todos os widgets estão ocultos. Ative pelo menos um card.
                </p>
                <ul v-else class="space-y-2">
                  <li
                    v-for="widget in visibleWidgets"
                    :key="`visible-${widget.key}`"
                    class="flex items-center justify-between gap-3 rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm"
                    draggable="true"
                    @dragstart="startDrag(widget.key)"
                    @dragend="endDrag"
                    @dragover.prevent
                    @drop.prevent="handleDrop(widget.key)"
                  >
                    <div class="flex items-center gap-3">
                      <span class="text-slate-500">
                        <svg
                          class="h-4 w-4"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="1.5"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" d="M5 9h14M5 15h14" />
                        </svg>
                      </span>
                      <span class="font-medium text-white">{{ widget.label }}</span>
                    </div>
                    <label class="flex items-center gap-2 text-xs text-slate-300">
                      <input
                        type="checkbox"
                        class="rounded border-slate-600 bg-slate-900 text-indigo-500 focus:ring-indigo-500"
                        :checked="!widget.hidden"
                        @change="onToggleVisibility(widget, $event)"
                      />
                      Mostrar
                    </label>
                  </li>
                </ul>
              </section>

              <section class="mt-4 space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                  Ocultos
                </p>
                <p
                  v-if="!hiddenWidgets.length"
                  class="rounded-lg border border-dashed border-slate-700 px-3 py-3 text-xs text-slate-400"
                >
                  Nenhum widget oculto.
                </p>
                <ul v-else class="space-y-2">
                  <li
                    v-for="widget in hiddenWidgets"
                    :key="`hidden-${widget.key}`"
                    class="flex items-center justify-between gap-3 rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-200"
                  >
                    <span>{{ widget.label }}</span>
                    <button
                      type="button"
                      class="inline-flex items-center gap-1 rounded-md border border-indigo-500/40 px-2 py-1 text-xs font-semibold text-indigo-200 transition hover:border-indigo-400 hover:text-indigo-100"
                      @click="toggleWidgetVisibility(widget.key, true)"
                    >
                      <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" />
                      </svg>
                      <span>Mostrar</span>
                    </button>
                  </li>
                </ul>
              </section>

              <footer class="mt-4 flex items-center justify-between text-xs text-slate-400">
                <span>As preferências são salvas automaticamente.</span>
                <span v-if="savingWidgets" class="flex items-center gap-2 text-indigo-300">
                  <svg class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 12a8 8 0 018-8m8 8a8 8 0 01-8 8" />
                  </svg>
                  Salvando...
                </span>
              </footer>
            </div>
          </transition>
        </div>

        <div class="relative">
          <button
            ref="notificationButtonRef"
            type="button"
            class="relative inline-flex items-center justify-center rounded-full border border-slate-700 bg-slate-900 px-3 py-3 text-slate-200 transition hover:border-indigo-500 hover:bg-indigo-500/20 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            :class="{ 'cursor-default opacity-70': !alertList.length }"
            @click.stop="toggleAlertPanel"
            :title="alertList.length ? 'Abrir alertas de vencimento' : 'Nenhum alerta pendente'"
          >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M14.857 17.657a2 2 0 001.414-.586L18 15.343V11a6 6 0 10-12 0v4.343l1.729 1.728a2 2 0 001.414.586H14.857z"
              />
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 21h6" />
            </svg>
            <span
              v-if="alertList.length"
              class="absolute -right-1 -top-1 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[0.65rem] font-semibold text-white"
            >
              {{ alertList.length > 9 ? '9+' : alertList.length }}
            </span>
          </button>

          <transition name="fade">
            <div
              v-if="showAlertPanel"
              ref="alertPanelRef"
              class="absolute right-0 z-40 mt-3 w-96 rounded-2xl border border-slate-700 bg-slate-900/95 p-4 shadow-2xl shadow-black/40 backdrop-blur"
            >
              <header class="mb-3 flex items-center justify-between gap-3">
                <div>
                  <h3 class="text-sm font-semibold text-white">Alertas recentes</h3>
                  <p class="text-xs text-slate-400">Acompanhe contratos e faturas sensíveis.</p>
                </div>
                <div class="flex items-center gap-2">
                  <button
                    v-if="alertList.length"
                    type="button"
                    class="rounded-md px-2 py-1 text-xs font-semibold text-slate-300 transition hover:text-white hover:bg-slate-800/80 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="clearingAlerts"
                    @click.prevent="clearAlerts"
                  >
                    {{ clearingAlerts ? 'Limpando...' : 'Limpar tudo' }}
                  </button>
                  <button
                    type="button"
                    class="rounded-md p-1 text-slate-400 transition hover:text-white"
                    @click="closeAlertPanel"
                  >
                    <span class="sr-only">Fechar</span>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </header>

              <div
                v-if="!alertList.length"
                class="rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-6 text-center text-xs text-slate-400"
              >
                Sem alertas no momento.
              </div>

              <ul v-else class="space-y-3">
                <li
                  v-for="(alert, index) in alertList"
                  :key="`${alert.title}-${index}`"
                  class="rounded-lg border px-3 py-3 text-sm shadow-inner shadow-black/20"
                  :class="alertClasses(alert.type)"
                >
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <p class="text-xs uppercase tracking-wide text-slate-300/80">
                        {{
                          alert.type === 'danger'
                            ? 'Crítico'
                            : alert.type === 'warning'
                              ? 'Atenção'
                              : 'Informativo'
                        }}
                      </p>
                      <h4 class="text-sm font-semibold text-white">{{ alert.title }}</h4>
                      <p class="mt-1 text-xs text-slate-100/90">{{ alert.message }}</p>
                    </div>
                    <svg
                      v-if="alert.type === 'danger'"
                      class="mt-0.5 h-5 w-5 text-rose-200"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 9v4m0 4h.01M5.071 19h13.858a2 2 0 001.732-3L13.732 5a2 2 0 00-3.464 0L3.339 16a2 2 0 001.732 3z"
                      />
                    </svg>
                    <svg
                      v-else-if="alert.type === 'warning'"
                      class="mt-0.5 h-5 w-5 text-amber-200"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 8v4m0 4h.01M9.172 4.222l-6.36 11.024A2 2 0 004.512 19h14.976a2 2 0 001.7-3.754L12.828 4.222a2 2 0 00-3.656 0z"
                      />
                    </svg>
                    <svg
                      v-else
                      class="mt-0.5 h-5 w-5 text-indigo-200"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M13 16h-1v-4h-1m1-4h.01M12 4a8 8 0 100 16 8 8 0 000-16z"
                      />
                    </svg>
                  </div>
                  <div v-if="alert.action" class="mt-3">
                    <Link
                      :href="alert.action.href"
                      class="inline-flex items-center gap-2 rounded-lg border border-current px-3 py-1 text-xs font-semibold transition hover:bg-white/15"
                      @click="closeAlertPanel"
                    >
                      {{ alert.action.label }}
                    </Link>
                  </div>
                </li>
              </ul>
            </div>
          </transition>
        </div>
      </div>
    </template>

    <div class="space-y-8 text-slate-100">
      <div
        v-if="visibleWidgets.length"
        class="grid gap-6 xl:grid-cols-2"
      >
        <template v-for="widget in visibleWidgets" :key="widget.key">
          <div
            v-if="widget.key === 'metrics'"
            class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40 xl:col-span-2"
          >
            <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
              <article
                class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-inner shadow-black/30"
              >
                <p class="text-sm font-medium text-slate-300">Imóveis cadastrados</p>
                <p class="mt-3 text-4xl font-semibold text-white">
                  {{ metrics.propertiesTotal.toLocaleString('pt-BR') }}
                </p>
                <div class="mt-4">
                  <div class="flex items-center justify-between text-xs text-slate-400">
                    <span>Disponíveis</span>
                    <span>{{ metrics.propertiesAvailable.toLocaleString('pt-BR') }}</span>
                  </div>
                  <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-800">
                    <div
                      class="h-2 rounded-full bg-emerald-500"
                      :style="{ width: propertyOccupancy.availablePercent + '%' }"
                    />
                  </div>
                  <p class="mt-2 text-xs text-emerald-300">
                    {{ propertyOccupancy.availablePercent }}% do portfólio disponível
                  </p>
                </div>
              </article>

              <article
                class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-inner shadow-black/30"
              >
                <p class="text-sm font-medium text-slate-300">Contratos ativos</p>
                <p class="mt-3 text-4xl font-semibold text-white">
                  {{ metrics.activeContracts.toLocaleString('pt-BR') }}
                </p>
                <p class="mt-4 text-xs font-medium text-amber-300">
                  {{ metrics.contractsExpiringSoon }} vence(m) nos próximos 30 dias
                </p>
              </article>

              <article
                class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-inner shadow-black/30"
              >
                <p class="text-sm font-medium text-slate-300">Faturas em aberto</p>
                <p class="mt-3 text-4xl font-semibold text-white">
                  {{ metrics.openInvoices.toLocaleString('pt-BR') }}
                </p>
                <p class="mt-4 text-xs font-medium text-rose-300">
                  {{ metrics.overdueInvoices }} fatura(s) vencida(s)
                </p>
              </article>

              <article
                class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-inner shadow-black/30"
              >
                <p class="text-sm font-medium text-slate-300">Fluxo financeiro</p>
                <p class="mt-3 text-2xl font-semibold text-white">
                  {{ formatCurrency(metrics.paidThisMonth) }} recebidos no mês
                </p>
                <p class="mt-3 text-xs text-slate-400">
                  {{ formatCurrency(metrics.openAmount) }} em aberto
                </p>
              </article>
            </section>
          </div>

          <div
            v-else-if="widget.key === 'financial_overview'"
            class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
          >
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
              <div>
                <p class="text-sm font-semibold text-white">Painel financeiro</p>
                <p class="text-xs text-slate-400">Recebimentos x despesas (últimos 6 meses)</p>
              </div>
              <div class="text-left md:text-right">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                  Inadimplência
                </p>
                <p class="text-2xl font-semibold text-rose-300">{{ delinquencyRateDisplay }}</p>
                <p class="text-xs text-slate-500">Em aberto {{ delinquencyOpenValue }}</p>
                <p class="text-xs text-slate-500">Recebido no mês {{ delinquencyPaidValue }}</p>
              </div>
            </div>

            <div v-if="financialTrend.length" class="mt-6 space-y-4">
              <div class="h-48 w-full overflow-hidden rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                <svg viewBox="0 0 100 100" class="h-full w-full" preserveAspectRatio="none">
                  <defs>
                    <linearGradient id="creditGradient" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stop-color="#34d399" stop-opacity="0.4" />
                      <stop offset="100%" stop-color="#34d399" stop-opacity="0" />
                    </linearGradient>
                    <linearGradient id="debitGradient" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stop-color="#f87171" stop-opacity="0.35" />
                      <stop offset="100%" stop-color="#f87171" stop-opacity="0" />
                    </linearGradient>
                  </defs>

                  <rect x="0" y="0" width="100" height="100" fill="none" />
                  <line x1="0" :y1="chartBaseline" x2="100" :y2="chartBaseline" stroke="#1f2937" stroke-width="0.6" />
                  <line x1="0" y1="50" x2="100" y2="50" stroke="#1f2937" stroke-width="0.4" stroke-dasharray="2 3" />
                  <line x1="0" y1="10" x2="100" y2="10" stroke="#1f2937" stroke-width="0.4" stroke-dasharray="2 3" />

                  <path
                    v-if="trendHasData"
                    :d="debitAreaPath"
                    fill="url(#debitGradient)"
                    stroke="none"
                    opacity="0.8"
                  />
                  <path
                    v-if="trendHasData"
                    :d="debitPath"
                    fill="none"
                    stroke="#f87171"
                    stroke-width="1.6"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    vector-effect="non-scaling-stroke"
                  />
                  <path
                    v-if="trendHasData"
                    :d="creditAreaPath"
                    fill="url(#creditGradient)"
                    stroke="none"
                    opacity="0.8"
                  />
                  <path
                    v-if="trendHasData"
                    :d="creditPath"
                    fill="none"
                    stroke="#34d399"
                    stroke-width="1.6"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    vector-effect="non-scaling-stroke"
                  />

                  <g v-if="trendHasData">
                    <circle
                      v-for="point in debitPoints"
                      :key="`debit-${point.label}`"
                      :cx="point.x"
                      :cy="point.y"
                      r="1.4"
                      class="fill-rose-300"
                    />
                    <circle
                      v-for="point in creditPoints"
                      :key="`credit-${point.label}`"
                      :cx="point.x"
                      :cy="point.y"
                      r="1.4"
                      class="fill-emerald-300"
                    />
                  </g>
                </svg>
              </div>

              <div class="flex flex-wrap items-center gap-4 text-xs">
                <span class="inline-flex items-center gap-2 text-emerald-300">
                  <span class="h-2 w-2 rounded-full bg-emerald-400" />
                  Recebimentos {{ formatCurrency(totalCredit) }}
                </span>
                <span class="inline-flex items-center gap-2 text-rose-300">
                  <span class="h-2 w-2 rounded-full bg-rose-400" />
                  Despesas {{ formatCurrency(totalDebit) }}
                </span>
              </div>

              <div class="grid gap-3 text-center text-xs text-slate-400" :style="chartGridStyle">
                <div
                  v-for="point in financialTrend"
                  :key="`trend-${point.key}`"
                  class="rounded-lg border border-slate-800 bg-slate-950/40 px-2 py-3"
                >
                  <p class="font-medium text-slate-200">{{ point.label }}</p>
                  <p class="mt-1 text-[0.7rem] text-emerald-300">
                    {{ formatCurrency(point.credit) }}
                  </p>
                  <p class="text-[0.7rem] text-rose-300">
                    {{ formatCurrency(point.debit) }}
                  </p>
                </div>
              </div>
            </div>

            <div
              v-else
              class="mt-6 rounded-xl border border-dashed border-slate-800 bg-slate-900/40 px-4 py-12 text-center text-sm text-slate-400"
            >
              Nenhum lançamento financeiro recente.
            </div>
          </div>

          <div
            v-else-if="widget.key === 'expiring_contracts'"
            class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
          >
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-semibold text-white">Contratos a vencer</p>
                <p class="text-xs text-slate-400">Próximos 30 dias</p>
              </div>
            </div>

            <div
              v-if="!expiringContracts.length"
              class="mt-8 rounded-xl border border-dashed border-slate-800 bg-slate-900/40 px-4 py-12 text-center text-sm text-slate-400"
            >
              Nenhum contrato com vencimento próximo.
            </div>

            <ul v-else class="mt-6 space-y-4">
              <li
                v-for="contract in expiringContracts"
                :key="contract.id"
                class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-4 shadow-inner shadow-black/30"
              >
                <div class="flex items-center justify-between text-sm">
                  <div>
                    <p class="font-medium text-white">{{ contract.code }}</p>
                    <p class="text-xs text-slate-400">Imóvel {{ contract.imovel ?? 'N/A' }}</p>
                  </div>
                  <div class="text-right text-xs text-slate-400">
                    <p>{{ formatDate(contract.endsAt) }}</p>
                    <p class="text-amber-300">{{ formatDays(contract.daysLeft) }}</p>
                  </div>
                </div>
              </li>
            </ul>
          </div>

          <div
            v-else-if="widget.key === 'open_invoices'"
            class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
          >
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-semibold text-white">Faturas em aberto</p>
                <p class="text-xs text-slate-400">Controle de vencimentos</p>
              </div>
            </div>

            <div
              v-if="!openInvoices.length"
              class="mt-8 rounded-xl border border-dashed border-slate-800 bg-slate-900/40 px-4 py-12 text-center text-sm text-slate-400"
            >
              Nenhuma fatura aberta.
            </div>

            <ul v-else class="mt-6 space-y-4">
              <li
                v-for="invoice in openInvoices"
                :key="invoice.id"
                class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-4 shadow-inner shadow-black/30"
              >
                <div class="flex items-center justify-between text-sm">
                  <div>
                    <p class="font-medium text-white">Fatura #{{ invoice.id }}</p>
                    <p class="text-xs text-slate-400">
                      Contrato {{ invoice.contract ?? 'N/A' }} — Imóvel {{ invoice.property ?? 'N/A' }}
                    </p>
                  </div>
                  <div class="text-right text-xs text-slate-400">
                    <p>{{ formatDate(invoice.dueDate) }}</p>
                    <p
                      :class="[
                        'font-semibold',
                        invoice.lateDays && invoice.lateDays > 0 ? 'text-rose-300' : 'text-amber-300',
                      ]"
                    >
                      {{ formatDays(invoice.lateDays) }}
                    </p>
                  </div>
                </div>
              </li>
            </ul>
          </div>

          <div
            v-else-if="widget.key === 'recent_people'"
            class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40 xl:col-span-2"
          >
            <header class="mb-6 flex items-center justify-between">
              <div>
                <p class="text-sm font-semibold text-white">Pessoas adicionadas recentemente</p>
                <p class="text-xs text-slate-400">
                  Últimos cadastros de pessoas com papéis associados.
                </p>
              </div>
            </header>

            <div
              v-if="!recentPeople.length"
              class="rounded-xl border border-dashed border-slate-800 bg-slate-900/40 px-4 py-12 text-center text-sm text-slate-400"
            >
              Nenhum cadastro recente.
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full divide-y divide-slate-800 text-sm text-slate-100">
                <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
                  <tr>
                    <th class="px-4 py-3 text-left">Nome</th>
                    <th class="px-4 py-3 text-left">Documento</th>
                    <th class="px-4 py-3 text-left">Tipo</th>
                    <th class="px-4 py-3 text-left">Papéis</th>
                    <th class="px-4 py-3 text-left">Criado em</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                  <tr v-for="person in recentPeople" :key="person.id" class="hover:bg-slate-900/60">
                    <td class="px-4 py-3 text-slate-200">{{ person.name }}</td>
                    <td class="px-4 py-3 text-slate-300">{{ person.document ?? '-' }}</td>
                    <td class="px-4 py-3 text-slate-300">{{ person.type }}</td>
                    <td class="px-4 py-3 text-slate-300">
                      <span v-if="person.roles.length" class="inline-flex flex-wrap gap-1">
                        <span
                          v-for="role in person.roles"
                          :key="role"
                          class="rounded-full border border-slate-700 px-2 py-0.5 text-xs text-slate-300"
                        >
                          {{ role }}
                        </span>
                      </span>
                      <span v-else>-</span>
                    </td>
                    <td class="px-4 py-3 text-slate-300">{{ person.createdAt ?? '-' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </template>
      </div>

      <div
        v-else
        class="rounded-2xl border border-dashed border-slate-800 bg-slate-900/60 p-6 text-center text-sm text-slate-400"
      >
        Todos os widgets estão ocultos. Utilize o botão "Widgets" para reativar os cards.
      </div>
    </div>
  </AuthenticatedLayout>
</template>
