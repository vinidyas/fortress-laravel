<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import {
  computed,
  reactive,
  ref,
  watch,
  onBeforeUnmount,
  nextTick,
  unref,
  type MaybeRef,
} from 'vue';
import MoneyInput from '@/Components/Form/MoneyInput.vue';
import DatePicker from '@/Components/Form/DatePicker.vue';
import SearchableSelect from '@/Components/Form/SearchableSelect.vue';
import TransactionStatusBadge from '@/Components/Financeiro/TransactionStatusBadge.vue';
import PessoaFormModal from '@/Components/Pessoas/PessoaFormModal.vue';
import { useToast } from '@/composables/useToast';
import {
  resolveStatusCategory,
  resolveStatusGroupForStatus,
  resolveStatusGroupLabel,
  resolveStatusLabel,
  type TransactionStatusCategory,
  type TransactionStatusCode,
  type TransactionStatusGroup,
  type TransactionType,
} from '@/utils/financeiro/status';

interface Option {
  id: number;
  nome: string;
  codigo?: string | null;
  parent_id?: number | null;
}

interface PersonOption extends Option {
  papeis?: string[] | null;
}

interface PropertyOption {
  id: number;
  titulo?: string | null;
  codigo_interno?: string | null;
}

type PessoaRole = 'Proprietario' | 'Locatario' | 'Fiador' | 'Fornecedor' | 'Cliente';

interface AttachmentSummary {
  id: number;
  journal_entry_id: number;
  installment_id?: number | null;
  file_name: string;
  file_size: number;
  mime_type: string;
  download_url: string;
  uploaded_at?: string | null;
  uploaded_by?: { id: number; name: string } | null;
}

interface ReceiptSummary {
  id: number;
  number: string;
  issue_date?: string | null;
  status: 'processing' | 'generated' | string;
  download_url?: string | null;
  installment_id?: number | null;
  metadata?: Record<string, any> | null;
}

type Status = TransactionStatusCode;
type Tipo = TransactionType;
type StatusGroup = TransactionStatusGroup;
type ImprovementType = 'reforma' | 'investimento' | null;

interface InstallmentPayload {
  id?: number;
  numero_parcela?: number;
  movement_date?: string | null;
  due_date?: string | null;
  payment_date?: string | null;
  valor_principal?: number | string;
  valor_juros?: number | string;
  valor_multa?: number | string;
  valor_desconto?: number | string;
  valor_total?: number | string;
  status?: Status;
  meta?: Record<string, unknown> | null;
}

interface TransactionPayload {
  id?: number;
  account?: Option | null;
  counter_account?: Option | null;
  cost_center?: Option | null;
  property?: { id: number; nome?: string | null } | null;
  person?: { id: number; nome: string; papeis?: string[] | null } | null;
  movement_date?: string | null;
  due_date?: string | null;
  payment_date?: string | null;
  descricao?: string | null;
  description?: string | null;
  description_id?: number | null;
  notes?: string | null;
  reference_code?: string | null;
  improvement_type?: 'reforma' | 'investimento' | null;
  tipo: Tipo;
  valor: string | number;
  status: Status;
  installments?: InstallmentPayload[];
  allocations?: Array<{
    cost_center?: Option | null;
    property?: { id: number; nome?: string | null } | null;
    cost_center_id?: number | null;
    property_id?: number | null;
    percentage?: number | string | null;
    amount?: number | string | null;
  }>;
  attachments?: AttachmentSummary[];
  receipts?: ReceiptSummary[];
}

export type { TransactionPayload, Option as TransactionOption, PersonOption as TransactionPersonOption };

interface DescriptionSuggestion {
  id: number;
  texto: string;
  uso_total?: number;
  ultima_utilizacao?: string | null;
}

type FormContext = 'page' | 'modal';
type FormAppearance = 'light' | 'dark';
type ModalTab = 'basic' | 'installments' | 'attachments' | 'receipts';

const props = withDefaults(
  defineProps<{
    mode: 'create' | 'edit';
    transaction: TransactionPayload | null;
    accounts: Option[];
    costCenters: Option[];
    people: PersonOption[];
    properties: PropertyOption[];
    permissions: { update: boolean; delete: boolean; reconcile: boolean };
    context?: FormContext;
    appearance?: FormAppearance;
    redirectOnSave?: boolean;
    activeTab?: MaybeRef<ModalTab>;
  }>(),
  {
    context: 'page' as FormContext,
    appearance: 'light' as FormAppearance,
    redirectOnSave: true,
  }
);

const emit = defineEmits<{
  (e: 'saved'): void;
  (e: 'deleted'): void;
  (e: 'cancel'): void;
}>();

const isCreate = computed(() => props.mode === 'create');
const isModalContext = computed(() => props.context === 'modal');
const isDarkAppearance = computed(() => props.appearance === 'dark');
const entryId = computed(() => props.transaction?.id ?? null);
const activeTab = computed<ModalTab>(() => (unref(props.activeTab) as ModalTab | undefined) ?? 'basic');

const ACCOUNT_USAGE_STORAGE_KEY = 'financeiro:lastAccountUsage';
const DESCRIPTION_HISTORY_STORAGE_KEY = 'financeiro:descriptionHistory';
const DESCRIPTION_HISTORY_LIMIT = 50;

type AccountUsageEntry = { count: number; lastUsedAt: number };

const loadAccountUsage = (): Record<number, AccountUsageEntry> => {
  if (typeof window === 'undefined') {
    return {};
  }

  try {
    const raw = window.localStorage.getItem(ACCOUNT_USAGE_STORAGE_KEY);
    if (!raw) {
      return {};
    }

    const parsed = JSON.parse(raw) as Record<string, AccountUsageEntry>;
    return Object.entries(parsed).reduce<Record<number, AccountUsageEntry>>((acc, [key, value]) => {
      const numericKey = Number.parseInt(key, 10);
      if (!Number.isNaN(numericKey) && value && typeof value.count === 'number') {
        acc[numericKey] = {
          count: Number.isFinite(value.count) ? value.count : 0,
          lastUsedAt: Number.isFinite(value.lastUsedAt) ? value.lastUsedAt : 0,
        };
      }
      return acc;
    }, {});
  } catch (error) {
    console.error('Failed to parse account usage data', error);
    return {};
  }
};

const persistAccountUsage = (map: Record<number, AccountUsageEntry>) => {
  if (typeof window === 'undefined') {
    return;
  }

  const serialized = Object.entries(map).reduce<Record<string, AccountUsageEntry>>((acc, [id, entry]) => {
    acc[id] = entry;
    return acc;
  }, {});

  window.localStorage.setItem(ACCOUNT_USAGE_STORAGE_KEY, JSON.stringify(serialized));
};

const registerAccountUsage = (accountId: number | null) => {
  if (!accountId) {
    return;
  }

  const usage = loadAccountUsage();
  const current = usage[accountId] ?? { count: 0, lastUsedAt: 0 };

  usage[accountId] = {
    count: current.count + 1,
    lastUsedAt: Date.now(),
  };

  persistAccountUsage(usage);
};

const resolveMostUsedAccountId = (): number | null => {
  const usage = loadAccountUsage();
  let bestId: number | null = null;
  let bestCount = -1;
  let bestTimestamp = -1;

  for (const account of props.accounts ?? []) {
    const entry = usage[account.id];
    if (!entry) {
      continue;
    }

    if (entry.count > bestCount || (entry.count === bestCount && entry.lastUsedAt > bestTimestamp)) {
      bestId = account.id;
      bestCount = entry.count;
      bestTimestamp = entry.lastUsedAt;
    }
  }

  if (bestId !== null) {
    return bestId;
  }

  return props.accounts?.[0]?.id ?? null;
};

const loadDescriptionHistory = (): string[] => {
  if (typeof window === 'undefined') {
    return [];
  }

  try {
    const raw = window.localStorage.getItem(DESCRIPTION_HISTORY_STORAGE_KEY);
    if (!raw) {
      return [];
    }

    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) {
      return [];
    }

    return parsed
      .filter((item) => typeof item === 'string')
      .map((item) => item.trim())
      .filter((item) => item !== '');
  } catch (error) {
    console.error('Failed to parse description history', error);
    return [];
  }
};

const persistDescriptionHistory = (history: string[]) => {
  if (typeof window === 'undefined') {
    return;
  }

  window.localStorage.setItem(DESCRIPTION_HISTORY_STORAGE_KEY, JSON.stringify(history));
};

const historyDescriptions = ref<string[]>(loadDescriptionHistory());
const registerDescriptionHistory = (value: string | null | undefined) => {
  const normalized = (value ?? '').trim();
  if (!normalized) {
    return;
  }

  const existing = historyDescriptions.value.filter(
    (entry) => entry.toLowerCase() !== normalized.toLowerCase(),
  );

  historyDescriptions.value = [normalized, ...existing].slice(0, DESCRIPTION_HISTORY_LIMIT);
  persistDescriptionHistory(historyDescriptions.value);
  applyDescriptionSuggestions();
};

const appearanceClasses = computed(() => ({
  'transaction-form': true,
  'appearance-dark': isDarkAppearance.value,
  'appearance-light': !isDarkAppearance.value,
  'context-modal': isModalContext.value,
  'context-page': !isModalContext.value,
}));
const panelClasses = computed(() =>
  isDarkAppearance.value
    ? 'rounded-2xl border border-white/5 bg-white/5 p-6 backdrop-blur supports-[backdrop-filter]:bg-white/10 shadow-[0_25px_60px_-35px_rgba(15,23,42,1)]'
    : 'rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/40'
);
const showBasicSection = computed(() => !isModalContext.value || activeTab.value === 'basic');
const showInstallmentsSection = computed(() => !isModalContext.value || activeTab.value === 'installments');
const showAllocationsSection = computed(() => !isModalContext.value || activeTab.value === 'installments');
const showAttachmentsSection = computed(() => {
  if (!entryId.value) {
    return false;
  }

  return !isModalContext.value || activeTab.value === 'attachments';
});
const showReceiptsSection = computed(() => {
  if (!entryId.value) {
    return false;
  }

  return !isModalContext.value || activeTab.value === 'receipts';
});
const canSubmit = computed(() => isCreate.value || props.permissions.update);
const toast = useToast();
const submitting = ref(false);
const deleting = ref(false);
const descriptionSuggestions = ref<DescriptionSuggestion[]>([]);
const showDescriptionSuggestions = ref(false);
const descriptionLoading = ref(false);
const selectedDescriptionId = ref<number | null>(
  props.transaction?.description_id ?? null
);
const selectedDescriptionLabel = ref<string>(
  props.transaction?.description ?? props.transaction?.descricao ?? ''
);
let descriptionSearchTimeout: ReturnType<typeof setTimeout> | null = null;
const descriptionSearchTerm = ref('');
let lastRemoteSuggestions: DescriptionSuggestion[] = [];
const showInstallmentsManager = ref(false);
const attachments = ref<AttachmentSummary[]>(props.transaction?.attachments ?? []);
const attachmentsLoading = ref(false);
const receipts = ref<ReceiptSummary[]>(props.transaction?.receipts ?? []);
const receiptsLoading = ref(false);
const attachmentUploadState = reactive({
  file: null as File | null,
  installmentId: null as number | null,
  loading: false,
});
const generatingReceipt = ref(false);
const attachmentInputRef = ref<HTMLInputElement | null>(null);
const selectedReceiptInstallmentId = ref<number | null>(null);
const attachmentsLoaded = ref(false);
const receiptsLoaded = ref(false);
const descriptionFieldRef = ref<HTMLTextAreaElement | null>(null);
const notesFieldRef = ref<HTMLTextAreaElement | null>(null);
const typeOptions: Array<{
  value: Tipo;
  label: string;
  accent: string;
  accentLight: string;
}> = [
  {
    value: 'despesa',
    label: 'Despesa',
    accent: 'from-rose-500/90 via-orange-500/80 to-amber-400/80',
    accentLight: 'from-rose-200 via-orange-100 to-amber-100',
  },
  {
    value: 'receita',
    label: 'Receita',
    accent: 'from-emerald-500/90 to-lime-400/80',
    accentLight: 'from-emerald-200 via-lime-100 to-green-100',
  },
  {
    value: 'transferencia',
    label: 'Transferência',
    accent: 'from-sky-500/90 to-indigo-500/80',
    accentLight: 'from-sky-200 via-indigo-100 to-blue-100',
  },
];
const typeCardShadowDark = 'shadow-[0_18px_38px_-22px_rgba(99,102,241,0.85)]';
const typeCardShadowLight = 'shadow-[0_18px_35px_-18px_rgba(99,102,241,0.35)]';
const getTypeOptionClasses = (option: (typeof typeOptions)[number]) => {
  const isActive = form.type === option.value;

  if (isDarkAppearance.value) {
    return isActive
      ? `border-transparent bg-gradient-to-br ${option.accent} text-white ${typeCardShadowDark}`
      : 'border-white/10 bg-white/5 text-slate-200 hover:border-white/20 hover:bg-white/10';
  }

  return isActive
    ? `border-transparent bg-gradient-to-br ${option.accentLight} text-slate-900 ${typeCardShadowLight}`
    : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-200/60 hover:bg-indigo-50/70';
};

