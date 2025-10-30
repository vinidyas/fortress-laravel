<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import DatePicker from '@/Components/Form/DatePicker.vue';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';

interface AccountOption {
  id: number;
  nome: string;
}

interface LedgerRow {
  id: number;
  movement_date?: string | null;
  due_date?: string | null;
  description?: string | null;
  type?: string | null;
  type_label?: string | null;
  person?: { id: number; nome: string } | null;
  property?: { id: number; nome: string } | null;
  notes?: string | null;
  reference_code?: string | null;
  status?: string | null;
  status_label?: string | null;
  signed_amount?: number;
  absolute_amount?: number;
}

type ReportType = 'despesa' | 'receita';
type DatePreset =
  | 'today'
  | 'yesterday'
  | 'tomorrow'
  | 'thisWeek'
  | 'lastWeek'
  | 'thisMonth'
  | 'thisYear';

type DateBasis = 'movement' | 'due';

interface SavedFiltersState {
  type?: ReportType;
  financial_account_id?: string;
  financial_account_ids?: string[];
  date_from?: string;
  date_to?: string;
  status?: string;
  date_basis?: DateBasis;
}

const props = defineProps<{
  accounts: AccountOption[];
  canExport: boolean;
  initialType?: ReportType | null;
}>();

const STORAGE_KEY = 'reports:bank-ledger:wizard-filters';
const PREVIEW_LIMIT = 25;

const typeLabels: Record<ReportType, string> = {
  despesa: 'Despesas',
  receita: 'Receitas',
};

const availableTypes: Array<{ id: ReportType; label: string; description: string }> = [
  { id: 'despesa', label: 'Despesas', description: 'Extrato consolidado de pagamentos e saídas.' },
  { id: 'receita', label: 'Receitas', description: 'Lançamentos de recebimentos e entradas.' },
];

const wizardSteps = [
  { id: 1 as const, label: 'Tipo e conta' },
  { id: 2 as const, label: 'Período' },
  { id: 3 as const, label: 'Pré-visualização' },
];
type WizardStep = (typeof wizardSteps)[number]['id'];

const datePresets: Array<{ id: DatePreset; label: string }> = [
  { id: 'today', label: 'Hoje' },
  { id: 'yesterday', label: 'Ontem' },
  { id: 'tomorrow', label: 'Amanhã' },
  { id: 'thisWeek', label: 'Esta semana' },
  { id: 'lastWeek', label: 'Semana passada' },
  { id: 'thisMonth', label: 'Este mês' },
  { id: 'thisYear', label: 'Este ano' },
];

const statusOptions = [
  { value: '', label: 'Todos' },
  { value: 'open', label: 'Em aberto' },
  { value: 'settled', label: 'Quitado' },
  { value: 'overdue', label: 'Em atraso' },
  { value: 'cancelled', label: 'Cancelado' },
];

const allAccountOptions = computed(() =>
  props.accounts.map((account) => ({
    id: String(account.id),
    nome: account.nome ?? `Conta ${account.id}`,
  }))
);

const allAccountIds = computed(() => allAccountOptions.value.map((option) => option.id));

const normalizeAccountIds = (ids: string[] | undefined | null): string[] => {
  if (!ids || ids.length === 0) {
    return [];
  }

  const allowed = new Set(allAccountIds.value);

  return Array.from(new Set(ids.map((value) => String(value)))).filter((id) => allowed.has(id));
};

const getAccountName = (id: string) => {
  if (!id) {
    return 'Todos os bancos';
  }

  const option = allAccountOptions.value.find((account) => account.id === String(id));
  return option?.nome ?? 'Conta não encontrada';
};

const formatAccountsLabel = (ids: string[], fallbackId: string) => {
  let effective = normalizeAccountIds(ids);

  if (!effective.length && fallbackId) {
    effective = normalizeAccountIds([fallbackId]);
  }

  if (!effective.length) {
    return 'Todos os bancos';
  }

  if (effective.length === allAccountIds.value.length) {
    return 'Todos os bancos';
  }

  if (effective.length === 1) {
    return getAccountName(effective[0]);
  }

  const names = effective
    .map((id) => getAccountName(id))
    .filter((name) => name && name !== 'Conta não encontrada');

  if (names.length === 0) {
    return 'Todos os bancos';
  }

  if (names.length <= 3) {
    return names.join(', ');
  }

  return `${names.length} contas selecionadas`;
};

const loadSavedFilters = (): SavedFiltersState => {
  if (typeof window === 'undefined') {
    return {};
  }

  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) {
      return {};
    }

    const parsed = JSON.parse(raw) as Record<string, unknown> | null;
    if (!parsed) {
      return {};
    }

    const type = parsed.type;
    const financialAccountId = parsed.financial_account_id !== undefined && parsed.financial_account_id !== null
      ? String(parsed.financial_account_id)
      : undefined;
    const financialAccountIds = Array.isArray(parsed.financial_account_ids)
      ? parsed.financial_account_ids
          .map((value) => String(value))
          .filter((value) => value.trim() !== '')
      : undefined;

    return {
      ...(parsed as SavedFiltersState),
      type: type === 'despesa' || type === 'receita' ? (type as ReportType) : undefined,
      financial_account_id: financialAccountId,
      financial_account_ids: financialAccountIds,
    };
  } catch {
    return {};
  }
};

const savedFilters = loadSavedFilters();
const initialType = savedFilters.type ?? props.initialType ?? null;

const selection = reactive<{
  type: ReportType | '';
  financial_account_id: string;
  financial_accounts: string[];
  date_basis: DateBasis;
  date_from: string;
  date_to: string;
}>({
  type: initialType ?? '',
  financial_account_id: savedFilters.financial_account_id ?? '',
  financial_accounts: savedFilters.financial_account_ids && savedFilters.financial_account_ids.length
    ? normalizeAccountIds(savedFilters.financial_account_ids)
    : savedFilters.financial_account_id
      ? normalizeAccountIds([savedFilters.financial_account_id])
      : [],
  date_basis: savedFilters.date_basis ?? 'movement',
  date_from: savedFilters.date_from ?? '',
  date_to: savedFilters.date_to ?? '',
});

