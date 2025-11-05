<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TransactionFormModal from '@/Components/Financeiro/TransactionFormModal.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { computed, nextTick, onMounted, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { formatDate } from '@/utils/date';
import { useNotificationStore } from '@/Stores/notifications';
import type { TransactionPayload } from '@/Components/Financeiro/TransactionForm.vue';

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

interface PayableTodayItem {
  id: number;
  description: string;
  amount: number;
  dueDate: string | null;
  status: string;
  costCenter?: string | null;
  link?: string | null;
}

interface PayableTodaySummary {
  count: number;
  total: number;
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
  billed: number;
  received: number;
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

interface FinanceOption {
  id: number;
  nome: string;
  codigo?: string | null;
  parent_id?: number | null;
}

interface FinancePersonOption extends FinanceOption {
  papeis?: string[] | null;
}

interface FinancePropertyOption {
  id: number;
  titulo?: string | null;
  codigo_interno?: string | null;
}

interface FinancePermissions {
  update: boolean;
  delete: boolean;
  reconcile: boolean;
}

const props = defineProps<{
  metrics?: Partial<Metrics>;
  expiringContracts: ExpiringContract[];
  openInvoices: OpenInvoice[];
  payablesToday?: PayableTodayItem[];
  payablesTodaySummary?: Partial<PayableTodaySummary>;
  alerts?: AlertItem[];
  widgets?: Array<{ key: string; label: string; hidden?: boolean; position?: number }>;
  financialTrend?: FinancialTrendPoint[];
  delinquency?: Partial<DelinquencySummary>;
  financeAccounts?: FinanceOption[];
  financeCostCenters?: FinanceOption[];
  financePeople?: FinancePersonOption[];
  financeProperties?: FinancePropertyOption[];
  financePermissions?: Partial<FinancePermissions>;
}>();

const page = usePage();
const abilities = computed<string[]>(() => (page.props as any)?.auth?.abilities ?? []);
const canUseAssistant = computed(() => abilities.value.includes('assistant.use'));

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

const payablesToday = computed<PayableTodayItem[]>(() => props.payablesToday ?? []);
const payablesTodaySummary = computed<PayableTodaySummary>(() => ({
  count: Number(props.payablesTodaySummary?.count ?? 0),
  total: Number(props.payablesTodaySummary?.total ?? 0),
}));

const financeAccounts = computed<FinanceOption[]>(() => props.financeAccounts ?? []);
const financeCostCenters = computed<FinanceOption[]>(() => props.financeCostCenters ?? []);
const financePeople = computed<FinancePersonOption[]>(() => props.financePeople ?? []);
const financeProperties = computed<FinancePropertyOption[]>(() => props.financeProperties ?? []);
const financePermissions = computed<FinancePermissions>(() => ({
  update: Boolean(props.financePermissions?.update),
  delete: Boolean(props.financePermissions?.delete),
  reconcile: Boolean(props.financePermissions?.reconcile),
}));

const trendMaxValue = computed(() => {
  const values = financialTrend.value.flatMap((point) => [
    Number(point.billed ?? 0),
    Number(point.received ?? 0),
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

const buildPoints = (key: 'billed' | 'received'): ChartPoint[] => {
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

const billedPoints = computed<ChartPoint[]>(() => buildPoints('billed'));
const receivedPoints = computed<ChartPoint[]>(() => buildPoints('received'));

const billedPath = computed(() => makePath(billedPoints.value));
const receivedPath = computed(() => makePath(receivedPoints.value));
const billedAreaPath = computed(() => makeAreaPath(billedPoints.value));
const receivedAreaPath = computed(() => makeAreaPath(receivedPoints.value));

const totalBilled = computed(() =>
  financialTrend.value.reduce((sum, point) => sum + Number(point.billed ?? 0), 0)
);

const totalReceived = computed(() =>
  financialTrend.value.reduce((sum, point) => sum + Number(point.received ?? 0), 0)
);

const chartGridStyle = computed(() => ({
  gridTemplateColumns: `repeat(${Math.max(financialTrend.value.length, 1)}, minmax(0, 1fr))`,
}));

const trendHasData = computed(() =>
  financialTrend.value.some((point) => Number(point.billed ?? 0) > 0 || Number(point.received ?? 0) > 0)
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
const payablesTodayTotalDisplay = computed(() => formatCurrency(payablesTodaySummary.value.total ?? 0));

const payableStatusLabel = (status: string) => {
  const normalized = (status ?? '').toLowerCase();

  if (normalized === 'atrasado') {
    return 'Em atraso';
  }

  if (normalized === 'pendente' || normalized === 'planejado') {
    return 'Pendente';
  }

  if (normalized === 'pago') {
    return 'Pago';
  }

  if (normalized === 'cancelado') {
    return 'Cancelado';
  }

  return status || '-';
};

const payableStatusClasses = (status: string) => {
  const normalized = (status ?? '').toLowerCase();

  if (normalized === 'atrasado') {
    return 'bg-rose-500/20 text-rose-100 border border-rose-500/40';
  }

  if (normalized === 'pendente' || normalized === 'planejado') {
    return 'bg-amber-500/20 text-amber-100 border border-amber-500/40';
  }

  if (normalized === 'pago') {
    return 'bg-emerald-500/15 text-emerald-200 border border-emerald-500/40';
  }

  return 'bg-slate-800/60 text-slate-200 border border-slate-700/40';
};

const defaultWidgetDefinitions: Record<string, string> = {
  metrics: 'Indicadores gerais',
  financial_overview: 'Faturamento x recebimentos',
  delinquency: 'Inadimplência',
  payables_today: 'Contas a pagar (hoje)',
  expiring_contracts: 'Contratos a vencer',
  open_invoices: 'Faturas em aberto',
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
const assistantButtonRef = ref<HTMLElement | null>(null);
const alertPanelRef = ref<HTMLElement | null>(null);
const notificationStore = useNotificationStore();
const clearingAlerts = ref(false);

const showAssistantPanel = ref(false);
const assistantMessages = ref<Array<{ role: 'assistant' | 'user'; text: string }>>([
  {
    role: 'assistant',
    text: 'Olá! Posso gerar um resumo financeiro ou localizar faturas e contas a pagar. O que você precisa agora?',
  },
]);
const assistantInput = ref('');
const assistantLoading = ref(false);
const assistantPanelRef = ref<HTMLElement | null>(null);
const assistantMessagesEndRef = ref<HTMLElement | null>(null);
const assistantContext = ref<Record<string, any> | null>(null);
const assistantQuickPrompts = [
  { label: 'Resumo do dia', message: 'Resumo do dia' },
  { label: 'Faturas em atraso', message: 'Me fale sobre as faturas em atraso' },
  { label: 'Contas a pagar', message: 'Quais contas a pagar hoje?' },
  { label: 'Contratos ativos', message: 'Quantos contratos estão ativos?' },
];
const minAssistantPanelHeight = 280;
const maxAssistantPanelHeight = 640;
const assistantPanelHeight = ref(320);
const isResizingAssistant = ref(false);
let assistantResizeStartY = 0;
let assistantResizeStartHeight = 320;

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

  if (
    showAssistantPanel.value &&
    target &&
    !assistantButtonRef.value?.contains(target) &&
    !assistantPanelRef.value?.contains(target)
  ) {
    closeAssistantPanel();
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

const loadingPayableId = ref<number | null>(null);
const transactionModal = reactive<{ visible: boolean; transaction: TransactionPayload | null }>({
  visible: false,
  transaction: null,
});

const mapEntryToFormPayload = (entry: any): TransactionPayload => ({
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

const closeTransactionModal = () => {
  transactionModal.visible = false;
  transactionModal.transaction = null;
};

const refreshPayables = () => {
  router.reload({ only: ['payablesToday', 'payablesTodaySummary'] });
};

const handleTransactionSaved = () => {
  closeTransactionModal();
  refreshPayables();
};

const handleTransactionDeleted = () => {
  closeTransactionModal();
  refreshPayables();
};

const openPayableDetails = async (payable: PayableTodayItem) => {
  if (!payable?.id) {
    notificationStore.error('Lançamento inválido.');
    return;
  }

  loadingPayableId.value = payable.id;
  transactionModal.transaction = null;

  try {
    const { data } = await axios.get(`/api/financeiro/journal-entries/${payable.id}`);
    const entry = data?.data ?? {};
    transactionModal.transaction = mapEntryToFormPayload(entry);
    transactionModal.visible = true;
  } catch (error: any) {
    const message = error?.response?.data?.message ?? 'Não foi possível carregar o lançamento.';
    notificationStore.error(message);
  } finally {
    loadingPayableId.value = null;
  }
};

const startAssistantResize = (event: MouseEvent) => {
  if (!canUseAssistant.value) {
    return;
  }

  event.preventDefault();
  assistantResizeStartY = event.clientY;
  assistantResizeStartHeight = assistantPanelHeight.value;
  isResizingAssistant.value = true;
  document.addEventListener('mousemove', onAssistantResize);
  document.addEventListener('mouseup', stopAssistantResize);
};

const onAssistantResize = (event: MouseEvent) => {
  if (!isResizingAssistant.value) {
    return;
  }

  const delta = event.clientY - assistantResizeStartY;
  let nextHeight = assistantResizeStartHeight + delta;
  nextHeight = Math.min(maxAssistantPanelHeight, Math.max(minAssistantPanelHeight, nextHeight));
  assistantPanelHeight.value = nextHeight;
};

const stopAssistantResize = () => {
  if (!isResizingAssistant.value) {
    document.removeEventListener('mousemove', onAssistantResize);
    document.removeEventListener('mouseup', stopAssistantResize);
    return;
  }

  isResizingAssistant.value = false;
  document.removeEventListener('mousemove', onAssistantResize);
  document.removeEventListener('mouseup', stopAssistantResize);

  if (typeof window !== 'undefined') {
    window.localStorage.setItem('assistantPanelHeight', String(Math.round(assistantPanelHeight.value)));
  }
};

const scrollAssistantToBottom = () => {
  nextTick(() => assistantMessagesEndRef.value?.scrollIntoView({ behavior: 'smooth' }));
};

const toggleAssistantPanel = () => {
  if (!canUseAssistant.value) {
    return;
  }

  showAssistantPanel.value = !showAssistantPanel.value;
  if (showAssistantPanel.value) {
    showAlertPanel.value = false;
    closeWidgetSettingsPanel();
    nextTick(() => {
      const textarea = assistantPanelRef.value?.querySelector<HTMLTextAreaElement>('textarea');
      textarea?.focus();
    });
    scrollAssistantToBottom();
  }
};

const closeAssistantPanel = () => {
  showAssistantPanel.value = false;
};

watch(assistantMessages, scrollAssistantToBottom, { deep: true });

watch(canUseAssistant, (value) => {
  if (!value) {
    showAssistantPanel.value = false;
  }
});

const sendAssistantMessage = async () => {
  if (!canUseAssistant.value) {
    return;
  }

  if (assistantLoading.value) {
    return;
  }

  const content = assistantInput.value.trim();

  if (!content) {
    return;
  }

  assistantMessages.value.push({ role: 'user', text: content });
  assistantInput.value = '';
  assistantLoading.value = true;
  assistantContext.value = null;

  try {
    const { data } = await axios.post('/api/finance-assistant', { message: content });
    const reply = data?.data?.reply ?? 'Não consegui gerar uma resposta no momento.';
    assistantMessages.value.push({ role: 'assistant', text: reply });
    assistantContext.value = data?.data?.context ?? null;
  } catch (error: any) {
    const message = error?.response?.data?.message ?? 'Não foi possível consultar o assistente agora.';
    assistantMessages.value.push({ role: 'assistant', text: message });
    assistantContext.value = null;
  } finally {
    assistantLoading.value = false;
    scrollAssistantToBottom();
  }
};

const triggerQuickPrompt = async (prompt: string) => {
  if (!canUseAssistant.value) {
    return;
  }

  if (assistantLoading.value) {
    return;
  }

  if (!showAssistantPanel.value) {
    toggleAssistantPanel();
    await nextTick();
  }

  assistantInput.value = prompt;
  await nextTick();
  void sendAssistantMessage();
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

  if (typeof window !== 'undefined') {
    const storedHeight = Number(window.localStorage.getItem('assistantPanelHeight'));
    if (!Number.isNaN(storedHeight) && storedHeight >= minAssistantPanelHeight && storedHeight <= maxAssistantPanelHeight) {
      assistantPanelHeight.value = storedHeight;
    }
  }
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside);
  stopAssistantResize();
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

        <div v-if="canUseAssistant" class="relative">
          <button
            ref="assistantButtonRef"
            type="button"
            class="relative inline-flex items-center justify-center rounded-full border border-slate-700 bg-slate-900 px-3 py-3 text-slate-200 transition hover:border-indigo-500 hover:bg-indigo-500/20 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            @click.stop="toggleAssistantPanel"
            :title="showAssistantPanel ? 'Fechar assistente' : 'Abrir assistente financeiro'"
          >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M7 8h10M7 12h6m-1 8l-4-3H7a4 4 0 01-4-4V7a4 4 0 014-4h10a4 4 0 014 4v6a4 4 0 01-4 4h-1l-4 3z"
              />
            </svg>
          </button>
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
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <p class="text-sm font-semibold text-white">Faturamento x recebimentos</p>
                <p class="text-xs text-slate-400">Comparativo mensal de faturas emitidas e recebidas</p>
              </div>
              <div class="flex flex-wrap gap-6 text-xs text-slate-400">
                <div>
                  <p class="uppercase tracking-wide text-slate-500">Emitido (6 meses)</p>
                  <p class="text-lg font-semibold text-rose-300">{{ formatCurrency(totalBilled) }}</p>
                </div>
                <div>
                  <p class="uppercase tracking-wide text-slate-500">Recebido (6 meses)</p>
                  <p class="text-lg font-semibold text-emerald-300">{{ formatCurrency(totalReceived) }}</p>
                </div>
                <div>
                  <p class="uppercase tracking-wide text-slate-500">Recebido no mês</p>
                  <p class="text-lg font-semibold text-emerald-200">{{ delinquencyPaidValue }}</p>
                </div>
                <div>
                  <p class="uppercase tracking-wide text-slate-500">Em aberto</p>
                  <p class="text-lg font-semibold text-rose-200">{{ delinquencyOpenValue }}</p>
                </div>
              </div>
            </div>

            <div v-if="financialTrend.length" class="mt-6 space-y-4">
              <div class="h-48 w-full overflow-hidden rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                <svg viewBox="0 0 100 100" class="h-full w-full" preserveAspectRatio="none">
                  <defs>
                    <linearGradient id="billedGradient" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stop-color="#fb7185" stop-opacity="0.35" />
                      <stop offset="100%" stop-color="#fb7185" stop-opacity="0" />
                    </linearGradient>
                    <linearGradient id="receivedGradient" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stop-color="#34d399" stop-opacity="0.35" />
                      <stop offset="100%" stop-color="#34d399" stop-opacity="0" />
                    </linearGradient>
                  </defs>

                  <path v-if="trendHasData" :d="billedAreaPath" fill="url(#billedGradient)" stroke="none" opacity="0.8" />
                  <path v-if="trendHasData" :d="billedPath" fill="none" stroke="#fb7185" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                  <path v-if="trendHasData" :d="receivedAreaPath" fill="url(#receivedGradient)" stroke="none" opacity="0.8" />
                  <path v-if="trendHasData" :d="receivedPath" fill="none" stroke="#34d399" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />

                  <g v-if="trendHasData">
                    <circle
                      v-for="point in billedPoints"
                      :key="`billed-${point.label}`"
                      :cx="point.x"
                      :cy="point.y"
                      r="1.4"
                      class="fill-rose-200"
                    />
                    <circle
                      v-for="point in receivedPoints"
                      :key="`received-${point.label}`"
                      :cx="point.x"
                      :cy="point.y"
                      r="1.4"
                      class="fill-emerald-200"
                    />
                  </g>
                </svg>
              </div>

              <div class="flex flex-wrap items-center gap-4 text-xs">
                <span class="inline-flex items-center gap-2 text-rose-300">
                  <span class="h-2 w-2 rounded-full bg-rose-400" />
                  Emitido {{ formatCurrency(totalBilled) }}
                </span>
                <span class="inline-flex items-center gap-2 text-emerald-300">
                  <span class="h-2 w-2 rounded-full bg-emerald-400" />
                  Recebido {{ formatCurrency(totalReceived) }}
                </span>
              </div>

              <div class="grid gap-3 text-center text-xs text-slate-400" :style="chartGridStyle">
                <div
                  v-for="point in financialTrend"
                  :key="`trend-${point.key}`"
                  class="rounded-lg border border-slate-800 bg-slate-950/40 px-2 py-3"
                >
                  <p class="font-medium text-slate-200">{{ point.label }}</p>
                  <p class="mt-1 text-[0.7rem] text-rose-300">
                    Emitido {{ formatCurrency(point.billed) }}
                  </p>
                  <p class="text-[0.7rem] text-emerald-300">
                    Recebido {{ formatCurrency(point.received) }}
                  </p>
                </div>
              </div>
            </div>

            <div
              v-else
              class="mt-6 rounded-xl border border-dashed border-slate-800 bg-slate-900/40 px-4 py-12 text-center text-sm text-slate-400"
            >
              Não há faturas emitidas ou recebidas no período.
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
            v-else-if="widget.key === 'delinquency'"
            class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
          >
            <header class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-white">Inadimplência do mês</p>
                <p class="text-xs text-slate-400">
                  Percentual calculado sobre faturas emitidas e quitadas no período atual.
                </p>
              </div>
              <span class="rounded-full border border-rose-500/30 bg-rose-500/10 px-3 py-1 text-xs font-semibold text-rose-200">
                {{ delinquencyRateDisplay }}
              </span>
            </header>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
              <div class="rounded-xl border border-slate-800 bg-slate-950/50 px-4 py-6 text-center">
                <p class="text-xs uppercase tracking-wide text-slate-500">Em aberto</p>
                <p class="mt-2 text-xl font-semibold text-rose-200">{{ delinquencyOpenValue }}</p>
              </div>
              <div class="rounded-xl border border-slate-800 bg-slate-950/50 px-4 py-6 text-center">
                <p class="text-xs uppercase tracking-wide text-slate-500">Recebido no mês</p>
                <p class="mt-2 text-xl font-semibold text-emerald-200">{{ delinquencyPaidValue }}</p>
              </div>
            </div>

            <footer class="mt-6 text-xs text-slate-500">
              Atualize as faturas assim que o Bradesco confirmar a liquidação para manter o índice fiel.
            </footer>
          </div>

          <div
            v-else-if="widget.key === 'payables_today'"
            class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40 xl:col-span-2"
          >
            <header class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
              <div>
                <p class="text-sm font-semibold text-white">Contas a pagar (hoje)</p>
                <p class="text-xs text-slate-400">
                  Lançamentos financeiros com vencimento no dia corrente.
                </p>
              </div>
              <div class="text-right">
                <p class="text-xs text-slate-400">Total do dia</p>
                <p class="text-lg font-semibold text-rose-200">{{ payablesTodayTotalDisplay }}</p>
                <p class="text-xs text-slate-500">
                  {{ payablesTodaySummary.count }}
                  {{ payablesTodaySummary.count === 1 ? 'lançamento' : 'lançamentos' }}
                </p>
              </div>
            </header>

            <div
              v-if="!payablesToday.length"
              class="rounded-xl border border-dashed border-slate-800 bg-slate-900/40 px-4 py-12 text-center text-sm text-slate-400"
            >
              Nenhuma conta vence hoje.
            </div>

            <div v-else class="space-y-4">
              <ul class="space-y-3">
                <li
                  v-for="payable in payablesToday"
                  :key="`payable-${payable.id}`"
                  class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900/70 p-4 md:flex-row md:items-center md:justify-between"
                >
                  <div class="space-y-1">
                    <p class="text-sm font-semibold text-white">{{ payable.description }}</p>
                    <p class="text-xs text-slate-400">
                      <span v-if="payable.costCenter">Centro de custo: {{ payable.costCenter }}</span>
                      <span v-else>Sem centro de custo</span>
                    </p>
                  </div>
                  <div class="flex flex-wrap items-center gap-4 md:justify-end">
                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="payableStatusClasses(payable.status)">
                      {{ payableStatusLabel(payable.status) }}
                    </span>
                    <div class="text-right text-xs text-slate-400">
                      <p>Vencimento</p>
                      <p class="font-semibold text-slate-100">{{ formatDate(payable.dueDate) }}</p>
                    </div>
                    <div class="text-right">
                      <p class="text-sm font-semibold text-rose-200">{{ formatCurrency(payable.amount) }}</p>
                      <button
                        type="button"
                        class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-indigo-300 transition hover:text-indigo-100 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="loadingPayableId === payable.id"
                        @click="openPayableDetails(payable)"
                      >
                        <span>
                          {{ loadingPayableId === payable.id ? 'Abrindo...' : 'Detalhes' }}
                        </span>
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                      </button>
                    </div>
                  </div>
                </li>
              </ul>

              <div class="text-right">
                <Link
                  href="/financeiro"
                  class="inline-flex items-center gap-2 text-xs font-semibold text-indigo-300 transition hover:text-indigo-100"
                >
                  <span>Ver todos os lançamentos</span>
                  <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                  </svg>
                </Link>
              </div>
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

    <transition name="fade">
      <div
        v-if="canUseAssistant && showAssistantPanel"
        ref="assistantPanelRef"
        class="fixed bottom-24 right-6 z-40 w-full max-w-md rounded-3xl border border-slate-800 bg-slate-950/95 shadow-2xl shadow-black/50 backdrop-blur"
      >
        <header class="flex items-start justify-between gap-4 border-b border-white/5 px-5 py-4">
          <div>
            <p class="text-sm font-semibold text-white">Assistente financeiro</p>
            <p class="text-xs text-slate-400">Pergunte sobre faturas, contas a pagar e contratos.</p>
          </div>
          <button
            type="button"
            class="rounded-full p-1.5 text-slate-400 transition hover:bg-white/10 hover:text-white"
            @click="closeAssistantPanel"
          >
            <span class="sr-only">Fechar</span>
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </header>

        <div class="relative flex flex-col px-5 py-4 pb-6" :style="{ height: assistantPanelHeight + 'px' }">
          <div class="mb-3 flex flex-wrap gap-2">
            <button
              v-for="prompt in assistantQuickPrompts"
              :key="`assistant-prompt-${prompt.message}`"
              type="button"
              class="inline-flex items-center gap-1 rounded-full border border-indigo-500/40 px-3 py-1 text-xs font-semibold text-indigo-200 transition hover:border-indigo-400 hover:bg-indigo-500/10 hover:text-indigo-100"
              :disabled="assistantLoading"
              @click.prevent="triggerQuickPrompt(prompt.message)"
            >
              {{ prompt.label }}
            </button>
          </div>

          <div class="flex-1 overflow-y-auto space-y-3 pr-1">
            <div
              v-for="(msg, index) in assistantMessages"
              :key="`assistant-msg-${index}`"
              class="rounded-2xl px-4 py-3 text-sm leading-relaxed"
              :class="
                msg.role === 'assistant'
                  ? 'bg-slate-800/70 text-slate-100 border border-slate-700/80'
                  : 'bg-indigo-500/80 text-white border border-indigo-400/60 self-end'
              "
            >
              {{ msg.text }}
            </div>
            <span ref="assistantMessagesEndRef" />
          </div>

          <div
            v-if="assistantContext && (assistantContext.overdue_invoices?.length || assistantContext.payables_today_list?.length || assistantContext.payables_overdue_list?.length)"
            class="mt-3 space-y-3 text-xs text-slate-300"
          >
            <div
              v-if="assistantContext.overdue_invoices?.length"
              class="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-3 text-rose-100"
            >
              <p class="mb-2 font-semibold text-rose-200">Faturas em atraso (top {{ assistantContext.overdue_invoices.length }})</p>
              <ul class="space-y-1">
                <li v-for="invoice in assistantContext.overdue_invoices" :key="`assist-invoice-${invoice.id}`">
                  <span class="font-semibold">#{{ invoice.id }}</span>
                  — contrato {{ invoice.contract ?? 's/ contrato' }} — vence em {{ invoice.due_date ?? 's/data' }}
                  — {{ formatCurrency(invoice.amount) }}
                  <span v-if="invoice.days_overdue"> ({{ invoice.days_overdue }} dia(s) em atraso)</span>
                </li>
              </ul>
            </div>

            <div
              v-if="assistantContext.payables_today_list?.length"
              class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-3 text-amber-100"
            >
              <p class="mb-2 font-semibold text-amber-200">Contas com vencimento hoje</p>
              <ul class="space-y-1">
                <li v-for="item in assistantContext.payables_today_list" :key="`assist-payable-today-${item.id}`">
                  {{ item.description }} — {{ formatCurrency(item.amount) }} — {{ item.cost_center ?? 'sem centro' }}
                </li>
              </ul>
            </div>

            <div
              v-if="assistantContext.payables_overdue_list?.length"
              class="rounded-2xl border border-orange-500/30 bg-orange-500/10 p-3 text-orange-100"
            >
              <p class="mb-2 font-semibold text-orange-200">Contas em atraso (top {{ assistantContext.payables_overdue_list.length }})</p>
              <ul class="space-y-1">
                <li v-for="item in assistantContext.payables_overdue_list" :key="`assist-payable-overdue-${item.id}`">
                  {{ item.description }} — {{ formatCurrency(item.amount) }} — venc. {{ item.due_date ?? 's/data' }}
                  <span v-if="item.days_overdue"> ({{ item.days_overdue }} dia(s) em atraso)</span>
                </li>
              </ul>
            </div>
            </div>

            <form class="mt-4 space-y-3" @submit.prevent="sendAssistantMessage">
              <div class="rounded-2xl border border-slate-700 bg-slate-900/70 focus-within:border-indigo-500/60">
                <textarea
                v-model="assistantInput"
                rows="3"
                class="w-full rounded-2xl border-0 bg-transparent px-3 py-3 text-sm text-white placeholder-slate-500 focus:ring-0"
                placeholder="Ex.: Resumo do dia, faturas em atraso, contas a pagar..."
                :disabled="assistantLoading"
                @keydown.enter.exact.prevent="sendAssistantMessage"
              ></textarea>
            </div>
            <div class="flex items-center justify-between gap-3 text-xs text-slate-500">
              <span v-if="assistantLoading" class="flex items-center gap-2 text-indigo-300">
                <svg class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 12a8 8 0 018-8m8 8a8 8 0 01-8 8" />
                </svg>
                Gerando resposta...
              </span>
              <button
                type="submit"
                class="ml-auto inline-flex items-center gap-2 rounded-full bg-indigo-500 px-4 py-2 text-xs font-semibold text-white transition hover:bg-indigo-400 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="assistantLoading || !assistantInput.trim()"
              >
                Enviar
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 5l7 7-7 7" />
                </svg>
              </button>
            </div>
          </form>
          <div
            class="absolute bottom-1 left-0 right-0 h-3 cursor-ns-resize rounded-b-3xl"
            @mousedown.prevent.stop="startAssistantResize"
          ></div>
        </div>
      </div>
    </transition>

    <TransactionFormModal
      :show="transactionModal.visible"
      mode="edit"
      :transaction="transactionModal.transaction"
      :accounts="financeAccounts"
      :cost-centers="financeCostCenters"
      :people="financePeople"
      :properties="financeProperties"
      :permissions="financePermissions"
      @close="closeTransactionModal"
      @updated="handleTransactionSaved"
      @created="handleTransactionSaved"
      @deleted="handleTransactionDeleted"
    />
  </AuthenticatedLayout>
</template>