const getTypeOptionBadgeClasses = (option: (typeof typeOptions)[number]) => {
  const isActive = form.type === option.value;

  if (isDarkAppearance.value) {
    return isActive
      ? 'border-white/40 bg-white/20 text-white'
      : 'border-white/25 bg-white/10 text-slate-200';
  }

  return isActive
    ? 'border-indigo-200 bg-indigo-100 text-indigo-700'
    : 'border-slate-200 bg-slate-50 text-slate-500';
};

const getTypeOptionLabelClasses = (option: (typeof typeOptions)[number]) => {
  const isActive = form.type === option.value;

  if (isDarkAppearance.value) {
    return isActive ? 'text-white' : 'text-slate-100';
  }

  return isActive ? 'text-slate-900' : 'text-slate-600';
};

const improvementTypeOptions: Array<{
  value: Exclude<ImprovementType, null>;
  label: string;
}> = [
  {
    value: 'reforma',
    label: 'Reforma',
  },
  {
    value: 'investimento',
    label: 'Investimento',
  },
];

const isImprovementActive = (value: Exclude<ImprovementType, null>) =>
  form.improvement_type === value;

const improvementOptionClasses = (value: Exclude<ImprovementType, null>) => {
  const active = isImprovementActive(value);

  if (isDarkAppearance.value) {
    return active
      ? 'border-indigo-400/60 bg-indigo-500/20 text-white shadow-[0_18px_32px_-20px_rgba(79,70,229,0.85)]'
      : 'border-white/12 bg-white/5 text-slate-200 hover:border-indigo-400/40 hover:bg-indigo-500/10';
  }

  return active
    ? 'border-indigo-200 bg-indigo-100 text-indigo-700 shadow-sm'
    : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-200 hover:bg-indigo-50';
};

const improvementOptionIconClasses = (value: Exclude<ImprovementType, null>) => {
  const active = isImprovementActive(value);

  if (isDarkAppearance.value) {
    return active
      ? 'bg-indigo-400 text-slate-950'
      : 'bg-slate-800 text-slate-200 group-hover:bg-indigo-400 group-hover:text-slate-900';
  }

  return active
    ? 'bg-indigo-500 text-white'
    : 'bg-slate-200 text-slate-500 group-hover:bg-indigo-200 group-hover:text-indigo-800';
};

const toggleImprovementType = (value: Exclude<ImprovementType, null>) => {
  if (!canSubmit.value) {
    return;
  }

  form.improvement_type = form.improvement_type === value ? null : value;
};

const resolveDefaultBankAccountId = (): number | null => {
  if (props.transaction?.account?.id) {
    return props.transaction.account.id;
  }

  return resolveMostUsedAccountId();
};

const form = reactive({
  bank_account_id: resolveDefaultBankAccountId(),
  counter_bank_account_id: props.transaction?.counter_account?.id ?? null,
  cost_center_id: props.transaction?.cost_center?.id ?? null,
  property_id: props.transaction?.property?.id ?? null,
  person_id: props.transaction?.person?.id ?? null,
  type: (props.transaction?.tipo ?? 'despesa') as Tipo,
  improvement_type: (props.transaction?.improvement_type ?? null) as 'reforma' | 'investimento' | null,
  amount: props.transaction?.valor ? String(props.transaction.valor) : '',
  movement_date:
    props.transaction?.movement_date ??
    props.transaction?.due_date ??
    new Date().toISOString().slice(0, 10),
  due_date: props.transaction?.due_date ?? null,
  payment_date:
    props.transaction?.payment_date ??
    props.transaction?.due_date ??
    props.transaction?.movement_date ??
    null,
  description: selectedDescriptionLabel.value ?? '',
  notes: props.transaction?.notes ?? '',
  reference_code: props.transaction?.reference_code ?? '',
  currency: 'BRL',
  status: (props.transaction?.status ?? 'planejado') as Status,
});

interface InstallmentForm {
  id: number | null;
  numero_parcela: number;
  movement_date: string;
  due_date: string;
  payment_date: string | null;
  valor_principal: string;
  valor_juros: string;
  valor_multa: string;
  valor_desconto: string;
  valor_total: string;
  status: Status;
  meta: Record<string, unknown> | null;
}

interface AllocationForm {
  cost_center_id: number | null;
  property_id: number | null;
  percentage: string;
  amount: string;
}

const initialInstallments = (): InstallmentForm[] => {
  if (props.transaction?.installments?.length) {
    return props.transaction.installments.map((installment, index) => ({
      id:
        installment.id !== undefined && installment.id !== null
          ? Number(installment.id)
          : null,
      numero_parcela: installment.numero_parcela ?? index + 1,
      movement_date: installment.movement_date ?? props.transaction?.movement_date ?? new Date().toISOString().slice(0, 10),
      due_date: installment.due_date ?? installment.movement_date ?? props.transaction?.movement_date ?? new Date().toISOString().slice(0, 10),
      payment_date:
        installment.payment_date ??
        (['pago'].includes(installment.status ?? '') 
          ? (installment.due_date ??
            props.transaction?.payment_date ??
            props.transaction?.due_date ??
            props.transaction?.movement_date ??
            new Date().toISOString().slice(0, 10))
          : null),
      valor_principal: String(installment.valor_principal ?? installment.valor_total ?? 0),
      valor_juros: String(installment.valor_juros ?? 0),
      valor_multa: String(installment.valor_multa ?? 0),
      valor_desconto: String(installment.valor_desconto ?? 0),
      valor_total: String(installment.valor_total ?? 0),
      status: installment.status ?? 'planejado',
      meta: (installment.meta as Record<string, unknown> | null) ?? null,
    }));
  }

  const baseDate = props.transaction?.movement_date ?? new Date().toISOString().slice(0, 10);
  return [
    {
      id: null,
      numero_parcela: 1,
      movement_date: baseDate,
      due_date: props.transaction?.due_date ?? baseDate,
      payment_date: null,
      valor_principal: props.transaction?.valor ? String(props.transaction.valor) : '',
      valor_juros: '0',
      valor_multa: '0',
      valor_desconto: '0',
      valor_total: props.transaction?.valor ? String(props.transaction.valor) : '',
      status: 'planejado',
      meta: null,
    },
  ];
};

const installments = ref<InstallmentForm[]>(initialInstallments());

const initialAllocations = (): AllocationForm[] => {
  if (props.transaction?.allocations?.length) {
    return props.transaction.allocations.map((allocation: any) => ({
      cost_center_id: allocation.cost_center?.id ?? allocation.cost_center_id ?? null,
      property_id: allocation.property?.id ?? allocation.property_id ?? null,
      percentage: allocation.percentage ? String(allocation.percentage) : '',
      amount: allocation.amount ? String(allocation.amount) : '',
    }));
  }

  return [];
};

const allocations = ref<AllocationForm[]>(initialAllocations());

const installmentsCount = computed(() => installments.value.length);
const pendingInstallmentsCount = computed(
  () =>
    installments.value.filter((installment) => !['pago', 'cancelado'].includes(installment.status)).length,
);
const installmentsTotalAmount = computed(() =>
  installments.value.reduce(
    (total, installment) => total + Number.parseFloat(installment.valor_total || '0'),
    0,
  ),
);
const persistedInstallments = computed(() => installments.value.filter((installment) => Boolean(installment.id)));
const allocationsCount = computed(() => allocations.value.length);
const formatCurrencyDisplay = (value: number) =>
  new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: form.currency ?? 'BRL',
  }).format(value ?? 0);

let suppressStatusSync = false;
const setFormStatus = (status: Status) => {
  suppressStatusSync = true;
  form.status = status;
  nextTick(() => {
    suppressStatusSync = false;
  });
};

const applyStatusToInstallments = (status: Status) => {
  const fallback = form.due_date ?? form.movement_date ?? new Date().toISOString().slice(0, 10);

  installments.value = installments.value.map((installment) => {
    const updated: InstallmentForm = { ...installment, status };

    if (status === 'pago') {
      updated.payment_date = installment.payment_date ?? updated.due_date ?? fallback;
    } else {
      updated.payment_date = null;
    }

    return updated;
  });
};

const lastOpenStatus = ref<Status>(
  form.status && !['pago', 'cancelado'].includes(form.status) ? form.status : 'planejado'
);

const statusGroupModel = computed<StatusGroup>({
  get: () => resolveStatusGroupForStatus(form.status),
  set: (group) => {
    if (group === 'settled') {
      setFormStatus('pago');
      applyStatusToInstallments('pago');
      return;
    }

    if (group === 'cancelled') {
      setFormStatus('cancelado');
      applyStatusToInstallments('cancelado');
      return;
    }

    const nextStatus = lastOpenStatus.value ?? 'planejado';
    setFormStatus(nextStatus);
    applyStatusToInstallments(nextStatus);
  },
});

watch(
  () => form.status,
  (value) => {
    if (value && !['pago', 'cancelado'].includes(value)) {
      lastOpenStatus.value = value;
    }
  },
  { immediate: true }
);

const currentStatusLabel = computed(() => resolveStatusLabel(form.status, form.type));
const currentStatusCategory = computed<TransactionStatusCategory>(() => resolveStatusCategory(form.status));