const filters = reactive<{
  type: ReportType | '';
  financial_account_id: string;
  financial_accounts: string[];
  date_basis: DateBasis;
  date_from: string;
  date_to: string;
  status: string;
}>({
  type: savedFilters.type ?? '',
  financial_account_id: savedFilters.financial_account_id ?? '',
  financial_accounts: savedFilters.financial_account_ids && savedFilters.financial_account_ids.length
    ? normalizeAccountIds(savedFilters.financial_account_ids)
    : savedFilters.financial_account_id
      ? normalizeAccountIds([savedFilters.financial_account_id])
      : [],
  date_basis: savedFilters.date_basis ?? 'movement',
  date_from: savedFilters.date_from ?? '',
  date_to: savedFilters.date_to ?? '',
  status: savedFilters.status ?? '',
});

const setSelectionAccounts = (ids: string[]) => {
  const normalized = normalizeAccountIds(ids);

  if (!normalized.length) {
    selection.financial_accounts = [...allAccountIds.value];
    selection.financial_account_id = '';
    return;
  }

  if (normalized.length === allAccountIds.value.length) {
    selection.financial_accounts = [...allAccountIds.value];
    selection.financial_account_id = '';
    return;
  }

  if (normalized.length === 1) {
    selection.financial_accounts = [...normalized];
    selection.financial_account_id = normalized[0];
    return;
  }

  selection.financial_accounts = [...normalized];
  selection.financial_account_id = '';
};

const setFiltersAccounts = (ids: string[]) => {
  const normalized = normalizeAccountIds(ids);

  if (!normalized.length) {
    filters.financial_accounts = [...allAccountIds.value];
    filters.financial_account_id = '';
    return;
  }

  if (normalized.length === allAccountIds.value.length) {
    filters.financial_accounts = [...allAccountIds.value];
    filters.financial_account_id = '';
    return;
  }

  if (normalized.length === 1) {
    filters.financial_accounts = [...normalized];
    filters.financial_account_id = normalized[0];
    return;
  }

  filters.financial_accounts = [...normalized];
  filters.financial_account_id = '';
};

const handleAccountSelect = (value: string) => {
  if (value === '') {
    setSelectionAccounts(allAccountIds.value);
  } else {
    setSelectionAccounts([value]);
  }
};

const handleAccountCheckboxChange = (id: string, checked: boolean) => {
  const normalizedId = String(id);
  const current = new Set(selection.financial_accounts);

  if (checked) {
    current.add(normalizedId);
  } else {
    current.delete(normalizedId);
  }

  const updated = normalizeAccountIds(Array.from(current));

  if (!updated.length) {
    setSelectionAccounts(allAccountIds.value);
    return;
  }

  setSelectionAccounts(updated);
};

const isAccountChecked = (id: string) => selection.financial_accounts.includes(String(id));

const selectAllAccounts = () => {
  setSelectionAccounts(allAccountIds.value);
};

setSelectionAccounts(
  selection.financial_accounts.length
    ? selection.financial_accounts
    : selection.financial_account_id
      ? [selection.financial_account_id]
      : allAccountIds.value
);

setFiltersAccounts(
  filters.financial_accounts.length
    ? filters.financial_accounts
    : filters.financial_account_id
      ? [filters.financial_account_id]
      : allAccountIds.value
);

const wizardStep = ref<WizardStep>(1);
const showWizard = ref(false);
const stepErrors = reactive<{ step1: string; step2: string }>({ step1: '', step2: '' });

const loading = ref(false);
const errorMessage = ref('');
const ledgerRows = ref<LedgerRow[]>([]);
const openingBalance = ref(0);
const closingBalance = ref(0);
const totals = ref({ inflow: 0, outflow: 0, net: 0 });
const totalRowCount = ref(0);

const hasActiveFilters = computed(() => filters.type !== '');
const hasRows = computed(() => ledgerRows.value.length > 0);
const filtersTypeLabel = computed(() =>
  filters.type ? `Relatório de ${typeLabels[filters.type]}` : 'Relatório'
);
const filtersAccountLabel = computed(() =>
  formatAccountsLabel(filters.financial_accounts, filters.financial_account_id)
);
const filtersStatusLabel = computed(() => {
  const option = statusOptions.find((status) => status.value === filters.status);
  return option?.label ?? 'Todos';
});
const filtersPeriodLabel = computed(() => {
  const from = formatDateHuman(filters.date_from) ?? 'Início';
  const to = formatDateHuman(filters.date_to) ?? 'Hoje';
  const basisLabel = filters.date_basis === 'due' ? 'Vencimento' : 'Movimento';
  return `${basisLabel}: ${from} a ${to}`;
});
const selectionTypeLabel = computed(() =>
  selection.type ? typeLabels[selection.type] : 'Selecione um tipo'
);
const selectionAccountLabel = computed(() =>
  formatAccountsLabel(selection.financial_accounts, selection.financial_account_id)
);
const selectionPeriodLabel = computed(() => {
  const from = formatDateHuman(selection.date_from) ?? 'Início';
  const to = formatDateHuman(selection.date_to) ?? 'Hoje';
  const basisLabel = selection.date_basis === 'due' ? 'Vencimento' : 'Movimento';
  return `${basisLabel}: ${from} a ${to}`;
});
const previewRows = computed(() => {
  const limit = Math.min(15, ledgerRows.value.length);
  return ledgerRows.value.slice(0, limit);
});
const previewHasMoreRows = computed(() => totalRowCount.value > previewRows.value.length);

const columnWidths = computed(() => {
  const rows = ledgerRows.value ?? [];
  const maxLength = (selector: (row: LedgerRow) => string | null | undefined) =>
    rows.reduce((max, row) => {
      const value = selector(row);
      return Math.max(max, value ? value.length : 0);
    }, 0);

  const maxSupplierLength = maxLength((row) => row.person?.nome);
  const maxPropertyLength = maxLength((row) => row.property?.nome);

  const dateWidth = 6.5;
  const dueWidth = 6.5;
  const statusWidth = 6;
  const valueWidth = 8;

  const availableWidth = 100 - (dateWidth + dueWidth + statusWidth + valueWidth);
  const descriptionMinWidth = 24;
  const supplierMinWidth = 14;
  const supplierMaxWidth = 24;
  const propertyMinWidth = 14;
  const propertyMaxWidth = 34;

  const propertyTarget = propertyMinWidth + maxPropertyLength * 0.22;
  const propertyMaxAllowed = Math.max(propertyMinWidth, availableWidth - supplierMinWidth - descriptionMinWidth);
  let propertyWidth = Math.min(propertyMaxWidth, Math.max(propertyMinWidth, propertyTarget, propertyMinWidth));
  propertyWidth = Math.min(propertyWidth, propertyMaxAllowed);

  const supplierTarget = supplierMinWidth + maxSupplierLength * 0.18;
  let supplierWidth = Math.min(supplierMaxWidth, Math.max(supplierMinWidth, supplierTarget));
  const remainingAfterProperty = availableWidth - propertyWidth;
  const supplierMaxAllowed = Math.max(supplierMinWidth, remainingAfterProperty - descriptionMinWidth);

  if (supplierMaxAllowed <= supplierMinWidth) {
    supplierWidth = supplierMinWidth;
    propertyWidth = Math.max(
      propertyMinWidth,
      Math.min(propertyWidth, availableWidth - supplierWidth - descriptionMinWidth),
    );
  } else {
    supplierWidth = Math.min(supplierWidth, supplierMaxAllowed);
  }

  const descriptionWidth = Math.max(descriptionMinWidth, availableWidth - supplierWidth - propertyWidth);

  const toPercent = (width: number) => `${width}%`;

  return {
    date: toPercent(dateWidth),
    supplier: toPercent(supplierWidth),
    description: toPercent(descriptionWidth),
    property: toPercent(propertyWidth),
    due: toPercent(dueWidth),
    status: toPercent(statusWidth),
    value: toPercent(valueWidth),
  };
});

const normalizeDate = (date: Date) => new Date(date.getFullYear(), date.getMonth(), date.getDate());
const formatDate = (date: Date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};
const addDays = (date: Date, days: number) =>
  new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);

const formatCurrency = (value: number | string) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(
    typeof value === 'string' ? Number.parseFloat(value || '0') : value ?? 0,
  );

const formatDateHuman = (value?: string | null) => {
  if (!value) {
    return null;
  }

  const [year, month, day] = value.split('-');
  if (!year || !month || !day) {
    return value;
  }

  return `${day}/${month}/${year}`;
};

const persistFilters = () => {
  if (typeof window === 'undefined') {
    return;
  }

  const payload: SavedFiltersState = {
    type: filters.type || undefined,
    financial_account_id: filters.financial_accounts.length === 1
      ? filters.financial_accounts[0]
      : filters.financial_account_id || undefined,
    financial_account_ids:
      filters.financial_accounts.length &&
      filters.financial_accounts.length !== allAccountIds.value.length
        ? filters.financial_accounts
        : undefined,
    date_from: filters.date_from || undefined,
    date_to: filters.date_to || undefined,
    status: filters.status || undefined,
    date_basis: filters.date_basis || 'movement',
  };

  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
};

const clearPersistedFilters = () => {
  if (typeof window === 'undefined') {
    return;
  }

  window.localStorage.removeItem(STORAGE_KEY);
};

const applyDatePreset = (preset: DatePreset) => {
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
    case 'lastWeek': {
      const startOfCurrentWeek = addDays(today, -today.getDay());
      start = addDays(startOfCurrentWeek, -7);
      end = addDays(start, 6);
      break;
    }
    case 'thisMonth': {
      start = new Date(today.getFullYear(), today.getMonth(), 1);
      end = today;
      break;
    }
    case 'thisYear': {
      start = new Date(today.getFullYear(), 0, 1);
      end = today;
      break;
    }
    default:
      break;
  }

  selection.date_from = formatDate(start);
  selection.date_to = formatDate(end);
};

const openWizard = (step: WizardStep = 1) => {
  stepErrors.step1 = '';
  stepErrors.step2 = '';
  wizardStep.value = step;
  showWizard.value = true;
  nextTick(() => {
    // permite que componentes internos consumam o estado atualizado
  });
};

const startWizard = () => {
  if (!selection.type && props.initialType) {
    selection.type = props.initialType;
  }
  if (!selection.financial_accounts.length) {
    if (props.accounts.length === 1) {
      setSelectionAccounts([String(props.accounts[0].id)]);
    } else {
      setSelectionAccounts(allAccountIds.value);
    }
  }
  openWizard(1);
};

const editCurrentReport = () => {
  selection.type = filters.type || selection.type || (props.initialType ?? '');
  const accountsToApply = filters.financial_accounts.length
    ? filters.financial_accounts
    : filters.financial_account_id
      ? [filters.financial_account_id]
      : allAccountIds.value;
  setSelectionAccounts(accountsToApply);
  selection.date_from = filters.date_from;
  selection.date_to = filters.date_to;
  openWizard(filters.date_from || filters.date_to ? 2 : 1);
};

const closeWizard = () => {
  showWizard.value = false;
};