watch(
  () => props.transaction,
  () => {
    if (!props.transaction) {
      attachments.value = [];
      receipts.value = [];
      setFormStatus('planejado');
      form.bank_account_id = resolveDefaultBankAccountId();
      form.type = 'despesa';
      form.improvement_type = null;
      return;
    }

    form.bank_account_id = props.transaction.account?.id ?? null;
    form.counter_bank_account_id = props.transaction.counter_account?.id ?? null;
    form.cost_center_id = props.transaction.cost_center?.id ?? null;
    form.property_id = props.transaction.property?.id ?? null;
    form.person_id = props.transaction.person?.id ?? null;
    form.type = (props.transaction.tipo ?? 'despesa') as Tipo;
    form.improvement_type = (props.transaction.improvement_type ?? null) as ImprovementType;
    form.amount = props.transaction.valor ? String(props.transaction.valor) : '';
    form.movement_date = props.transaction.movement_date ?? new Date().toISOString().slice(0, 10);
    form.due_date = props.transaction.due_date ?? null;
    form.payment_date =
      props.transaction.payment_date ??
      props.transaction.due_date ??
      props.transaction.movement_date ??
      null;
    selectedDescriptionId.value = props.transaction.description_id ?? null;
    selectedDescriptionLabel.value =
      props.transaction.description ?? props.transaction.descricao ?? '';
    form.description = selectedDescriptionLabel.value ?? '';
    form.notes = props.transaction.notes ?? '';
    form.reference_code = props.transaction.reference_code ?? '';
    installments.value = initialInstallments();
    allocations.value = initialAllocations();
    attachments.value = props.transaction.attachments ?? [];
    receipts.value = props.transaction.receipts ?? [];
    attachmentUploadState.installmentId = null;
    selectedReceiptInstallmentId.value = null;
    setFormStatus((props.transaction.status ?? 'planejado') as Status);

    if (form.status === 'pago') {
      form.payment_date =
        props.transaction.payment_date ??
        props.transaction.due_date ??
        form.due_date ??
        form.movement_date ??
        new Date().toISOString().slice(0, 10);
    } else {
      form.payment_date = null;
    }

    nextTick(() => {
      syncTextareaHeight(descriptionFieldRef.value, 44);
      syncTextareaHeight(notesFieldRef.value, 44);
    });
  },
);

watch(
  () => form.status,
  (value, previous) => {
    if (suppressStatusSync || !value || value === previous) {
      return;
    }

    if (value === 'pago') {
      form.payment_date =
        form.due_date ?? form.movement_date ?? new Date().toISOString().slice(0, 10);
    } else {
      form.payment_date = null;
    }

    applyStatusToInstallments(value as Status);
  },
);

const fetchDescriptionSuggestions = async (term: string) => {
  try {
    descriptionLoading.value = true;
    const { data } = await axios.get('/api/financeiro/journal-entry-descriptions', {
      params: {
        search: term,
        limit: 8,
      },
    });

    lastRemoteSuggestions = Array.isArray(data?.data) ? data.data : [];
    applyDescriptionSuggestions();
  } catch (error) {
    console.error(error);
    lastRemoteSuggestions = [];
    applyDescriptionSuggestions();
  } finally {
    descriptionLoading.value = false;
  }
};

const scheduleDescriptionFetch = (term: string) => {
  if (descriptionSearchTimeout) {
    clearTimeout(descriptionSearchTimeout);
  }

  const normalized = term.trim();
  descriptionSearchTerm.value = normalized;

  descriptionSearchTimeout = setTimeout(() => {
    if (normalized.length < 2) {
      fetchDescriptionSuggestions('');
      return;
    }

    fetchDescriptionSuggestions(normalized);
  }, 200);
};

watch(
  () => form.description,
  (value) => {
    const normalizedValue = (value ?? '').trim();
    const normalizedSelected = (selectedDescriptionLabel.value ?? '').trim();

    if (normalizedValue !== normalizedSelected) {
      selectedDescriptionId.value = null;
      selectedDescriptionLabel.value = value ?? '';
    }

    scheduleDescriptionFetch(normalizedValue);
    nextTick(() => syncTextareaHeight(descriptionFieldRef.value, 44));
  },
  { immediate: true }
);

watch(
  () => form.notes,
  () => {
    nextTick(() => syncTextareaHeight(notesFieldRef.value, 44));
  },
  { immediate: true }
);

applyDescriptionSuggestions();

const handleDescriptionFocus = () => {
  showDescriptionSuggestions.value = true;
  scheduleDescriptionFetch((form.description ?? '').trim());
};

const handleDescriptionBlur = () => {
  setTimeout(() => {
    showDescriptionSuggestions.value = false;
  }, 150);
};

const syncTextareaHeight = (element: HTMLTextAreaElement | null, minHeight = 0) => {
  if (!element) return;

  element.style.height = 'auto';
  const nextHeight = Math.max(minHeight, element.scrollHeight);
  element.style.height = `${nextHeight}px`;
};

const autoResizeTextarea = (event: Event, minHeight = 0) => {
  syncTextareaHeight(event.target as HTMLTextAreaElement | null, minHeight);
};

const autoResizeDescription = (event: Event) => {
  autoResizeTextarea(event, 44);
};

const autoResizeNotes = (event: Event) => {
  autoResizeTextarea(event, 44);
};

function applyDescriptionSuggestions() {
  const term = descriptionSearchTerm.value.toLowerCase();

  const localMatches = historyDescriptions.value
    .filter((entry) => (term ? entry.toLowerCase().includes(term) : true))
    .map((entry, index) => ({
      id: -(index + 1),
      texto: entry,
      uso_total: undefined,
      ultima_utilizacao: null,
    }));

  const localTexts = new Set(localMatches.map((match) => match.texto.toLowerCase()));

  const remoteMatches = (lastRemoteSuggestions ?? []).filter((suggestion) => {
    const text = (suggestion.texto ?? '').toLowerCase();
    return text !== '' && !localTexts.has(text);
  });

  descriptionSuggestions.value = [...localMatches, ...remoteMatches];
}

const selectDescription = (suggestion: DescriptionSuggestion) => {
  selectedDescriptionId.value = suggestion.id;
  selectedDescriptionLabel.value = suggestion.texto;
  form.description = suggestion.texto;
  showDescriptionSuggestions.value = false;
  registerDescriptionHistory(suggestion.texto);
};

watch(
  () => form.amount,
  (value) => {
    const amount = Number.parseFloat(value || '0');
    if (Number.isNaN(amount)) {
      return;
    }

    if (installments.value.length === 1) {
      const amountFixed = amount > 0 ? amount.toFixed(2) : '0';
      const installment = installments.value[0];
      installment.valor_principal = amountFixed;
      installment.valor_total = amountFixed;
    }
  }
);

watch(
  () => form.movement_date,
  (value) => {
    if (!value) {
      return;
    }

    if (installments.value.length === 1) {
      const installment = installments.value[0];
      installment.movement_date = value;
      if (!form.due_date) {
        installment.due_date = value;
      }
    }
  }
);

watch(
  () => form.due_date,
  (value) => {
    if (installments.value.length === 1 && value) {
      installments.value[0].due_date = value;
    }

    if (form.status === 'pago') {
      form.payment_date = value ?? form.movement_date ?? new Date().toISOString().slice(0, 10);
      applyStatusToInstallments(form.status);
    }
  }
);

const openInstallmentsManager = () => {
  showInstallmentsManager.value = true;
};

const closeInstallmentsManager = () => {
  showInstallmentsManager.value = false;
};

const resolveRequiredRole = (type: Tipo): string | null => {
  if (type === 'receita') {
    return 'Cliente';
  }

  if (type === 'despesa') {
    return 'Fornecedor';
  }

  if (type === 'transferencia') {
    return null;
  }

  return null;
};

const normalizePersonOption = (person: any, fallbackRole?: string): PersonOption => ({
  id: Number(person?.id ?? 0),
  nome: String(person?.nome ?? person?.nome_razao_social ?? '').trim(),
  papeis: Array.isArray(person?.papeis)
    ? person.papeis
    : fallbackRole
      ? [fallbackRole]
      : [],
});

const peopleList = ref<PersonOption[]>(
  props.people?.map((person) => normalizePersonOption(person)) ?? []
);

const originalPropertyLabel = computed(() => {
  const property = props.transaction?.property ?? null;
  if (property?.nome) {
    return property.nome;
  }

  const tx = props.transaction as any;

  return tx?.property_label
    ?? tx?.propertyLabel
    ?? tx?.property_label_mcc
    ?? tx?.propertyLabelMcc
    ?? null;
});

const mergePeople = (items: PersonOption[]) => {
  const map = new Map<number, PersonOption>(
    (peopleList.value ?? []).map((person) => [person.id, person]),
  );

  items.forEach((item) => {
    if (!item.id) {
      return;
    }

    const existing = map.get(item.id);
    if (!existing) {
      map.set(item.id, item);
      return;
    }

    const roles = new Set<string>();
    if (Array.isArray(existing.papeis)) {
      existing.papeis.forEach((role) => roles.add(role));
    }
    if (Array.isArray(item.papeis)) {
      item.papeis.forEach((role) => roles.add(role));
    }

    map.set(item.id, {
      ...existing,
      ...item,
      papeis: Array.from(roles),
    });
  });

  peopleList.value = Array.from(map.values()).sort((a, b) => a.nome.localeCompare(b.nome));
};

watch(
  () => props.people,
  (newValue) => {
    mergePeople((newValue ?? []).map((person) => normalizePersonOption(person)));
  },
  { deep: true }
);

watch(
  () => props.transaction?.person,
  (person) => {
    if (!person) {
      return;
    }

    const normalized = normalizePersonOption(person, resolveRequiredRole(form.type) ?? undefined);
    mergePeople([normalized]);
  },
  { immediate: true }
);

const costCenterOptions = computed(() => props.costCenters);

const costCenterHierarchy = computed(() => {
  const list = (props.costCenters ?? []) as Array<Option & { parent_id?: number | null }>;
  const byId = new Map<number, Option & { parent_id?: number | null }>();
  const byCode = new Map<string, Option & { parent_id?: number | null }>();

  list.forEach((center) => {
    byId.set(center.id, center);
    if (center.codigo) {
      byCode.set(center.codigo.trim(), center);
    }
  });

  const findParent = (center: Option & { parent_id?: number | null }) => {
    if (center.parent_id && byId.has(center.parent_id)) {
      return byId.get(center.parent_id) ?? null;
    }

    const code = center.codigo?.trim();
    if (!code) {
      return null;
    }

    const parts = code.split('.');
    if (parts.length < 2) {
      return null;
    }

    const candidates = new Set<string>();
    const baseParts = parts.slice(0, -1);
    const lastPart = parts[parts.length - 1];

    candidates.add([...baseParts, '0'].join('.'));
    candidates.add([...baseParts, '00'].join('.'));
    candidates.add(baseParts.join('.'));
    candidates.add([...baseParts, lastPart.replace(/[0-9]/g, '0')].join('.'));

    for (let i = baseParts.length; i >= 1; i--) {
      const prefix = baseParts.slice(0, i).join('.');
      if (prefix) {
        candidates.add(prefix);
        candidates.add(`${prefix}.0`);
      }
    }

    let best: Option | null = null;
    let bestScore = -1;

    candidates.forEach((candidateCode) => {
      const normalized = candidateCode.trim();
      if (!normalized) {
        return;
      }

      const candidate = byCode.get(normalized);
      if (candidate && candidate.id !== center.id) {
        const score = normalized.length;
        if (score > bestScore) {
          best = candidate;
          bestScore = score;
        }
      }
    });

    return best ?? null;
  };

  const parentById = new Map<number, Option | null>();
  const selectable: Array<Option & { parent_id?: number | null }> = [];

  list.forEach((center) => {
    const parent = findParent(center);
    parentById.set(center.id, parent);
    if (parent) {
      selectable.push(center);
    }
  });

  return {
    byId,
    parentById,
    selectable,
  };
});

const costCenterSelectOptions = computed(() => {
  const base = costCenterHierarchy.value.selectable.map((center) => ({
    value: center.id,
    label: center.codigo ? `${center.codigo} — ${center.nome}` : center.nome,
  }));

  const currentId = form.cost_center_id;
  if (!isCreate.value && currentId && !base.some((option) => option.value === currentId)) {
    const current = costCenterHierarchy.value.byId.get(currentId);
    if (current) {
      base.unshift({
        value: current.id,
        label: current.codigo ? `${current.codigo} — ${current.nome}` : current.nome,
      });
    }
  }

  return base;
});

const selectedCostCenterParent = computed<Option | null>(() => {
  const id = form.cost_center_id;
  if (!id) {
    return null;
  }

  return costCenterHierarchy.value.parentById.get(id) ?? null;
});

const selectedCostCenterParentLabel = computed(() => {
  const parent = selectedCostCenterParent.value;
  if (!parent) {
    return null;
  }

  return parent.codigo ? `${parent.codigo} — ${parent.nome}` : parent.nome;
});
const peopleOptions = computed<PersonOption[]>(() => peopleList.value ?? []);

const personSearchState = reactive<{ term: string; timeoutId: number | null }>({
  term: '',
  timeoutId: null,
});

const fetchPeople = async (term: string) => {
  const query = term.trim();

  if (query.length < 2) {
    return;
  }

  try {
    const params: Record<string, any> = {
      'filter[search]': query,
      per_page: 20,
    };

    const role = resolveRequiredRole(form.type);
    if (role) {
      params['filter[papel]'] = role;
    }

    const { data } = await axios.get('/api/pessoas', { params });

    const items = Array.isArray(data?.data)
      ? data.data.map((item: any) => normalizePersonOption(item, role ?? undefined))
      : [];

    if (items.length) {
      mergePeople(items);
    }
  } catch (error) {
    console.error('Não foi possível carregar pessoas', error);
  }
};

const handlePersonSearch = (term: string) => {
  personSearchState.term = term;

  if (personSearchState.timeoutId) {
    window.clearTimeout(personSearchState.timeoutId);
  }

  personSearchState.timeoutId = window.setTimeout(() => {
    fetchPeople(personSearchState.term);
  }, 300);
};
const personFieldLabel = computed(() => {
  if (form.type === 'receita') {
    return 'Cliente';
  }

  if (form.type === 'despesa') {
    return 'Fornecedor';
  }

  if (form.type === 'transferencia') {
    return 'Transferência';
  }

  return 'Pessoa';
});
const personFieldPlaceholder = computed(() => {
  if (personFieldLabel.value === 'Cliente') {
    return 'Selecione um cliente';
  }

  if (personFieldLabel.value === 'Fornecedor') {
    return 'Selecione um fornecedor';
  }

  if (personFieldLabel.value === 'Transferência') {
    return 'Selecione cliente ou fornecedor';
  }

  return 'Não vinculado';
});
const personCreateLabel = computed(() => {
  if (personFieldLabel.value === 'Transferência') {
    return 'cliente ou fornecedor';
  }

  if (personFieldLabel.value === 'Pessoa') {
    return 'pessoa';
  }

  return personFieldLabel.value.toLowerCase();
});
const personCreationRoles = computed<PessoaRole[]>(() => {
  if (form.type === 'receita') {
    return ['Cliente'];
  }

  if (form.type === 'despesa') {
    return ['Fornecedor'];
  }

  if (form.type === 'transferencia') {
    return ['Cliente', 'Fornecedor'];
  }

  return ['Cliente'];
});
const showPersonModal = ref(false);
const personModalRoles = ref<PessoaRole[]>([]);
const propertyOptions = computed(() => props.properties);
const isTransfer = computed(() => form.type === 'transferencia');
const accountSelectOptions = computed(() =>
  (props.accounts ?? []).map((account) => ({
    value: account.id,
    label: account.nome ?? `Conta ${account.id}`,
  }))
);
const counterAccountSelectOptions = computed(() =>
  accountSelectOptions.value.filter((option) => option.value !== form.bank_account_id)
);
const personSelectOptions = computed(() =>
  peopleOptions.value.map((person) => ({
    value: person.id,
    label: person.nome,
  }))
);
const hasPersonOptions = computed(() => personSelectOptions.value.length > 0);
const propertySelectOptions = computed(() =>
  (props.properties ?? []).map((property) => ({
    value: property.id,
    label: property.titulo ?? property.codigo_interno ?? `Imóvel ${property.id}`,
  }))
);

watch(
  () => props.accounts,
  () => {
    if (!isCreate.value) {
      return;
    }

    const validIds = new Set(accountSelectOptions.value.map((option) => option.value));
    const currentId = form.bank_account_id;

    if (currentId && validIds.has(currentId)) {
      return;
    }

    form.bank_account_id = resolveDefaultBankAccountId();
  },
  { deep: true, immediate: true }
);

const errors = reactive<Record<string, string>>({});
const descriptionError = computed(
  () => errors.description_custom ?? errors.description_id ?? errors.description,
);

const resetErrors = () => {
  Object.keys(errors).forEach((key) => delete errors[key]);
};

watch(
  [() => form.type, () => peopleOptions.value.map((person) => person.id)],
  () => {
    if (!form.person_id) {
      return;
    }

    const allowedIds = new Set(peopleOptions.value.map((person) => Number(person.id)));
    const currentId = Number(form.person_id);
    if (!allowedIds.has(currentId)) {
      form.person_id = null;
    }
  },
  { immediate: true },
);

const openPersonModal = () => {
  personModalRoles.value = [...personCreationRoles.value];
  showPersonModal.value = true;
};

const handlePersonModalClose = () => {
  showPersonModal.value = false;
};

const handlePersonModalCreated = (person: { id: number; nome: string; papeis: PessoaRole[] }) => {
  const normalized = normalizePersonOption(person);
  peopleList.value = [
    normalized,
    ...peopleList.value.filter((existing) => existing.id !== normalized.id),
  ];
  form.person_id = normalized.id;
  showPersonModal.value = false;
};

watch(
  () => [form.type, form.bank_account_id, form.counter_bank_account_id] as const,
  ([type, bankId, counterId]) => {
    if (type === 'transferencia' && counterId !== null && bankId === counterId) {
      form.counter_bank_account_id = null;
    }
  },
);

const addInstallment = () => {
  const nextNumber = installments.value.length + 1;
  installments.value.push({
    id: null,
    numero_parcela: nextNumber,
    movement_date: form.movement_date,
    due_date: form.due_date ?? form.movement_date,
    payment_date:
      form.status === 'pago'
        ? form.due_date ?? form.movement_date ?? new Date().toISOString().slice(0, 10)
        : null,
    valor_principal: '0',
    valor_juros: '0',
    valor_multa: '0',
    valor_desconto: '0',
    valor_total: '0',
    status: form.status ?? 'planejado',
    meta: null,
  });
};

const addAllocation = () => {
  allocations.value.push({
    cost_center_id: null,
    property_id: null,
    percentage: '',
    amount: '',
  });
};

const removeAllocation = (index: number) => {
  allocations.value.splice(index, 1);
};

const removeInstallment = (index: number) => {
  if (installments.value.length === 1) {
    toast.error('O lançamento deve possuir pelo menos uma parcela.');
    return;
  }

  installments.value.splice(index, 1);
  installments.value.forEach((installment, idx) => {
    installment.numero_parcela = idx + 1;
  });
};

const generateEqualInstallments = () => {
  const count = installments.value.length;
  const amount = Number.parseFloat(form.amount || '0');

  if (!count || amount <= 0) {
    toast.error('Informe o valor total e o número de parcelas.');
    return;
  }

  const baseValue = Math.floor((amount / count) * 100) / 100;
  let remainder = Math.round(amount * 100) - Math.round(baseValue * 100) * count;

  installments.value.forEach((installment, index) => {
    let value = baseValue;
    if (remainder > 0) {
      value += 0.01;
      remainder -= 1;
    }

    installment.valor_principal = value.toFixed(2);
   installment.valor_total = value.toFixed(2);
   installment.movement_date = form.movement_date;
   const dueDate = new Date(form.movement_date ?? new Date().toISOString().slice(0, 10));
   dueDate.setMonth(dueDate.getMonth() + index);
   installment.due_date = dueDate.toISOString().slice(0, 10);
   installment.payment_date =
      form.status === 'pago'
        ? installment.due_date ?? form.due_date ?? form.movement_date ?? new Date().toISOString().slice(0, 10)
        : null;
 });
};

const buildPayload = () => {
  const amount = Number.parseFloat(form.amount || '0');
  const resolvedPaymentDate =
    form.status === 'pago'
      ? form.payment_date ?? form.due_date ?? form.movement_date ?? new Date().toISOString().slice(0, 10)
      : null;

  if (form.status === 'pago') {
    form.payment_date = resolvedPaymentDate;
  } else {
    form.payment_date = null;
  }

  return {
    type: form.type,
    bank_account_id: form.bank_account_id,
    counter_bank_account_id: isTransfer.value ? form.counter_bank_account_id : null,
    cost_center_id: form.cost_center_id != null ? Number(form.cost_center_id) : null,
    property_id: form.property_id != null ? Number(form.property_id) : null,
    person_id: form.person_id != null ? Number(form.person_id) : null,
    improvement_type: form.improvement_type,
    movement_date: form.movement_date,
    due_date: form.due_date || form.movement_date,
    payment_date: resolvedPaymentDate,
    description_id: selectedDescriptionId.value,
    description_custom: selectedDescriptionId.value ? null : form.description || null,
    notes: form.notes || null,
    reference_code: form.reference_code || null,
    currency: form.currency,
    status: form.status,
    amount,
    installments: installments.value.map((installment, index) => ({
      id: installment.id ?? undefined,
      numero_parcela: installment.numero_parcela ?? index + 1,
      movement_date: installment.movement_date,
      due_date: installment.due_date,
      payment_date:
        (installment.status ?? form.status) === 'pago'
          ? installment.payment_date ?? installment.due_date ?? resolvedPaymentDate
          : null,
      valor_principal: Number.parseFloat(installment.valor_principal || '0'),
      valor_juros: Number.parseFloat(installment.valor_juros || '0'),
      valor_multa: Number.parseFloat(installment.valor_multa || '0'),
      valor_desconto: Number.parseFloat(installment.valor_desconto || '0'),
      valor_total: Number.parseFloat(installment.valor_total || '0'),
      status: installment.status ?? 'planejado',
      meta: installment.meta ?? null,
    })),
    allocations: allocations.value.map((allocation) => ({
      cost_center_id: allocation.cost_center_id,
      property_id: allocation.property_id,
      percentage:
        allocation.percentage !== '' ? Number.parseFloat(allocation.percentage) : null,
      amount: allocation.amount !== '' ? Number.parseFloat(allocation.amount) : null,
    })),
  };
};