const proceedToDates = () => {
  if (!selection.type) {
    stepErrors.step1 = 'Selecione o tipo de relatório antes de continuar.';
    return;
  }

  stepErrors.step1 = '';
  wizardStep.value = 2;
};

const clearSelectionDates = () => {
  selection.date_from = '';
  selection.date_to = '';
};

const confirmDates = async () => {
  if (!selection.type) {
    stepErrors.step1 = 'Selecione o tipo de relatório antes de continuar.';
    wizardStep.value = 1;
    return;
  }

  stepErrors.step2 = '';
  wizardStep.value = 3;

  filters.type = selection.type as ReportType;
  const accountsToApply = selection.financial_accounts.length
    ? selection.financial_accounts
    : selection.financial_account_id
      ? [selection.financial_account_id]
      : allAccountIds.value;

  setFiltersAccounts(accountsToApply);
  filters.date_basis = selection.date_basis;
  filters.date_from = selection.date_from;
  filters.date_to = selection.date_to;

  persistFilters();
  await loadReport();
};

const loadReport = async () => {
  if (!filters.type) {
    ledgerRows.value = [];
    openingBalance.value = 0;
    closingBalance.value = 0;
    totals.value = { inflow: 0, outflow: 0, net: 0 };
    totalRowCount.value = 0;
    return;
  }

  loading.value = true;
  errorMessage.value = '';

  try {
    const params: Record<string, any> = {
      type: filters.type,
      preview_limit: PREVIEW_LIMIT,
      date_basis: filters.date_basis,
    };

    const normalizedAccounts = normalizeAccountIds(filters.financial_accounts);

    if (normalizedAccounts.length && normalizedAccounts.length !== allAccountIds.value.length) {
      params.financial_account_ids = normalizedAccounts;
    } else if (filters.financial_account_id) {
      params.financial_account_id = filters.financial_account_id;
    }
  if (filters.date_from) {
    params.date_from = filters.date_from;
  }
    if (filters.date_to) {
      params.date_to = filters.date_to;
    }
    if (filters.status) {
      params.status = filters.status;
    }

    const { data } = await axios.get('/api/reports/bank-ledger', { params });

    ledgerRows.value = data.data ?? [];
    openingBalance.value = data.opening_balance ?? 0;
    closingBalance.value = data.closing_balance ?? 0;
    totals.value = data.totals ?? { inflow: 0, outflow: 0, net: 0 };
    const parsedTotal = Number(data.total_rows);
    totalRowCount.value = Number.isFinite(parsedTotal) ? parsedTotal : ledgerRows.value.length;
    persistFilters();
  } catch (error: any) {
    ledgerRows.value = [];
    openingBalance.value = 0;
    closingBalance.value = 0;
    totals.value = { inflow: 0, outflow: 0, net: 0 };
    totalRowCount.value = 0;
    errorMessage.value =
      error?.response?.data?.message ?? 'Não foi possível carregar o extrato detalhado.';
  } finally {
    loading.value = false;
  }
};

const exportReport = (format: 'csv' | 'pdf' | 'xlsx') => {
  if (!props.canExport || !filters.type) {
    return;
  }

  if (format === 'pdf') {
    if (!filters.date_from || !filters.date_to) {
      errorMessage.value = 'Para exportar em PDF, selecione um período de no máximo 31 dias.';
      return;
    }

    const parseIsoDate = (value: string) => {
      const [year, month, day] = value.split('-').map((segment) => Number(segment));
      return new Date(year, (month ?? 1) - 1, day ?? 1);
    };

    const startDate = parseIsoDate(filters.date_from);
    const endDate = parseIsoDate(filters.date_to);

    if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
      errorMessage.value = 'Não foi possível interpretar o período selecionado. Ajuste as datas e tente novamente.';
      return;
    }

    const start = normalizeDate(startDate).getTime();
    const end = normalizeDate(endDate).getTime();
    const rangeInDays = Math.abs(end - start) / (1000 * 60 * 60 * 24) + 1;

    if (rangeInDays > 31) {
      errorMessage.value = 'Para exportar em PDF, limite o período a no máximo 31 dias. Utilize XLSX ou CSV para períodos maiores.';
      return;
    }
  }

  const params = new URLSearchParams();
  params.append('type', filters.type);

  const normalizedAccounts = normalizeAccountIds(filters.financial_accounts);
  if (normalizedAccounts.length && normalizedAccounts.length !== allAccountIds.value.length) {
    normalizedAccounts.forEach((id) => {
      params.append('financial_account_ids[]', id);
    });
  } else if (filters.financial_account_id) {
    params.append('financial_account_id', filters.financial_account_id);
  }
  params.append('date_basis', filters.date_basis);
  if (filters.date_from) {
    params.append('date_from', filters.date_from);
  }
  if (filters.date_to) {
    params.append('date_to', filters.date_to);
  }
  if (filters.status) {
    params.append('status', filters.status);
  }

  params.append('format', format);

  const url = `/api/reports/bank-ledger/export?${params.toString()}`;
  window.open(url, '_blank', 'noopener');
};

const resetAllFilters = () => {
  filters.type = '';
  setFiltersAccounts(allAccountIds.value);
  filters.date_from = '';
  filters.date_to = '';
  filters.status = '';

  selection.type = props.initialType ?? '';
  if (props.accounts.length === 1) {
    setSelectionAccounts([String(props.accounts[0].id)]);
  } else {
    setSelectionAccounts(allAccountIds.value);
  }
  selection.date_from = '';
  selection.date_to = '';

  ledgerRows.value = [];
  openingBalance.value = 0;
  closingBalance.value = 0;
  totals.value = { inflow: 0, outflow: 0, net: 0 };
  errorMessage.value = '';
  totalRowCount.value = 0;

  clearPersistedFilters();
};