const handleSubmit = async () => {
  if (!canSubmit.value) {
    return;
  }

  resetErrors();
  submitting.value = true;

  try {
    const payload = buildPayload();

    if (isCreate.value) {
      await axios.post('/api/financeiro/journal-entries', payload);
      toast.success('Lançamento criado com sucesso.');
    } else if (props.transaction?.id) {
      await axios.put(`/api/financeiro/journal-entries/${props.transaction.id}`, payload);
      toast.success('Lançamento atualizado com sucesso.');
    }

    registerAccountUsage(form.bank_account_id);
    registerDescriptionHistory(form.description);
    applyDescriptionSuggestions();

    if (props.redirectOnSave) {
      router.visit('/financeiro', { preserveScroll: true, preserveState: true });
    } else {
      emit('saved');
    }
  } catch (error: any) {
    if (error?.response?.status === 422) {
      const validation = error.response.data?.errors ?? {};
      Object.entries(validation).forEach(([field, messages]) => {
        errors[field] = Array.isArray(messages) ? String(messages[0]) : String(messages);
      });
      toast.error('Corrija os campos destacados e tente novamente.');
    } else {
      const message = error?.response?.data?.message ?? 'Não foi possível salvar o lançamento.';
      toast.error(message);
    }
  } finally {
    submitting.value = false;
  }
};

const handleCancel = () => {
  if (submitting.value || deleting.value) {
    return;
  }

  if (isModalContext.value) {
    emit('cancel');
  }
};

const handleDelete = async () => {
  if (isCreate.value || !props.transaction?.id || !props.permissions.delete) {
    return;
  }

  const confirmed = window.confirm('Deseja realmente excluir este lançamento? Esta ação não pode ser desfeita.');
  if (!confirmed) {
    return;
  }

  deleting.value = true;

  try {
    await axios.delete(`/api/financeiro/journal-entries/${props.transaction.id}`);
    toast.success('Lançamento excluído com sucesso.');

    if (props.redirectOnSave) {
      router.visit('/financeiro', { preserveScroll: true, preserveState: true });
    } else {
      emit('deleted');
    }
  } catch (error: any) {
    const message = error?.response?.data?.message ?? 'Não foi possível excluir o lançamento.';
    toast.error(message);
  } finally {
    deleting.value = false;
  }
};

const installmentStatusOptions: Array<{ value: Status; label: string }> = [
  { value: 'planejado', label: 'Planejado' },
  { value: 'pendente', label: 'Pendente' },
  { value: 'atrasado', label: 'Atrasado' },
  { value: 'pago', label: 'Pago' },
  { value: 'cancelado', label: 'Cancelado' },
];

const transactionStatusOptions = computed<Array<{ value: StatusGroup; label: string }>>(() => [
  { value: 'open', label: resolveStatusGroupLabel('open', form.type) },
  { value: 'settled', label: resolveStatusGroupLabel('settled', form.type) },
  { value: 'cancelled', label: resolveStatusGroupLabel('cancelled', form.type) },
]);

onBeforeUnmount(() => {
  if (descriptionSearchTimeout) {
    clearTimeout(descriptionSearchTimeout);
  }
});

const fetchAttachments = async () => {
  if (!entryId.value) {
    return;
  }

  attachmentsLoading.value = true;
  try {
    const { data } = await axios.get(`/api/financeiro/journal-entries/${entryId.value}/attachments`);
    attachments.value = Array.isArray(data?.data) ? data.data : [];
  } catch (error) {
    console.error(error);
    toast.error('Não foi possível carregar os anexos.');
  } finally {
    attachmentsLoading.value = false;
  }
};

const fetchReceipts = async () => {
  if (!entryId.value) {
    return;
  }

  receiptsLoading.value = true;
  try {
    const { data } = await axios.get(`/api/financeiro/journal-entries/${entryId.value}/receipts`);
    receipts.value = Array.isArray(data?.data) ? data.data : [];
  } catch (error) {
    console.error(error);
    toast.error('Não foi possível carregar os recibos.');
  } finally {
    receiptsLoading.value = false;
  }
};

watch(
  () => entryId.value,
  (value) => {
    if (!value) {
      attachments.value = [];
      receipts.value = [];
      attachmentsLoaded.value = false;
      receiptsLoaded.value = false;
      return;
    }

    if (!isModalContext.value) {
      attachmentsLoaded.value = true;
      receiptsLoaded.value = true;
      fetchAttachments();
      fetchReceipts();
      return;
    }

    attachmentsLoaded.value = false;
    receiptsLoaded.value = false;

    if (activeTab.value === 'attachments') {
      attachmentsLoaded.value = true;
      fetchAttachments();
    }

    if (activeTab.value === 'receipts') {
      receiptsLoaded.value = true;
      fetchReceipts();
    }
  },
  { immediate: true },
);

watch(
  () => activeTab.value,
  (tab) => {
    if (!isModalContext.value || !entryId.value) {
      return;
    }

    if (tab === 'attachments' && !attachmentsLoaded.value) {
      attachmentsLoaded.value = true;
      fetchAttachments();
    }

    if (tab === 'receipts' && !receiptsLoaded.value) {
      receiptsLoaded.value = true;
      fetchReceipts();
    }
  }
);

const handleAttachmentFileChange = (event: Event) => {
  const input = event.target as HTMLInputElement;
  attachmentUploadState.file = input.files?.[0] ?? null;
};

const uploadAttachment = async () => {
  if (!entryId.value) {
    toast.error('Salve o lançamento antes de anexar arquivos.');
    return;
  }

  if (!attachmentUploadState.file) {
    toast.error('Selecione um arquivo para anexar.');
    return;
  }

  attachmentUploadState.loading = true;

  try {
    const formData = new FormData();
    formData.append('file', attachmentUploadState.file);
    if (attachmentUploadState.installmentId) {
      formData.append('installment_id', String(attachmentUploadState.installmentId));
    }

    const { data } = await axios.post(
      `/api/financeiro/journal-entries/${entryId.value}/attachments`,
      formData,
      {
        headers: { 'Content-Type': 'multipart/form-data' },
      },
    );

    const attachment = data?.data;
    if (attachment) {
      attachments.value = [attachment, ...attachments.value.filter((item) => item.id !== attachment.id)];
    } else {
      await fetchAttachments();
    }

    toast.success('Anexo adicionado com sucesso.');
    attachmentUploadState.file = null;
    attachmentUploadState.installmentId = null;
    if (attachmentInputRef.value) {
      attachmentInputRef.value.value = '';
    }
  } catch (error: any) {
    const message = error?.response?.data?.message ?? 'Não foi possível enviar o anexo.';
    toast.error(message);
  } finally {
    attachmentUploadState.loading = false;
  }
};

const removeAttachment = async (attachment: AttachmentSummary) => {
  if (!entryId.value) {
    return;
  }

  const confirmed = window.confirm('Deseja remover este anexo?');
  if (!confirmed) {
    return;
  }

  try {
    await axios.delete(`/api/financeiro/journal-entries/${entryId.value}/attachments/${attachment.id}`);
    attachments.value = attachments.value.filter((item) => item.id !== attachment.id);
    toast.success('Anexo removido.');
  } catch (error: any) {
    toast.error(error?.response?.data?.message ?? 'Não foi possível remover o anexo.');
  }
};

const formatBytes = (size: number): string => {
  if (!Number.isFinite(size) || size <= 0) {
    return '0 B';
  }

  const units = ['B', 'KB', 'MB', 'GB'];
  const exponent = Math.min(Math.floor(Math.log(size) / Math.log(1024)), units.length - 1);
  const value = size / 1024 ** exponent;

  return `${value.toFixed(value < 10 && exponent > 0 ? 1 : 0)} ${units[exponent]}`;
};

const downloadAttachment = (attachment: AttachmentSummary) => {
  window.open(attachment.download_url, '_blank');
};

const generateReceipt = async () => {
  if (!entryId.value) {
    toast.error('Salve o lançamento antes de gerar recibos.');
    return;
  }

  generatingReceipt.value = true;

  try {
    const payload: Record<string, any> = {};
    if (selectedReceiptInstallmentId.value) {
      payload.installment_id = selectedReceiptInstallmentId.value;
    }

    const { data } = await axios.post(
      `/api/financeiro/journal-entries/${entryId.value}/generate-receipt`,
      payload,
    );

    const receipt = data?.data;
    if (receipt) {
      receipts.value = [receipt, ...receipts.value.filter((item) => item.id !== receipt.id)];
    } else {
      await fetchReceipts();
    }

    toast.success(data?.message ?? 'Recibo gerado com sucesso.');
  } catch (error: any) {
    const message = error?.response?.data?.message ?? 'Não foi possível gerar o recibo.';
    toast.error(message);
  } finally {
    generatingReceipt.value = false;
  }
};

const downloadReceipt = (receipt: ReceiptSummary) => {
  if (!receipt.download_url) {
    toast.info('Recibo ainda em processamento.');
    return;
  }

  window.open(receipt.download_url, '_blank');
};
</script>

<template>
  <section :class="['space-y-6', appearanceClasses]">
    <header
      v-if="!isModalContext"
      class="flex flex-col gap-2 border border-slate-200 bg-white p-4 shadow-sm"
    >
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-base font-semibold text-slate-900">
            {{ isCreate ? 'Novo lançamento' : `Lançamento #${props.transaction?.id}` }}
          </h1>
          <p class="text-xs text-slate-500">
            {{
              isCreate
                ? 'Preencha os dados para registrar o lançamento financeiro.'
                : 'Atualize as informações do lançamento financeiro.'
            }}
          </p>
        </div>
        <TransactionStatusBadge
          v-if="!isCreate && props.transaction"
          :status="form.status"
          :label="currentStatusLabel"
          :category="currentStatusCategory"
          :type="form.type"
        />
      </div>
    </header>

    <form class="space-y-6" @submit.prevent="handleSubmit">
      <section v-if="showBasicSection" :class="[panelClasses, 'space-y-4']">
          <div class="flex flex-col gap-5">
          <div class="grid gap-4 md:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
            <div class="flex flex-col gap-2">
              <label
                :class="[
                  'text-sm font-medium',
                  isDarkAppearance ? 'text-slate-100' : 'text-slate-700'
                ]"
              >
                Tipo *
              </label>
              <div class="grid gap-1 md:grid-cols-3">
                <label
                  v-for="option in typeOptions"
                  :key="option.value"
                  class="group relative flex cursor-pointer flex-col gap-1 rounded-[18px] border px-2.5 py-2 text-xs transition md:px-3 md:py-2.5 md:text-sm"
                  :class="getTypeOptionClasses(option)"
                >
                  <input
                    v-model="form.type"
                    class="sr-only"
                    type="radio"
                    :value="option.value"
                    :disabled="!canSubmit"
                  />
                  <div class="flex items-center justify-between gap-2">
                    <span
                      class="text-xs font-semibold tracking-tight transition md:text-sm"
                      :class="getTypeOptionLabelClasses(option)"
                    >
                      {{ option.label }}
                    </span>
                    <span
                      class="inline-flex h-5 w-5 items-center justify-center rounded-full border text-[10px] font-semibold transition md:h-6 md:w-6"
                      :class="getTypeOptionBadgeClasses(option)"
                    >
                      {{
                        option.value === 'despesa'
                          ? '−'
                          : option.value === 'receita'
                            ? '+'
                            : '⇄'
                      }}
                    </span>
                  </div>
                </label>
            </div>
            <p v-if="errors.type" class="text-xs text-rose-400">{{ errors.type }}</p>
          </div>

            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-slate-700">Data do movimento *</label>
              <DatePicker
                v-model="form.movement_date"
                placeholder="dd/mm/aaaa"
                :required="canSubmit"
                :disabled="!canSubmit"
                :invalid="Boolean(errors.movement_date)"
                :appearance="isDarkAppearance ? 'dark' : 'light'"
              />
              <p v-if="errors.movement_date" class="text-xs text-rose-600">{{ errors.movement_date }}</p>
            </div>

            <div class="md:col-span-2 flex flex-wrap gap-2 pt-1">
              <label
                v-for="option in improvementTypeOptions"
                :key="option.value"
                class="group inline-flex min-w-[140px] cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold transition"
                :class="improvementOptionClasses(option.value)"
              >
                <input
                  type="checkbox"
                  class="sr-only"
                  :checked="isImprovementActive(option.value)"
                  :disabled="!canSubmit"
                  @change="toggleImprovementType(option.value)"
                />
                <span
                  class="inline-flex h-4 w-4 items-center justify-center rounded-full text-[10px] transition"
                  :class="improvementOptionIconClasses(option.value)"
                >
                  <svg
                    v-if="isImprovementActive(option.value)"
                    class="h-2.5 w-2.5"
                    viewBox="0 0 16 16"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 8l3 3 5-6" />
                  </svg>
                </span>
                <span>{{ option.label }}</span>
              </label>
              <p v-if="errors.improvement_type" class="text-xs text-rose-400 w-full">
                {{ errors.improvement_type }}
              </p>
            </div>
          </div>

            <div class="grid gap-3 md:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-slate-700">Conta *</label>
              <SearchableSelect
                v-model="form.bank_account_id"
                :options="accountSelectOptions"
                :disabled="!canSubmit"
                placeholder="Selecione uma conta"
                :allow-empty="false"
              />
              <p v-if="errors.bank_account_id" class="text-xs text-rose-600">{{ errors.bank_account_id }}</p>
            </div>

            <div class="flex flex-col gap-1.5">
                <div class="flex flex-wrap items-start justify-between gap-1">
                  <label class="text-sm font-medium text-slate-700">Centro de custo</label>
                  <span
                    v-if="selectedCostCenterParentLabel"
                    :class="[
                      'text-[11px] font-medium',
                    isDarkAppearance ? 'text-slate-300/80' : 'text-slate-500'
                  ]"
                >
                  Pai: {{ selectedCostCenterParentLabel }}
                </span>
              </div>
              <SearchableSelect
                v-model="form.cost_center_id"
                :options="costCenterSelectOptions"
                :disabled="!canSubmit"
                placeholder="Não vinculado"
                empty-label="Não vinculado"
              />
            </div>

            <div v-if="isTransfer" class="flex flex-col gap-1.5 md:col-span-2">
              <label class="text-sm font-medium text-slate-700">Conta destino *</label>
              <SearchableSelect
                v-model="form.counter_bank_account_id"
                :options="counterAccountSelectOptions"
                :disabled="!canSubmit"
                placeholder="Selecione uma conta"
                :allow-empty="false"
              />
              <p v-if="errors.counter_bank_account_id" class="text-xs text-rose-600">
                {{ errors.counter_bank_account_id }}
              </p>
            </div>
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-slate-700">Descrição</label>
            <div class="relative">
              <textarea
                v-model="form.description"
                :disabled="!canSubmit"
                class="w-full min-h-[2.75rem] resize-none overflow-hidden rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm leading-relaxed focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                @focus="handleDescriptionFocus"
                @blur="handleDescriptionBlur"
                @input="autoResizeDescription"
                ref="descriptionFieldRef"
              />
              <div
                v-if="showDescriptionSuggestions"
                class="absolute left-0 right-0 z-20 mt-1 max-h-56 overflow-auto rounded-lg border border-slate-700 bg-slate-900 shadow-xl shadow-black/40"
              >
                <p v-if="descriptionLoading" class="px-3 py-2 text-xs text-slate-400">Carregando descrições...</p>
                <template v-else>
                  <button
                    v-for="suggestion in descriptionSuggestions"
                    :key="suggestion.id"
                    type="button"
                    class="flex w-full items-center px-3 py-2 text-left text-sm text-slate-200 hover:bg-slate-800"
                    @mousedown.prevent="selectDescription(suggestion)"
                  >
                    {{ suggestion.texto }}
                  </button>
                  <p
                    v-if="!descriptionSuggestions.length"
                    class="px-3 py-2 text-xs text-slate-500"
                  >
                    Nenhuma descrição recente encontrada.
                  </p>
                </template>
              </div>
            </div>
            <p v-if="descriptionError" class="text-xs text-rose-600">{{ descriptionError }}</p>
          </div>

          <div class="grid gap-3 md:grid-cols-2">
            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-slate-700">{{ personFieldLabel }}</label>
              <SearchableSelect
                v-model="form.person_id"
                :options="personSelectOptions"
                :disabled="!canSubmit || !hasPersonOptions"
                :placeholder="personFieldPlaceholder"
                :empty-label="personFieldPlaceholder"
                open-strategy="typing"
                :show-toggle="false"
                @search="handlePersonSearch"
              />
              <div
                class="mt-1 text-[11px] leading-snug"
                :class="isDarkAppearance ? 'text-slate-400' : 'text-slate-500'"
              >
                Não encontrou?
                <button
                  type="button"
                  class="font-medium text-indigo-500 underline decoration-dotted underline-offset-2 hover:text-indigo-400"
                  @click="openPersonModal"
                >
                  Adicionar novo {{ personCreateLabel }}
                </button>
              </div>
            </div>

            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-slate-700">Imóvel</label>
              <SearchableSelect
                v-model="form.property_id"
                :options="propertySelectOptions"
                :disabled="!canSubmit"
                placeholder="Não vinculado"
                empty-label="Não vinculado"
              />
              <p
                v-if="!form.property_id && originalPropertyLabel"
                class="mt-1 text-xs text-slate-400"
              >
                Imóvel (CSV): {{ originalPropertyLabel }}
              </p>
            </div>
          </div>

          <div class="grid gap-3 md:grid-cols-2">
            <MoneyInput
              v-model="form.amount"
              name="valor"
              label="Valor *"
              :required="canSubmit"
              :disabled="!canSubmit"
              input-class="border-slate-300 bg-white text-slate-900 rounded-md px-3 py-1.5 focus:border-indigo-500 focus:ring-indigo-500"
            />

            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-slate-700">Data de vencimento</label>
              <DatePicker
                v-model="form.due_date"
                placeholder="dd/mm/aaaa"
                :disabled="!canSubmit"
                :appearance="isDarkAppearance ? 'dark' : 'light'"
              />
            </div>
          </div>

          <div class="grid gap-3 md:grid-cols-2">
            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-slate-700">Código de referência</label>
              <input
                v-model="form.reference_code"
                type="text"
                :disabled="!canSubmit"
                class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              />
            </div>

            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-slate-700">Status</label>
              <select
                v-model="statusGroupModel"
                :disabled="!canSubmit"
                class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              >
                <option
                  v-for="option in transactionStatusOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option>
              </select>
              <p v-if="errors.status" class="text-xs text-rose-600">{{ errors.status }}</p>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-medium text-slate-700">Observações</label>
          <textarea
            v-model="form.notes"
            :disabled="!canSubmit"
            class="w-full min-h-[2.75rem] resize-none overflow-hidden rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm leading-relaxed focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            @input="autoResizeNotes"
            ref="notesFieldRef"
          />
        </div>
      </section>

    <Teleport to="body">
      <transition name="fade">
        <div
          v-if="showInstallmentsManager"
          class="fixed inset-0 z-[1100] flex items-center justify-center bg-slate-950/80 px-4 py-6 backdrop-blur"
          @keydown.esc.prevent.stop="closeInstallmentsManager"
          @click.self="closeInstallmentsManager"
        >
          <div
            class="relative w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40"
          >
            <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
              <div>
                <h2 class="text-lg font-semibold text-white">Configurar parcelas</h2>
                <p class="text-xs text-slate-400">
                  Ajuste vencimentos, valores e status das parcelas deste lançamento.
                </p>
              </div>
              <button
                type="button"
                class="rounded-md p-2 text-slate-400 transition hover:text-white"
                @click="closeInstallmentsManager"
              >
                <span class="sr-only">Fechar</span>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </header>
            <div class="max-h-[75vh] overflow-y-auto px-6 py-5 space-y-4">
              <div class="flex items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-3 text-xs text-slate-300">
                  <span>Parcelas: {{ installmentsCount }}</span>
                  <span>Pendentes: {{ pendingInstallmentsCount }}</span>
                  <span>Total: {{ formatCurrencyDisplay(installmentsTotalAmount) }}</span>
                </div>
                <div class="flex gap-2">
                  <button
                    type="button"
                    class="rounded border border-slate-700 px-3 py-1 text-xs text-slate-200 hover:bg-slate-800"
                    @click="addInstallment"
                  >
                    Adicionar parcela
                  </button>
                  <button
                    type="button"
                    class="rounded border border-indigo-500 px-3 py-1 text-xs text-indigo-200 hover:bg-indigo-600/20"
                    @click="generateEqualInstallments"
                    :disabled="installments.length === 0"
                  >
                    Gerar parcelas iguais
                  </button>
                </div>
              </div>

              <div class="overflow-x-auto rounded-lg border border-slate-800">
                <table class="min-w-full divide-y divide-slate-800 text-sm text-slate-100">
                  <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                      <th class="px-3 py-2 text-left">#</th>
                      <th class="px-3 py-2 text-left">Movimento</th>
                      <th class="px-3 py-2 text-left">Vencimento</th>
                      <th class="px-3 py-2 text-left">Valor</th>
                      <th class="px-3 py-2 text-left">Status</th>
                      <th class="px-3 py-2 text-right">Ações</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-800">
                    <tr v-for="(installment, index) in installments" :key="index">
                      <td class="px-3 py-2 text-slate-200">{{ installment.numero_parcela }}</td>
                      <td class="px-3 py-2">
                        <DatePicker
                          v-model="installment.movement_date"
                          placeholder="dd/mm/aaaa"
                          :disabled="!canSubmit"
                          appearance="dark"
                        />
                      </td>
                      <td class="px-3 py-2">
                        <DatePicker
                          v-model="installment.due_date"
                          placeholder="dd/mm/aaaa"
                          :disabled="!canSubmit"
                          appearance="dark"
                        />
                      </td>
                      <td class="px-3 py-2">
                        <input
                          v-model="installment.valor_total"
                          type="number"
                          step="0.01"
                          min="0"
                          :disabled="!canSubmit"
                          class="w-28 rounded border border-slate-700 bg-slate-900 px-2 py-1 text-sm text-white focus:border-indigo-500 focus:outline-none"
                        />
                      </td>
                      <td class="px-3 py-2">
                        <select
                          v-model="installment.status"
                          :disabled="!canSubmit"
                          class="rounded border border-slate-700 bg-slate-900 px-2 py-1 text-sm text-white focus:border-indigo-500 focus:outline-none"
                        >
                          <option
                            v-for="option in installmentStatusOptions"
                            :key="option.value"
                            :value="option.value"
                          >
                            {{ option.label }}
                          </option>
                        </select>
                      </td>
                      <td class="px-3 py-2 text-right">
                        <button
                          type="button"
                          class="rounded border border-rose-500/60 px-2 py-1 text-xs text-rose-200 hover:bg-rose-500/10"
                          @click="removeInstallment(index)"
                          :disabled="!canSubmit"
                        >
                          Remover
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <footer class="flex items-center justify-end gap-2 border-t border-slate-800 px-6 py-4">
              <button
                type="button"
                class="rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-200 transition hover:bg-slate-800"
                @click="closeInstallmentsManager"
              >
                Fechar
              </button>
            </footer>
          </div>
        </div>
      </transition>
    </Teleport>

      <section
        v-if="showInstallmentsSection"
        :class="panelClasses"
      >
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-sm font-semibold" :class="isDarkAppearance ? 'text-slate-200' : 'text-slate-700'">
            Parcelas
          </h2>
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded border px-3 py-1 text-xs font-medium transition disabled:opacity-60"
              :class="
                isDarkAppearance
                  ? 'border-slate-700 text-slate-200 hover:bg-slate-800'
                  : 'border-slate-300 text-slate-600 hover:bg-slate-100'
              "
              @click="openInstallmentsManager"
              :disabled="!canSubmit"
            >
              <svg
                class="h-3.5 w-3.5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" />
              </svg>
              Configurar parcelas
            </button>
            <button
              type="button"
              class="rounded border px-3 py-1 text-xs font-medium transition disabled:opacity-60"
              :class="
                isDarkAppearance
                  ? 'border-slate-700 text-slate-200 hover:bg-slate-800'
                  : 'border-slate-300 text-slate-600 hover:bg-slate-100'
              "
              @click="addInstallment"
              :disabled="!canSubmit"
            >
              Adicionar parcela
            </button>
            <button
              type="button"
              class="rounded border px-3 py-1 text-xs font-medium transition disabled:opacity-60"
              :class="
                isDarkAppearance
                  ? 'border-indigo-500 text-indigo-200 hover:bg-indigo-600/20'
                  : 'border-indigo-500 text-indigo-600 hover:bg-indigo-50'
              "
              @click="generateEqualInstallments"
              :disabled="!canSubmit || installments.length === 0"
            >
              Gerar parcelas iguais
            </button>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table
            class="min-w-full text-sm"
            :class="isDarkAppearance ? 'divide-y divide-slate-800' : 'divide-y divide-slate-200'"
          >
            <thead
              :class="[
                'text-xs uppercase',
                isDarkAppearance ? 'bg-slate-900 text-slate-400' : 'bg-slate-100 text-slate-500'
              ]"
            >
              <tr>
                <th class="px-3 py-2 text-left">#</th>
                <th class="px-3 py-2 text-left">Movimento</th>
                <th class="px-3 py-2 text-left">Vencimento</th>
                <th class="px-3 py-2 text-left">Valor</th>
                <th class="px-3 py-2 text-left">Status</th>
                <th class="px-3 py-2 text-right">Ações</th>
              </tr>
            </thead>
            <tbody :class="isDarkAppearance ? 'divide-y divide-slate-800' : 'divide-y divide-slate-200'">
              <tr v-for="(installment, index) in installments" :key="index">
                <td class="px-3 py-2" :class="isDarkAppearance ? 'text-slate-200' : 'text-slate-700'">
                  {{ installment.numero_parcela }}
                </td>
                <td class="px-3 py-2">
                  <DatePicker
                    v-model="installment.movement_date"
                    placeholder="dd/mm/aaaa"
                    :disabled="!canSubmit"
                    :appearance="isDarkAppearance ? 'dark' : 'light'"
                  />
                </td>
                <td class="px-3 py-2">
                  <DatePicker
                    v-model="installment.due_date"
                    placeholder="dd/mm/aaaa"
                    :disabled="!canSubmit"
                    :appearance="isDarkAppearance ? 'dark' : 'light'"
                  />
                </td>
                <td class="px-3 py-2">
                  <input
                    v-model="installment.valor_total"
                    type="number"
                    step="0.01"
                    min="0"
                    :disabled="!canSubmit"
                    class="w-28 rounded px-2 py-1 text-sm focus:border-indigo-500 focus:outline-none"
                    :class="isDarkAppearance ? 'border border-slate-700 bg-slate-900 text-slate-100' : 'border border-slate-300'"
                  />
                </td>
                <td class="px-3 py-2">
                  <select
                    v-model="installment.status"
                    :disabled="!canSubmit"
                    class="rounded px-2 py-1 text-sm focus:border-indigo-500 focus:outline-none"
                    :class="isDarkAppearance ? 'border border-slate-700 bg-slate-900 text-slate-100' : 'border border-slate-300'"
                  >
                    <option
                      v-for="option in installmentStatusOptions"
                      :key="option.value"
                      :value="option.value"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </td>
                <td class="px-3 py-2 text-right">
                  <button
                    type="button"
                    :class="
                      isDarkAppearance
                        ? 'rounded border border-rose-500/60 px-2 py-1 text-xs text-rose-200 hover:bg-rose-600/20'
                        : 'rounded border border-rose-300 px-2 py-1 text-xs text-rose-600 hover:bg-rose-50'
                    "
                    @click="removeInstallment(index)"
                    :disabled="!canSubmit"
                  >
                    Remover
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section
        v-if="showAllocationsSection"
        :class="panelClasses"
      >
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-sm font-semibold" :class="isDarkAppearance ? 'text-slate-200' : 'text-slate-700'">
            Rateios por centro de custo
          </h2>
          <button
            type="button"
            class="rounded border px-3 py-1 text-xs font-medium transition disabled:opacity-60"
            :class="
              isDarkAppearance
                ? 'border-slate-700 text-slate-200 hover:bg-slate-800'
                : 'border-slate-300 text-slate-600 hover:bg-slate-100'
            "
            @click="addAllocation"
            :disabled="!canSubmit"
          >
            Adicionar rateio
          </button>
        </div>
        <p
          class="mb-3 text-xs"
          :class="isDarkAppearance ? 'text-slate-400' : 'text-slate-500'"
        >
          Informe rateios percentual ou valores por centro de custo/imóvel (campos opcionais).
        </p>
        <div
          v-if="allocations.length === 0"
          class="rounded border px-3 py-4 text-xs"
          :class="isDarkAppearance ? 'border-slate-800 bg-slate-950/30 text-slate-400' : 'border-slate-200 bg-slate-50 text-slate-500'"
        >
          Nenhum rateio adicionado.
        </div>
        <div v-else class="space-y-3">
          <article
            v-for="(allocation, index) in allocations"
            :key="index"
            class="rounded border px-3 py-3"
            :class="isDarkAppearance ? 'border-slate-800 bg-slate-950/30' : 'border-slate-200 bg-white shadow-sm'"
          >
            <div class="grid gap-3 md:grid-cols-4">
              <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold" :class="isDarkAppearance ? 'text-slate-300' : 'text-slate-600'">
                  Centro de custo *
                </label>
                <select
                  v-model="allocation.cost_center_id"
                  :disabled="!canSubmit"
                  class="rounded px-2 py-1 text-sm focus:border-indigo-500 focus:outline-none"
                  :class="isDarkAppearance ? 'border border-slate-700 bg-slate-900 text-slate-100' : 'border border-slate-300'"
                >
                  <option :value="null">Selecione</option>
                  <option v-for="center in costCenterOptions" :key="center.id" :value="center.id">
                    {{ center.nome }}
                  </option>
                </select>
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold" :class="isDarkAppearance ? 'text-slate-300' : 'text-slate-600'">
                  Imóvel
                </label>
                <select
                  v-model="allocation.property_id"
                  :disabled="!canSubmit"
                  class="rounded px-2 py-1 text-sm focus:border-indigo-500 focus:outline-none"
                  :class="isDarkAppearance ? 'border border-slate-700 bg-slate-900 text-slate-100' : 'border border-slate-300'"
                >
                  <option :value="null">Não vinculado</option>
                  <option
                    v-for="property in propertyOptions"
                    :key="property.id"
                    :value="property.id"
                  >
                    {{ property.titulo ?? property.codigo_interno ?? `Imóvel ${property.id}` }}
                  </option>
                </select>
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold" :class="isDarkAppearance ? 'text-slate-300' : 'text-slate-600'">
                  Percentual (%)
                </label>
                <input
                  v-model="allocation.percentage"
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  :disabled="!canSubmit"
                  class="rounded px-2 py-1 text-sm focus:border-indigo-500 focus:outline-none"
                  :class="isDarkAppearance ? 'border border-slate-700 bg-slate-900 text-slate-100' : 'border border-slate-300'"
                  placeholder="0,00"
                />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold" :class="isDarkAppearance ? 'text-slate-300' : 'text-slate-600'">
                  Valor
                </label>
                <input
                  v-model="allocation.amount"
                  type="number"
                  step="0.01"
                  min="0"
                  :disabled="!canSubmit"
                  class="rounded px-2 py-1 text-sm focus:border-indigo-500 focus:outline-none"
                  :class="isDarkAppearance ? 'border border-slate-700 bg-slate-900 text-slate-100' : 'border border-slate-300'"
                  placeholder="0,00"
                />
              </div>
            </div>
            <div class="mt-3 flex justify-end">
              <button
                type="button"
                :class="
                  isDarkAppearance
                    ? 'rounded border border-rose-500/60 px-2 py-1 text-xs text-rose-200 hover:bg-rose-600/20'
                    : 'rounded border border-rose-300 px-2 py-1 text-xs text-rose-600 hover:bg-rose-50'
                "
                @click="removeAllocation(index)"
                :disabled="!canSubmit"
              >
                Remover rateio
              </button>
            </div>
          </article>
        </div>
      </section>

      <section v-if="showAttachmentsSection" :class="panelClasses">
        <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-sm font-semibold" :class="isDarkAppearance ? 'text-slate-200' : 'text-slate-700'">Anexos</h2>
            <p class="text-xs" :class="isDarkAppearance ? 'text-slate-400' : 'text-slate-500'">
              Adicione comprovantes ou documentos relacionados ao lançamento ou às parcelas.
            </p>
          </div>
          <div v-if="attachmentsLoading" class="text-xs" :class="isDarkAppearance ? 'text-slate-400' : 'text-slate-500'">Carregando...</div>
        </header>

        <template v-if="entryId">
        <div
          v-if="props.permissions.update"
          class="mb-4 flex flex-col gap-3 rounded-lg border"
          :class="isDarkAppearance ? 'border-slate-800 bg-slate-950/40 p-3' : 'border-slate-200 bg-slate-50 p-3'"
        >
          <div class="grid gap-3 lg:grid-cols-2">
            <div class="flex flex-col gap-1 text-sm" :class="isDarkAppearance ? 'text-slate-200' : 'text-slate-700'">
              <label class="text-xs font-semibold" :class="isDarkAppearance ? 'text-slate-300' : 'text-slate-600'">Arquivo</label>
              <input
                ref="attachmentInputRef"
                type="file"
                class="rounded border px-3 py-2 text-sm"
                :class="isDarkAppearance ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-slate-300 bg-white text-slate-700'"
                @change="handleAttachmentFileChange"
              />
              <span class="text-[11px]" :class="isDarkAppearance ? 'text-slate-500' : 'text-slate-500'">Tamanho máximo 10 MB.</span>
            </div>
            <div class="flex flex-col gap-1 text-sm" :class="isDarkAppearance ? 'text-slate-200' : 'text-slate-700'">
              <label class="text-xs font-semibold" :class="isDarkAppearance ? 'text-slate-300' : 'text-slate-600'">Parcela vinculada</label>
              <select
                v-model.number="attachmentUploadState.installmentId"
                class="rounded border px-3 py-2 text-sm"
                :class="isDarkAppearance ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-slate-300 bg-white text-slate-700'"
              >
                <option :value="null">Lançamento completo</option>
                <option
                  v-for="installment in persistedInstallments"
                  :key="installment.id"
                  :value="installment.id"
                >
                  Parcela {{ installment.numero_parcela ?? installment.id }} · vencimento {{ installment.due_date ?? '-' }}
                </option>
              </select>
            </div>
          </div>
          <div class="flex justify-end">
            <button
              type="button"
              class="rounded-lg px-4 py-2 text-sm font-semibold shadow"
              :class="attachmentUploadState.loading ? 'bg-slate-500 text-white opacity-60' : 'bg-indigo-600 text-white hover:bg-indigo-500'"
              :disabled="attachmentUploadState.loading"
              @click="uploadAttachment"
            >
              Salvar anexo
            </button>
          </div>
        </div>

        <div v-if="!attachments.length && !attachmentsLoading" class="text-xs" :class="isDarkAppearance ? 'text-slate-500' : 'text-slate-500'">
          Nenhum anexo cadastrado.
        </div>
        <ul v-else class="space-y-3">
          <li
            v-for="attachment in attachments"
            :key="attachment.id"
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border px-3 py-2"
            :class="isDarkAppearance ? 'border-slate-800 bg-slate-950/50' : 'border-slate-200 bg-slate-50'"
          >
            <div class="flex flex-col text-sm" :class="isDarkAppearance ? 'text-slate-200' : 'text-slate-700'">
              <span class="font-semibold" :class="isDarkAppearance ? 'text-white' : 'text-slate-800'">{{ attachment.file_name }}</span>
              <span class="text-xs" :class="isDarkAppearance ? 'text-slate-400' : 'text-slate-500'">
                {{ formatBytes(attachment.file_size ?? 0) }} · {{ attachment.mime_type }} ·
                {{ attachment.uploaded_at ? new Date(attachment.uploaded_at).toLocaleString('pt-BR') : '-' }}
              </span>
            </div>
            <div class="flex items-center gap-2 text-xs">
              <button
                type="button"
                class="rounded border px-3 py-1"
                :class="isDarkAppearance ? 'border-slate-600 text-slate-200 hover:bg-slate-800' : 'border-slate-300 text-slate-700 hover:bg-slate-100'"
                @click="downloadAttachment(attachment)"
              >
                Baixar
              </button>
              <button
                v-if="props.permissions.update"
                type="button"
                class="rounded border px-3 py-1"
                :class="isDarkAppearance ? 'border-rose-600 text-rose-200 hover:bg-rose-600/20' : 'border-rose-500 text-rose-600 hover:bg-rose-50'"
                @click="removeAttachment(attachment)"
              >
                Remover
              </button>
            </div>
          </li>
        </ul>
        </template>
        <div
          v-else
          class="rounded border px-3 py-3 text-xs"
          :class="isDarkAppearance ? 'border-slate-800 bg-slate-950/40 text-slate-400' : 'border-slate-200 bg-slate-50 text-slate-600'"
        >
          Salve o lançamento para adicionar anexos.
        </div>
      </section>

      <section v-if="showReceiptsSection" :class="panelClasses">
        <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-sm font-semibold" :class="isDarkAppearance ? 'text-slate-200' : 'text-slate-700'">Recibos</h2>
            <p class="text-xs" :class="isDarkAppearance ? 'text-slate-400' : 'text-slate-500'">
              Gere recibos em PDF para lançamentos quitados ou parcelas específicas.
            </p>
          </div>
          <div class="flex items-center gap-2" v-if="props.permissions.reconcile && entryId">
            <select
              v-model.number="selectedReceiptInstallmentId"
              class="rounded border px-3 py-2 text-sm"
              :class="isDarkAppearance ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-slate-300 bg-white text-slate-700'"
            >
              <option :value="null">Lançamento inteiro</option>
              <option
                v-for="installment in persistedInstallments"
                :key="installment.id"
                :value="installment.id"
              >
                Parcela {{ installment.numero_parcela ?? installment.id }}
              </option>
            </select>
            <button
              type="button"
              class="rounded-lg px-4 py-2 text-sm font-semibold shadow"
              :class="generatingReceipt ? 'bg-slate-500 text-white opacity-60' : 'bg-emerald-600 text-white hover:bg-emerald-500'"
              :disabled="generatingReceipt || !entryId"
              @click="generateReceipt"
            >
              Gerar recibo
            </button>
          </div>
        </header>

        <div
          v-if="!entryId"
          class="rounded border px-3 py-3 text-xs"
          :class="isDarkAppearance ? 'border-slate-800 bg-slate-950/40 text-slate-400' : 'border-slate-200 bg-slate-50 text-slate-600'"
        >
          Salve o lançamento para gerar recibos.
        </div>
        <template v-else>
        <div v-if="receiptsLoading" class="text-xs" :class="isDarkAppearance ? 'text-slate-400' : 'text-slate-500'">Carregando recibos...</div>
        <div v-else-if="!receipts.length" class="text-xs" :class="isDarkAppearance ? 'text-slate-500' : 'text-slate-500'">
          Nenhum recibo disponível.
        </div>
        <ul v-else class="space-y-3">
          <li
            v-for="receipt in receipts"
            :key="receipt.id"
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border px-3 py-2"
            :class="isDarkAppearance ? 'border-slate-800 bg-slate-950/50' : 'border-slate-200 bg-slate-50'"
          >
            <div class="flex flex-col text-sm" :class="isDarkAppearance ? 'text-slate-200' : 'text-slate-700'">
              <span class="font-semibold" :class="isDarkAppearance ? 'text-white' : 'text-slate-800'">{{ receipt.number }}</span>
              <span class="text-xs" :class="isDarkAppearance ? 'text-slate-400' : 'text-slate-500'">
                Emitido em {{ receipt.issue_date ? new Date(receipt.issue_date).toLocaleDateString('pt-BR') : '-' }} · Status: {{ receipt.status }}
              </span>
            </div>
            <button
              type="button"
              class="rounded border px-3 py-1 text-xs"
              :class="receipt.download_url ? (isDarkAppearance ? 'border-slate-600 text-slate-200 hover:bg-slate-800' : 'border-slate-300 text-slate-700 hover:bg-slate-100') : 'border-slate-500 text-slate-500 cursor-not-allowed'"
              :disabled="!receipt.download_url"
              @click="downloadReceipt(receipt)"
            >
              {{ receipt.download_url ? 'Baixar PDF' : 'Processando' }}
            </button>
          </li>
        </ul>
        </template>
      </section>

      <footer class="flex flex-wrap items-center gap-3">
        <button
          v-if="!isCreate && props.permissions.delete"
          type="button"
          class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm transition"
          :class="
            isDarkAppearance
              ? 'border-rose-500/60 text-rose-200 hover:bg-rose-600/20'
              : 'border-rose-500/70 text-rose-600 hover:bg-rose-50'
          "
          :disabled="deleting || submitting"
          @click="handleDelete"
        >
          <svg
            class="h-4 w-4"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h12M9 6v12m6-12v12M4 6l1.5 14A2 2 0 0 0 7.48 22h9.04a2 2 0 0 0 1.98-1.999L20 6" />
          </svg>
          {{ deleting ? 'Excluindo...' : 'Excluir' }}
        </button>
        <div class="ml-auto flex items-center gap-3">
          <button
            v-if="isModalContext"
            type="button"
            class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-slate-200 transition hover:border-white/25 hover:bg-white/10 disabled:opacity-60"
            @click="handleCancel"
            :disabled="submitting || deleting"
          >
            Cancelar
          </button>
          <Link
            v-else
            href="/financeiro"
            class="inline-flex items-center gap-2 rounded-full border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:border-indigo-300 hover:bg-indigo-50"
          >
            Cancelar
          </Link>
          <button
            type="submit"
            class="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-indigo-500 via-purple-500 to-sky-500 px-5 py-2 text-sm font-semibold text-white shadow-[0_18px_30px_-18px_rgba(99,102,241,0.9)] transition hover:shadow-[0_24px_45px_-20px_rgba(99,102,241,0.95)] disabled:opacity-60 disabled:shadow-none"
            :disabled="submitting || deleting || !canSubmit"
          >
            <svg
              v-if="submitting"
              class="h-4 w-4 animate-spin"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path d="M12 4v2" />
              <path d="M18.364 5.636l-1.414 1.414" />
              <path d="M20 12h-2" />
              <path d="M18.364 18.364l-1.414-1.414" />
              <path d="M12 20v-2" />
              <path d="M5.636 18.364l1.414-1.414" />
              <path d="M4 12h2" />
              <path d="M5.636 5.636l1.414 1.414" />
            </svg>
            {{ isCreate ? 'Salvar lançamento' : 'Atualizar lançamento' }}
          </button>
        </div>
      </footer>
    </form>
    <PessoaFormModal
      :show="showPersonModal"
      :roles="personModalRoles"
      :appearance="isDarkAppearance ? 'dark' : 'light'"
      @close="handlePersonModalClose"
      @created="handlePersonModalCreated"
    />
  </section>