const changeStatusFilter = async (value: string) => {
  filters.status = value;
  persistFilters();
  await loadReport();
};

const toStep = (step: WizardStep) => {
  wizardStep.value = step;
};

onMounted(() => {
  if (!selection.financial_account_id && props.accounts.length === 1) {
    selection.financial_account_id = String(props.accounts[0].id);
    selection.financial_accounts = [selection.financial_account_id];
  }

  if (filters.type) {
    loadReport();
  } else if (!filters.type && props.initialType && !savedFilters.type) {
    selection.type = props.initialType;
    startWizard();
  }
});

watch(
  () => props.accounts,
  () => {
    setSelectionAccounts(
      selection.financial_accounts.length
        ? selection.financial_accounts
        : selection.financial_account_id
          ? [selection.financial_account_id]
          : allAccountIds.value
    );

    setFiltersAccounts(
      filters.financial_accounts.length
        ? filters.financial_accounts
        : filters.financial_account_id
          ? [filters.financial_account_id]
          : allAccountIds.value
    );
  },
  { deep: true }
);
</script>

<template>
  <AuthenticatedLayout title="Relatório de Despesas e Receitas">
    <Head title="Relatório de Despesas e Receitas" />

    <section
      class="space-y-6 rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
    >
      <header class="flex flex-col gap-1">
        <h1 class="text-xl font-semibold text-white">Relatório de Despesas e Receitas</h1>
        <p class="text-sm text-slate-400">
          Combine despesas e receitas no mesmo fluxo, escolhendo tipo, conta e período em poucos passos.
        </p>
      </header>

      <div class="flex flex-wrap gap-3">
        <button
          type="button"
          class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500"
          @click="startWizard"
        >
          Novo relatório
        </button>
        <button
          v-if="hasActiveFilters"
          type="button"
          class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 transition hover:bg-slate-800"
          @click="editCurrentReport"
        >
          Editar filtros
        </button>
        <button
          v-if="hasActiveFilters"
          type="button"
          class="rounded-lg border border-rose-600/40 px-4 py-2 text-sm text-rose-200 transition hover:bg-rose-600/20"
          @click="resetAllFilters"
        >
          Limpar seleção
        </button>
      </div>

      <div
        v-if="!hasActiveFilters"
        class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/60 px-6 py-10 text-center text-sm text-slate-400"
      >
        Utilize o botão <span class="font-semibold text-slate-200">Novo relatório</span> para iniciar o assistente
        e escolher o tipo (despesas ou receitas), a conta bancária e o período desejado.
      </div>

      <template v-else>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Tipo do relatório</p>
            <p class="text-lg font-semibold text-white">{{ filtersTypeLabel }}</p>
          </article>
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Conta selecionada</p>
            <p class="text-lg font-semibold text-white">
              {{ filtersAccountLabel }}
            </p>
          </article>
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Período</p>
            <p class="text-lg font-semibold text-slate-200">
              {{ filtersPeriodLabel }}
            </p>
          </article>
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Status dos lançamentos</p>
            <p class="text-lg font-semibold text-slate-200">
              {{ filtersStatusLabel }}
            </p>
          </article>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Saldo inicial</p>
            <p class="text-xl font-semibold text-slate-200">
              {{ formatCurrency(openingBalance) }}
            </p>
          </article>
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Entradas</p>
            <p class="text-xl font-semibold text-emerald-300">
              {{ formatCurrency(totals.inflow) }}
            </p>
          </article>
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Saídas</p>
            <p class="text-xl font-semibold text-rose-300">
              {{ formatCurrency(totals.outflow) }}
            </p>
          </article>
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
            <p class="text-slate-400">Saldo final</p>
            <p
              :class="[
                'text-2xl font-semibold',
                closingBalance >= 0 ? 'text-emerald-300' : 'text-rose-300',
              ]"
            >
              {{ formatCurrency(closingBalance) }}
            </p>
          </article>
          <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm md:col-span-2">
            <p class="text-slate-400">Resultado no período</p>
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

        <div class="flex flex-wrap items-center justify-between gap-4">
          <div class="flex items-center gap-2 text-sm text-slate-300">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400" for="status-filter">
              Status
            </label>
            <select
              id="status-filter"
              class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
              :disabled="loading"
              :value="filters.status"
              @change="changeStatusFilter(($event.target as HTMLSelectElement).value)"
            >
              <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
          <div class="flex flex-wrap gap-2">
            <button
              v-if="props.canExport"
              type="button"
              class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 transition hover:bg-slate-800 disabled:opacity-60"
              :disabled="loading || !hasActiveFilters"
              @click="exportReport('csv')"
            >
              Exportar CSV
            </button>
            <button
              v-if="props.canExport"
              type="button"
              class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 transition hover:bg-slate-800 disabled:opacity-60"
              :disabled="loading || !hasActiveFilters"
              @click="exportReport('xlsx')"
            >
              Exportar XLSX
            </button>
            <button
              v-if="props.canExport"
              type="button"
              class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 transition hover:bg-slate-800 disabled:opacity-60"
              :disabled="loading || !hasActiveFilters"
              @click="exportReport('pdf')"
            >
              Exportar PDF
            </button>
          </div>
        </div>

        <p
          v-if="errorMessage"
          class="rounded-lg border border-rose-500/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-200"
        >
          {{ errorMessage }}
        </p>

        <div class="overflow-hidden rounded-2xl border border-slate-800">
          <table class="min-w-full table-fixed divide-y divide-slate-800 text-sm text-slate-100">
            <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th class="px-4 py-3 text-left" :style="{ width: columnWidths.date }">Data</th>
                <th class="px-4 py-3 text-left" :style="{ width: columnWidths.supplier }">Fornecedor</th>
                <th class="px-4 py-3 text-left" :style="{ width: columnWidths.description }">Descrição</th>
                <th class="px-4 py-3 text-left" :style="{ width: columnWidths.property }">Imóvel</th>
                <th class="px-4 py-3 text-left" :style="{ width: columnWidths.due }">Venc.</th>
                <th class="px-4 py-3 text-left" :style="{ width: columnWidths.status }">Status</th>
                <th class="px-4 py-3 text-right" :style="{ width: columnWidths.value }">Valor</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
              <tr v-if="!hasActiveFilters">
                <td colspan="7" class="px-6 py-8 text-center text-slate-400">
                  Inicie um novo relatório para visualizar os lançamentos.
                </td>
              </tr>
              <tr v-else-if="loading">
                <td colspan="7" class="px-6 py-8 text-center text-slate-400">Carregando dados...</td>
              </tr>
              <tr v-else-if="!hasRows">
                <td colspan="7" class="px-6 py-8 text-center text-slate-400">
                  Nenhum lançamento encontrado para os filtros selecionados.
                </td>
              </tr>
              <tr v-for="row in ledgerRows" :key="row.id">
                <td class="px-4 py-3 text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                  {{ row.movement_date ?? '-' }}
                </td>
                <td class="px-4 py-3 text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                  {{ row.person?.nome ?? '—' }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap overflow-hidden text-ellipsis">
                  <span class="font-semibold text-white">{{ row.description ?? '-' }}</span>
                </td>
                <td class="px-4 py-3 text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                  <span v-if="row.property?.nome">{{ row.property.nome }}</span>
                  <span v-else>—</span>
                </td>
                <td class="px-4 py-3 text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                  {{ row.due_date ? new Date(row.due_date).toLocaleDateString('pt-BR') : '—' }}
                </td>
                <td class="px-4 py-3 text-xs text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                  {{ row.status_label ?? '—' }}
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap overflow-hidden text-ellipsis">
                  {{ formatCurrency(row.signed_amount ?? 0) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p
          v-if="totalRowCount > ledgerRows.length"
          class="mt-3 text-xs text-slate-400"
        >
          Exibindo os primeiros {{ ledgerRows.length }} de {{ totalRowCount }} lançamentos. Utilize as opções de exportação para consultar o relatório completo.
        </p>
      </template>
    </section>

    <transition name="fade">
      <div
        v-if="showWizard"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-3 py-5 backdrop-blur"
        @keydown.esc.prevent.stop="closeWizard"
      >
        <div class="relative w-full max-w-5xl px-[1.5px] pb-[1.5px]">
          <div
            class="pointer-events-none absolute inset-0 -z-10 rounded-[28px] bg-gradient-to-br from-indigo-500/30 via-purple-500/20 to-emerald-400/25 blur-xl"
          ></div>
          <div
            class="relative overflow-hidden rounded-[26px] border border-white/10 bg-slate-950/85 shadow-[0_25px_60px_-25px_rgba(15,23,42,0.9)] backdrop-blur-2xl"
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
                  Relatório de Despesas e Receitas
                </h2>
                <p class="max-w-xl text-sm text-slate-300/80">
                  Siga o passo a passo para definir o tipo de relatório, conta bancária e período desejado antes de gerar os arquivos.
                </p>
              </div>
              <button
                type="button"
                class="group relative inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-300 transition hover:border-white/30 hover:bg-white/10 hover:text-white"
                @click="closeWizard"
              >
                <span class="sr-only">Fechar</span>
                <svg
                  class="h-4.5 w-4.5 transition-transform group-hover:rotate-180"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </header>

            <div class="max-h-[82vh] overflow-y-auto px-6 py-6">
              <nav
                class="mb-6 flex items-center gap-2 overflow-x-auto rounded-full border border-white/5 bg-white/5 p-1 text-xs font-semibold uppercase tracking-wide text-slate-400"
              >
                <button
                  v-for="step in wizardSteps"
                  :key="step.id"
                  type="button"
                  class="inline-flex min-w-[140px] items-center justify-center gap-2 rounded-full px-4 py-1.5 transition"
                  :class="[
                    wizardStep === step.id
                      ? 'bg-gradient-to-r from-indigo-500/90 via-purple-500/90 to-sky-500/90 text-white shadow-[0_8px_20px_-10px_rgba(99,102,241,0.8)]'
                      : wizardStep > step.id
                        ? 'text-slate-100 hover:bg-white/10'
                        : 'text-slate-400 hover:bg-white/10',
                  ]"
                  :disabled="wizardStep < step.id"
                  @click="wizardStep > step.id ? toStep(step.id) : undefined"
                >
                  <span
                    class="flex h-5 w-5 items-center justify-center rounded-full border border-white/20 text-[11px]"
                  >
                    {{ step.id }}
                  </span>
                  {{ step.label }}
                </button>
              </nav>

              <div v-if="wizardStep === 1" class="space-y-6">
                <section class="space-y-3">
                  <header class="space-y-1">
                    <h3 class="text-lg font-semibold text-white">Selecione o tipo de relatório</h3>
                    <p class="text-sm text-slate-300/80">
                      Escolha entre despesas ou receitas. Esta definição determina os lançamentos que serão considerados.
                    </p>
                  </header>
                  <div class="grid gap-4 md:grid-cols-2">
                    <button
                      v-for="type in availableTypes"
                      :key="type.id"
                      type="button"
                      class="rounded-2xl border px-5 py-4 text-left transition"
                      :class="[
                        selection.type === type.id
                          ? 'border-indigo-400/70 bg-indigo-500/15 text-white shadow-[0_15px_35px_-20px_rgba(99,102,241,0.9)]'
                          : 'border-white/10 bg-white/5 text-slate-200 hover:border-white/20 hover:bg-white/10',
                      ]"
                      @click="selection.type = type.id"
                    >
                      <h4 class="text-base font-semibold">{{ type.label }}</h4>
                      <p class="mt-1 text-xs text-slate-300/80">
                        {{ type.description }}
                      </p>
                    </button>
                  </div>
                  <p v-if="stepErrors.step1" class="text-sm text-rose-300">{{ stepErrors.step1 }}</p>
                </section>

                <section class="space-y-3">
                  <header class="space-y-1">
                    <h3 class="text-lg font-semibold text-white">Conta bancária</h3>
                    <p class="text-sm text-slate-300/80">
                      Selecione uma conta específica ou mantenha "Todos os bancos" para considerar todos os lançamentos disponíveis.
                    </p>
                  </header>
                  <select
                    class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-sm text-white focus:border-indigo-500 focus:outline-none"
                    v-model="selection.financial_account_id"
                    @change="handleAccountSelect(($event.target as HTMLSelectElement).value)"
                  >
                    <option value="">Todos os bancos</option>
                    <option v-for="account in props.accounts" :key="account.id" :value="String(account.id)">
                      {{ account.nome }}
                    </option>
                  </select>
                  <div class="rounded-xl border border-white/5 bg-white/5 p-4 text-xs text-slate-200">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                      <span class="font-semibold uppercase tracking-wide text-[11px] text-slate-300">
                        Seleção manual de contas
                      </span>
                      <button
                        type="button"
                        class="rounded-full border border-white/20 px-3 py-1 text-[11px] font-medium uppercase tracking-wide text-slate-100 transition hover:border-white/40 hover:bg-white/10"
                        @click="selectAllAccounts"
                      >
                        Selecionar todos
                      </button>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                      <label
                        v-for="account in allAccountOptions"
                        :key="account.id"
                        class="flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-[12px] transition hover:border-white/20 hover:bg-white/10"
                      >
                        <input
                          type="checkbox"
                          class="size-4 rounded border-white/20 bg-slate-900 text-indigo-400 focus:ring-indigo-400"
                          :checked="isAccountChecked(account.id)"
                          @change="handleAccountCheckboxChange(account.id, ($event.target as HTMLInputElement).checked)"
                        />
                        <span class="truncate text-sm">{{ account.nome }}</span>
                      </label>
                    </div>
                    <p class="mt-3 text-[11px] text-slate-300">
                      Selecionadas: <span class="font-medium text-slate-100">{{ selectionAccountLabel }}</span>
                    </p>
                  </div>
                </section>

                <footer class="flex items-center justify-between pt-2">
                  <div class="text-xs text-slate-400">
                    <span class="font-semibold text-slate-200">Resumo rápido:</span>
                    <span class="ml-1">
                      {{ selectionTypeLabel }} · {{ selectionAccountLabel }}
                    </span>
                  </div>
                  <div class="flex gap-3">
                    <button
                      type="button"
                      class="rounded-lg border border-white/10 px-4 py-2 text-sm text-slate-200 transition hover:bg-white/10"
                      @click="closeWizard"
                    >
                      Cancelar
                    </button>
                    <button
                      type="button"
                      class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-400"
                      @click="proceedToDates"
                    >
                      Próximo
                    </button>
                  </div>
                </footer>
              </div>

              <div v-else-if="wizardStep === 2" class="space-y-6">
                <section class="space-y-3">
                  <header class="space-y-1">
                    <h3 class="text-lg font-semibold text-white">Período do relatório</h3>
                    <p class="text-sm text-slate-300/80">
                      Informe um intervalo específico ou utilize os atalhos rápidos abaixo.
                    </p>
                  </header>
                  <div class="rounded-xl border border-white/5 bg-white/5 px-4 py-3 text-xs text-slate-200">
                    <span class="font-semibold uppercase tracking-wide text-[11px] text-slate-300">
                      Base de datas
                    </span>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                      <label class="flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm transition hover:border-white/20 hover:bg-white/10">
                        <input
                          type="radio"
                          class="size-4 border-white/30 bg-slate-900 text-indigo-400 focus:ring-indigo-400"
                          value="movement"
                          v-model="selection.date_basis"
                        />
                        <span>Data do movimento</span>
                      </label>
                      <label class="flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm transition hover:border-white/20 hover:bg-white/10">
                        <input
                          type="radio"
                          class="size-4 border-white/30 bg-slate-900 text-indigo-400 focus:ring-indigo-400"
                          value="due"
                          v-model="selection.date_basis"
                        />
                        <span>Data de vencimento</span>
                      </label>
                    </div>
                  </div>
                  <div class="grid gap-4 md:grid-cols-2">
                    <div class="flex flex-col gap-2">
                      <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                        Data inicial
                      </label>
                      <DatePicker v-model="selection.date_from" placeholder="dd/mm/aaaa" />
                    </div>
                    <div class="flex flex-col gap-2">
                      <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                        Data final
                      </label>
                      <DatePicker v-model="selection.date_to" placeholder="dd/mm/aaaa" />
                    </div>
                  </div>
                  <div class="flex flex-wrap gap-2">
                    <button
                      v-for="preset in datePresets"
                      :key="preset.id"
                      type="button"
                      class="rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-medium text-slate-200 transition hover:bg-white/10"
                      @click="applyDatePreset(preset.id)"
                    >
                      {{ preset.label }}
                    </button>
                    <button
                      type="button"
                      class="rounded-full border border-white/10 bg-transparent px-3 py-1 text-xs font-medium text-slate-200 transition hover:bg-white/10"
                      @click="clearSelectionDates"
                    >
                      Limpar datas
                    </button>
                  </div>
                </section>

                <footer class="flex items-center justify-between pt-2">
                  <div class="text-xs text-slate-400">
                    <span class="font-semibold text-slate-200">Resumo atual:</span>
                    <span class="ml-1">
                      {{ selectionPeriodLabel }}
                    </span>
                  </div>
                  <div class="flex gap-3">
                    <button
                      type="button"
                      class="rounded-lg border border-white/10 px-4 py-2 text-sm text-slate-200 transition hover:bg-white/10"
                      @click="toStep(1)"
                    >
                      Voltar
                    </button>
                    <button
                      type="button"
                      class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-400 disabled:opacity-60"
                      :disabled="loading"
                      @click="confirmDates"
                    >
                      OK
                    </button>
                  </div>
                </footer>
              </div>

              <div v-else class="space-y-6">
                <header class="space-y-1">
                  <h3 class="text-lg font-semibold text-white">
                    Pré-visualização
                  </h3>
                  <p class="text-sm text-slate-300/80">
                    Revise os dados abaixo antes de exportar o relatório em PDF, XLSX ou CSV.
                  </p>
                </header>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                  <article class="rounded-xl border border-white/10 bg-white/5 p-4 text-sm text-slate-200">
                    <p class="text-slate-400">Tipo</p>
                    <p class="text-base font-semibold text-white">
                      {{ selectionTypeLabel }}
                    </p>
                  </article>
                  <article class="rounded-xl border border-white/10 bg-white/5 p-4 text-sm text-slate-200">
                    <p class="text-slate-400">Conta</p>
                    <p class="text-base font-semibold text-white">
                      {{ selectionAccountLabel }}
                    </p>
                  </article>
                  <article class="rounded-xl border border-white/10 bg-white/5 p-4 text-sm text-slate-200">
                    <p class="text-slate-400">Período</p>
                    <p class="text-base font-semibold text-white">
                      {{ selectionPeriodLabel }}
                    </p>
                  </article>
                  <article class="rounded-xl border border-white/10 bg-white/5 p-4 text-sm text-slate-200">
                    <p class="text-slate-400">Status</p>
                    <p class="text-base font-semibold text-white">
                      {{ filtersStatusLabel }}
                    </p>
                  </article>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                  <div class="text-xs text-slate-400">
                    Pré-visualizando {{ previewRows.length }} de {{ totalRowCount }} lançamentos.
                    <span v-if="previewHasMoreRows" class="text-slate-100">
                      Abra o relatório completo para visualizar todos.
                    </span>
                  </div>
                  <div class="flex flex-wrap gap-2">
                    <button
                      v-if="props.canExport"
                      type="button"
                      class="rounded-lg border border-white/10 px-4 py-2 text-sm text-slate-200 transition hover:bg-white/10 disabled:opacity-60"
                      :disabled="loading"
                      @click="exportReport('csv')"
                    >
                      Gerar CSV
                    </button>
                    <button
                      v-if="props.canExport"
                      type="button"
                      class="rounded-lg border border-white/10 px-4 py-2 text-sm text-slate-200 transition hover:bg-white/10 disabled:opacity-60"
                      :disabled="loading"
                      @click="exportReport('xlsx')"
                    >
                      Gerar XLSX
                    </button>
                    <button
                      v-if="props.canExport"
                      type="button"
                      class="rounded-lg border border-white/10 px-4 py-2 text-sm text-slate-200 transition hover:bg-white/10 disabled:opacity-60"
                      :disabled="loading"
                      @click="exportReport('pdf')"
                    >
                      Gerar PDF
                    </button>
                  </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-white/10">
                  <table class="min-w-full table-fixed divide-y divide-white/10 text-sm text-white/90">
                    <thead class="bg-white/5 text-xs uppercase tracking-wide text-slate-300">
                      <tr>
                        <th class="px-4 py-3 text-left">Data</th>
                        <th class="px-4 py-3 text-left">Fornecedor</th>
                        <th class="px-4 py-3 text-left">Descrição</th>
                        <th class="px-4 py-3 text-left">Imóvel</th>
                        <th class="px-4 py-3 text-left">Venc.</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-right">Valor</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                      <tr v-if="loading">
                        <td colspan="7" class="px-6 py-8 text-center text-slate-300">
                          Carregando dados do relatório...
                        </td>
                      </tr>
                      <tr v-else-if="previewRows.length === 0">
                        <td colspan="7" class="px-6 py-8 text-center text-slate-300">
                          Nenhum lançamento encontrado para os filtros selecionados.
                        </td>
                      </tr>
                      <tr v-for="row in previewRows" :key="row.id">
                        <td class="px-4 py-3 text-slate-200 whitespace-nowrap overflow-hidden text-ellipsis">
                          {{ row.movement_date ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-slate-200 whitespace-nowrap overflow-hidden text-ellipsis">
                          {{ row.person?.nome ?? '—' }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap overflow-hidden text-ellipsis">
                          <span class="font-semibold text-white">{{ row.description ?? '-' }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-200 whitespace-nowrap overflow-hidden text-ellipsis">
                          <span v-if="row.property?.nome">{{ row.property.nome }}</span>
                          <span v-else>—</span>
                        </td>
                        <td class="px-4 py-3 text-slate-200 whitespace-nowrap overflow-hidden text-ellipsis">
                          {{ row.due_date ? new Date(row.due_date).toLocaleDateString('pt-BR') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-200 whitespace-nowrap overflow-hidden text-ellipsis">
                          {{ row.status_label ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-slate-100 whitespace-nowrap overflow-hidden text-ellipsis">
                          {{ formatCurrency(row.signed_amount ?? 0) }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <footer class="flex items-center justify-between pt-2">
                  <button
                    type="button"
                    class="rounded-lg border border-white/10 px-4 py-2 text-sm text-slate-200 transition hover:bg-white/10"
                    @click="toStep(2)"
                  >
                    Ajustar período
                  </button>
                  <button
                    type="button"
                    class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-400"
                    @click="closeWizard"
                  >
                    Concluir
                  </button>
                </footer>
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </AuthenticatedLayout>
</template>