</template>

<style scoped>
.transaction-form.appearance-dark :deep(.bg-white),
.transaction-form.appearance-dark :deep(.bg-white\/60),
.transaction-form.appearance-dark :deep(.bg-slate-50) {
  background-color: rgba(15, 23, 42, 0.9);
  color: #e2e8f0;
}

.transaction-form.appearance-dark :deep(.border-slate-200),
.transaction-form.appearance-dark :deep(.border-slate-300) {
  border-color: rgba(71, 85, 105, 0.6);
}

.transaction-form.appearance-dark :deep(.text-slate-900),
.transaction-form.appearance-dark :deep(.text-slate-800),
.transaction-form.appearance-dark :deep(.text-slate-700) {
  color: #f8fafc;
}

.transaction-form.appearance-dark :deep(.text-slate-600) {
  color: #e2e8f0;
}

.transaction-form.appearance-dark :deep(.text-slate-500) {
  color: #cbd5f5;
}

.transaction-form.appearance-dark :deep(input),
.transaction-form.appearance-dark :deep(select),
.transaction-form.appearance-dark :deep(textarea) {
  background-color: rgba(15, 23, 42, 0.55);
  border-color: rgba(148, 163, 184, 0.25);
  color: #f8fafc;
  box-shadow: 0 12px 32px -28px rgba(15, 23, 42, 0.9);
}

.transaction-form.appearance-dark :deep(input::placeholder),
.transaction-form.appearance-dark :deep(textarea::placeholder) {
  color: rgba(148, 163, 184, 0.65);
}

.transaction-form.appearance-dark :deep(.hover\:bg-slate-100:hover),
.transaction-form.appearance-dark :deep(.hover\:bg-rose-50:hover) {
  background-color: rgba(30, 41, 59, 0.75);
  color: #f8fafc;
}

.transaction-form.appearance-dark :deep(.rounded.border-rose-300) {
  border-color: rgba(248, 113, 113, 0.6);
  color: #fda4af;
}

.transaction-form.appearance-dark :deep(.border-dashed.border-slate-300) {
  border-color: rgba(71, 85, 105, 0.4);
}

.transaction-form.appearance-dark :deep(.border.border-slate-200.bg-white) {
  background-color: rgba(15, 23, 42, 0.85);
  border-color: rgba(71, 85, 105, 0.5);
}

.transaction-form.appearance-dark :deep(.text-xs.text-rose-600) {
  color: #fda4af;
}

.transaction-form.context-modal :deep(input:not([type='radio'])),
.transaction-form.context-modal :deep(select),
.transaction-form.context-modal :deep(textarea),
.transaction-form.context-modal :deep(.flatpickr-input) {
  padding-top: 0.375rem;
  padding-bottom: 0.375rem;
  border-radius: 0.5rem;
}
</style>
