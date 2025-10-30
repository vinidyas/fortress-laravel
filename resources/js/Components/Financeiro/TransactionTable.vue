<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import axios from '@/bootstrap';
import { useToast } from '@/composables/useToast';
import TransactionStatusBadge from '@/Components/Financeiro/TransactionStatusBadge.vue';
import {
  resolveStatusCategory,
  resolveStatusLabel,
  isTransactionType,
  type TransactionStatusCategory,
  type TransactionStatusCode,
  type TransactionType,
} from '@/utils/financeiro/status';

const STATUS_CATEGORIES: ReadonlyArray<TransactionStatusCategory> = [
  'open',
  'overdue',
  'settled',
  'cancelled',
];

const isStatusCategory = (value: unknown): value is TransactionStatusCategory =>
  typeof value === 'string' && STATUS_CATEGORIES.includes(value as TransactionStatusCategory);

interface TransactionRow {
  id: number;
  movement_date?: string | null;
  data_ocorrencia?: string | null;
  data_ocorrencia_formatada?: string | null;
  descricao?: string | null;
  description?: string | null;
  description_custom?: string | null;
  due_date?: string | null;
  tipo: TransactionType | 'credito' | 'debito';
  type?: TransactionType | string | null;
  valor: number | string;
  valor_formatado?: string;
  status: TransactionStatusCode | string;
  status_code?: TransactionStatusCode | string | null;
  status_label?: string | null;
  status_category?: TransactionStatusCategory | string | null;
  account?: { id: number; nome: string } | null;
  person?: { id: number; nome: string } | null;
  property?: { id: number; nome?: string | null } | null;
  property_label?: string | null;
  propertyLabel?: string | null;
  property_label_mcc?: string | null;
  propertyLabelMcc?: string | null;
  cost_center?: { id: number; nome: string; codigo?: string | null } | null;
  notes?: string | null;
  clone_of_id?: number | null;
  origin?: string | null;
}

const props = defineProps<{
  items: TransactionRow[];
  links: Array<{ url: string | null; label: string; active: boolean }>;
  can: { create: boolean; reconcile: boolean; export: boolean; delete: boolean };
  filters: Record<string, unknown>;
}>();

const emit = defineEmits<{
  (e: 'create'): void;
  (e: 'view', item: TransactionRow): void;
  (e: 'clone', item: TransactionRow): void;
}>();

export type { TransactionRow };

const hasItems = computed(() => props.items.length > 0);
const cloningId = ref<number | null>(null);
const payingId = ref<number | null>(null);
const cancellingId = ref<number | null>(null);
const receiptingId = ref<number | null>(null);
const deletingId = ref<number | null>(null);
const toast = useToast();

const selectedRowId = ref<number | null>(null);

const scrollRowIntoView = (id: number) => {
  const row = document.querySelector<HTMLElement>(`[data-transaction-row="${id}"]`);
  if (row) {
    row.scrollIntoView({
      block: 'nearest',
      behavior: 'auto',
    });
  }
};

const selectRow = (item: TransactionRow, options?: { scrollIntoView?: boolean }) => {
  if (!item) {
    return;
  }

  selectedRowId.value = item.id;

  if (options?.scrollIntoView) {
    requestAnimationFrame(() => {
      scrollRowIntoView(item.id);
    });
  }
};

type MenuAnchor = 'button' | { x: number; y: number };

const menuState = ref<{ itemId: number | null; anchor: MenuAnchor }>({
  itemId: null,
  anchor: 'button',
});

const openMenu = (item: TransactionRow, anchor: MenuAnchor) => {
  menuState.value = {
    itemId: item.id,
    anchor,
  };
};

const toggleActionsMenu = (item: TransactionRow) => {
  pressedArrows.clear();
  stopNavigation();

  const isSameButtonMenu =
    menuState.value.itemId === item.id && menuState.value.anchor === 'button';

  if (isSameButtonMenu) {
    closeActionsMenu();
    return;
  }

  selectRow(item);
  openMenu(item, 'button');
};

const closeActionsMenu = () => {
  menuState.value = {
    itemId: null,
    anchor: 'button',
  };
};

const isMenuOpenFor = (item: TransactionRow): boolean =>
  menuState.value.itemId === item.id;

const isMenuAnchoredToButton = (item: TransactionRow): boolean =>
  isMenuOpenFor(item) && menuState.value.anchor === 'button';

const currentMenuItem = computed(() =>
  menuState.value.itemId === null
    ? null
    : props.items.find((candidate) => candidate.id === menuState.value.itemId) ?? null,
);

const contextMenuCoordinates = computed(() =>
  typeof menuState.value.anchor === 'object' ? menuState.value.anchor : null,
);

const isContextMenuVisible = computed(
  () => contextMenuCoordinates.value !== null && currentMenuItem.value !== null,
);

const contextMenuStyle = computed(() => {
  const coords = contextMenuCoordinates.value;
  if (!coords) {
    return {};
  }

  return {
    left: `${coords.x}px`,
    top: `${coords.y}px`,
  };
});

const MENU_WIDTH = 176;
const MENU_HEIGHT = 220;
const MENU_MARGIN = 8;

const clampMenuPosition = (x: number, y: number): { x: number; y: number } => {
  const viewportWidth = window.innerWidth;
  const viewportHeight = window.innerHeight;

  const clampedX = Math.min(
    Math.max(MENU_MARGIN, x),
    Math.max(MENU_MARGIN, viewportWidth - MENU_WIDTH - MENU_MARGIN),
  );
  const clampedY = Math.min(
    Math.max(MENU_MARGIN, y),
    Math.max(MENU_MARGIN, viewportHeight - MENU_HEIGHT - MENU_MARGIN),
  );

  return { x: clampedX, y: clampedY };
};

const handleGlobalClick = (event: MouseEvent) => {
  if (menuState.value.itemId === null) {
    return;
  }

  const target = event.target as HTMLElement | null;
  if (!target) {
    return;
  }

  const menuSelector = `[data-actions-menu="${menuState.value.itemId}"]`;
  const triggerSelector = `[data-actions-trigger="${menuState.value.itemId}"]`;

  if (target.closest(menuSelector) || target.closest(triggerSelector)) {
    return;
  }

  closeActionsMenu();
};

const handleGlobalContextMenu = (event: MouseEvent) => {
  if (menuState.value.itemId === null) {
    return;
  }

  const target = event.target as HTMLElement | null;
  if (!target) {
    closeActionsMenu();
    return;
  }

  const menuSelector = `[data-actions-menu="${menuState.value.itemId}"]`;
  const triggerSelector = `[data-actions-trigger="${menuState.value.itemId}"]`;
  const rowSelector = `[data-transaction-row="${menuState.value.itemId}"]`;

  if (
    target.closest(menuSelector) ||
    target.closest(triggerSelector) ||
    target.closest(rowSelector)
  ) {
    return;
  }

  closeActionsMenu();
};

const interactiveTagNames = new Set(['INPUT', 'TEXTAREA', 'SELECT']);

let navigationInterval: ReturnType<typeof setInterval> | null = null;
let navigationDirection: 'up' | 'down' | null = null;
const pressedArrows = new Set<'up' | 'down'>();

const handleWindowBlur = () => {
  pressedArrows.clear();
  stopNavigation();
};

const shouldIgnoreKeyEvent = (event: KeyboardEvent): boolean => {
  const target = event.target as HTMLElement | null;
  if (!target) {
    return false;
  }

  if (interactiveTagNames.has(target.tagName)) {
    return true;
  }

  if (target.isContentEditable) {
    return true;
  }

  return false;
};

const moveSelection = (offset: number) => {
  if (!props.items.length) {
    return;
  }

  if (selectedRowId.value === null) {
    const initialItem =
      offset > 0
        ? props.items[0] ?? null
        : props.items[props.items.length - 1] ?? null;
    if (initialItem) {
      closeActionsMenu();
      selectRow(initialItem, { scrollIntoView: true });
    }
    return;
  }

  const currentIndex = props.items.findIndex((item) => item.id === selectedRowId.value);
  const fallbackIndex = offset > 0 ? 0 : props.items.length - 1;

  let nextIndex = currentIndex;
  if (currentIndex === -1) {
    nextIndex = fallbackIndex;
  } else {
    nextIndex = currentIndex + offset;
    if (nextIndex < 0) {
      nextIndex = 0;
    } else if (nextIndex >= props.items.length) {
      nextIndex = props.items.length - 1;
    }
  }

  if (nextIndex === currentIndex) {
    return;
  }

  const nextItem = props.items[nextIndex];
  if (nextItem) {
    closeActionsMenu();
    selectRow(nextItem, { scrollIntoView: true });
  }
};

function startNavigation(direction: 'up' | 'down'): void {
  if (navigationDirection === direction && navigationInterval) {
    return;
  }

  const offset = direction === 'down' ? 1 : -1;

  if (navigationInterval !== null) {
    clearInterval(navigationInterval);
  }

  navigationDirection = direction;
  moveSelection(offset);

  navigationInterval = setInterval(() => {
    moveSelection(offset);
  }, 90);
}

function stopNavigation(): void {
  if (navigationInterval !== null) {
    clearInterval(navigationInterval);
    navigationInterval = null;
  }

  navigationDirection = null;
}

const handleKeyNavigation = (event: KeyboardEvent) => {
  if (!props.items.length) {
    return;
  }

  if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey) {
    return;
  }

  if (shouldIgnoreKeyEvent(event)) {
    return;
  }

  if (event.key === 'Escape') {
    if (menuState.value.itemId !== null) {
      event.preventDefault();
      closeActionsMenu();
    }
    return;
  }

  if (event.key === 'ArrowDown') {
    event.preventDefault();
    pressedArrows.add('down');
    startNavigation('down');
    return;
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault();
    pressedArrows.add('up');
    startNavigation('up');
    return;
  }

  if (event.key === 'Enter') {
    if (selectedRowId.value === null) {
      return;
    }

    const selectedItem = props.items.find((item) => item.id === selectedRowId.value);
    if (!selectedItem) {
      return;
    }

    event.preventDefault();
    stopNavigation();
    pressedArrows.clear();
    handleOpenDetails(selectedItem);
  }
};

const handleKeyRelease = (event: KeyboardEvent) => {
  if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
    return;
  }

  const releasedDirection = event.key === 'ArrowDown' ? 'down' : 'up';
  pressedArrows.delete(releasedDirection);

  if (navigationDirection !== releasedDirection) {
    return;
  }

  if (pressedArrows.size === 0) {
    stopNavigation();
    return;
  }

  const arrowArray = Array.from(pressedArrows);
  const nextDirection = arrowArray.length ? arrowArray[arrowArray.length - 1] : null;
  if (!nextDirection) {
    stopNavigation();
    return;
  }

  startNavigation(nextDirection);
};

onMounted(() => {
  document.addEventListener('click', handleGlobalClick);
  document.addEventListener('keydown', handleKeyNavigation);
  document.addEventListener('keyup', handleKeyRelease);
  document.addEventListener('contextmenu', handleGlobalContextMenu);
  window.addEventListener('blur', handleWindowBlur);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleGlobalClick);
  document.removeEventListener('keydown', handleKeyNavigation);
  document.removeEventListener('keyup', handleKeyRelease);
  document.removeEventListener('contextmenu', handleGlobalContextMenu);
  window.removeEventListener('blur', handleWindowBlur);
  stopNavigation();
  pressedArrows.clear();
});

watch(
  () => props.items.map((item) => item.id),
  (ids) => {
    if (ids.length === 0) {
      pressedArrows.clear();
      stopNavigation();
      closeActionsMenu();
      selectedRowId.value = null;
      return;
    }

    if (selectedRowId.value === null || !ids.includes(selectedRowId.value)) {
      pressedArrows.clear();
      stopNavigation();
      const firstItem = props.items[0];
      if (firstItem) {
        selectRow(firstItem);
      }
    }

    if (menuState.value.itemId !== null && !ids.includes(menuState.value.itemId)) {
      closeActionsMenu();
    }
  },
  { immediate: true },
);

const resolveItemType = (item: TransactionRow): TransactionType | undefined => {
  if (isTransactionType(item.tipo)) {
    return item.tipo;
  }

  if (isTransactionType(item.type)) {
    return item.type;
  }

  return undefined;
};

const resolveBadgeLabel = (item: TransactionRow): string =>
  item.status_label ?? resolveStatusLabel(item.status, resolveItemType(item));

const resolveBadgeCategory = (item: TransactionRow): TransactionStatusCategory =>
  isStatusCategory(item.status_category)
    ? item.status_category
    : resolveStatusCategory(item.status);

const rowClasses = (item: TransactionRow): string[] => {
  const classes = ['hover:bg-slate-900/50', 'transition-colors', 'duration-200', 'ease-out'];

  const isSelected = selectedRowId.value === item.id;
  const isMenuOpen = menuState.value.itemId === item.id;

  if (isSelected) {
    classes.push('bg-indigo-500/15', 'ring-1', 'ring-indigo-400/30', 'backdrop-saturate-150');
  }

  if (isMenuOpen && !isSelected) {
    classes.push('bg-slate-900/70');
  }

  return classes;
};

const handleRowClick = (item: TransactionRow) => {
  pressedArrows.clear();
  stopNavigation();
  closeActionsMenu();
  selectRow(item);
};

const handleContextMenu = (event: MouseEvent, item: TransactionRow) => {
  if (!hasRowActions(item)) {
    return;
  }

  event.preventDefault();
  event.stopPropagation();
  pressedArrows.clear();
  stopNavigation();
  selectRow(item);
  const coords = clampMenuPosition(event.clientX, event.clientY);
  openMenu(item, coords);
};

interface MenuAction {
  key: string;
  label: string;
  action: () => void;
  disabled: boolean;
  classes: string;
  loading?: boolean;
  loadingClass?: string;
}

const getMenuActions = (item: TransactionRow): MenuAction[] => {
  const actions: MenuAction[] = [];

  if (props.can.create) {
    actions.push({
      key: 'clone',
      label: 'Clonar',
      action: () => handleClone(item),
      disabled:
        cloningId.value === item.id ||
        payingId.value === item.id ||
        cancellingId.value === item.id ||
        deletingId.value === item.id,
      classes:
        'flex w-full items-center justify-between px-3 py-2 text-left text-slate-200 transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60',
      loading: cloningId.value === item.id,
      loadingClass: 'text-[10px] uppercase text-slate-500',
    });
  }

  if (props.can.reconcile && item.status !== 'pago' && item.status !== 'cancelado') {
    actions.push({
      key: 'mark-paid',
      label: 'Marcar pago',
      action: () => handleMarkPaid(item),
      disabled:
        payingId.value === item.id ||
        cloningId.value === item.id ||
        cancellingId.value === item.id ||
        deletingId.value === item.id,
      classes:
        'flex w-full items-center justify-between px-3 py-2 text-left text-emerald-200 transition hover:bg-emerald-600/10 disabled:cursor-not-allowed disabled:opacity-60',
      loading: payingId.value === item.id,
      loadingClass: 'text-[10px] uppercase text-emerald-300',
    });
  }

  if (props.can.reconcile && item.status !== 'cancelado') {
    actions.push({
      key: 'cancel',
      label: 'Cancelar lançamento',
      action: () => handleCancel(item),
      disabled:
        cancellingId.value === item.id ||
        payingId.value === item.id ||
        deletingId.value === item.id,
      classes:
        'flex w-full items-center justify-between px-3 py-2 text-left text-rose-200 transition hover:bg-rose-600/10 disabled:cursor-not-allowed disabled:opacity-60',
      loading: cancellingId.value === item.id,
      loadingClass: 'text-[10px] uppercase text-rose-300',
    });
  }

  if (item.status === 'pago') {
    actions.push({
      key: 'receipt',
      label: 'Gerar recibo',
      action: () => handleGenerateReceipt(item),
      disabled: receiptingId.value === item.id || deletingId.value === item.id,
      classes:
        'flex w-full items-center justify-between px-3 py-2 text-left text-indigo-200 transition hover:bg-indigo-600/10 disabled:cursor-not-allowed disabled:opacity-60',
      loading: receiptingId.value === item.id,
      loadingClass: 'text-[10px] uppercase text-indigo-300',
    });
  }

  if (props.can.delete) {
    actions.push({
      key: 'delete',
      label: deletingId.value === item.id ? 'Excluindo...' : 'Excluir',
      action: () => handleDelete(item),
      disabled: deletingId.value === item.id,
      classes:
        'flex w-full items-center justify-between px-3 py-2 text-left text-rose-300 transition hover:bg-rose-600/10 disabled:cursor-not-allowed disabled:opacity-60',
    });
  }

  return actions;
};

const exportUrl = () => {
  const params = new URLSearchParams();
  Object.entries(props.filters).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '') {
      return;
    }
    params.append(key, String(value));
  });
  params.append('format', 'csv');

  return `/api/financeiro/journal-entries/export?${params.toString()}`;
};

const handlePagination = (link: { url: string | null }) => {
  if (!link.url) {
    return;
  }

  router.visit(link.url, {
    preserveScroll: true,
    preserveState: true,
  });
};

const reloadPage = () => {
  router.visit(route('financeiro.index'), {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  });
};

const handleClone = (item: TransactionRow) => {
  if (!props.can.create) {
    return;
  }

  closeActionsMenu();
  cloningId.value = item.id;
  emit('clone', item);
  cloningId.value = null;
};

const handleMarkPaid = async (item: TransactionRow) => {
  if (!props.can.reconcile) {
    return;
  }

  closeActionsMenu();
  payingId.value = item.id;

  try {
    const { data } = await axios.get(`/api/financeiro/journal-entries/${item.id}`);
    const installments = data?.data?.installments ?? [];
    const pending = installments.find((installment: any) =>
      ['planejado', 'pendente'].includes(installment.status),
    );

    if (!pending) {
      toast.info('Nenhuma parcela pendente para marcar como paga.');
      payingId.value = null;
      return;
    }

    await axios.post(
      `/api/financeiro/journal-entries/${item.id}/installments/${pending.id}/pay`,
      {
        payment_date: new Date().toISOString().slice(0, 10),
      },
    );

    toast.success('Parcela marcada como paga.');
    reloadPage();
  } catch (error: any) {
    const message = error?.response?.data?.message ?? 'Não foi possível marcar o pagamento.';
    toast.error(message);
  } finally {
    payingId.value = null;
  }
};

const handleCancel = async (item: TransactionRow) => {
  if (!props.can.reconcile || item.status === 'cancelado') {
    return;
  }

  closeActionsMenu();
  const confirmed = window.confirm('Deseja cancelar este lançamento?');
  if (!confirmed) {
    return;
  }

  cancellingId.value = item.id;

  try {
    await axios.post(`/api/financeiro/journal-entries/${item.id}/cancel`);
    toast.success('Lançamento cancelado.');
    reloadPage();
  } catch (error: any) {
    const message = error?.response?.data?.message ?? 'Não foi possível cancelar o lançamento.';
    toast.error(message);
  } finally {
    cancellingId.value = null;
  }
};

const handleGenerateReceipt = async (item: TransactionRow) => {
  if (item.status !== 'pago') {
    toast.info('O lançamento precisa estar pago para gerar recibo.');
    return;
  }

  closeActionsMenu();
  receiptingId.value = item.id;

  try {
    const response = await axios.post(`/api/financeiro/journal-entries/${item.id}/generate-receipt`);
    const message = response.data?.message ?? 'Recibo gerado com sucesso.';
    toast.success(message);
  } catch (error: any) {
    const message = error?.response?.data?.message ?? 'Não foi possível gerar o recibo.';
    toast.error(message);
  } finally {
    receiptingId.value = null;
  }
};

const handleDelete = async (item: TransactionRow) => {
  if (!props.can.delete) {
    return;
  }

  closeActionsMenu();
  const confirmed = window.confirm('Deseja realmente excluir este lançamento? Esta ação não pode ser desfeita.');
  if (!confirmed) {
    return;
  }

  deletingId.value = item.id;

  try {
    await axios.delete(`/api/financeiro/journal-entries/${item.id}`);
    toast.success('Lançamento excluído.');
    reloadPage();
  } catch (error: any) {
    const message = error?.response?.data?.message ?? 'Não foi possível excluir o lançamento.';
    toast.error(message);
  } finally {
    deletingId.value = null;
  }
};

const formatDate = (value?: string | null): string => {
  if (!value) {
    return '-';
  }

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat('pt-BR', {
    timeZone: 'UTC',
  }).format(parsed);
};

const extractNoteInfo = (notes?: string | null): { property: string | null; clean: string | null } => {
  if (!notes) {
    return { property: null, clean: null };
  }

  const lines = notes.split(/\r?\n/);
  let property: string | null = null;
  const filtered: string[] = [];

  lines.forEach((line) => {
    const trimmed = line.trim();
    if (trimmed.toLowerCase().startsWith('[imóvel csv]')) {
      property = trimmed.substring(12).trim() || property;
      return;
    }

    if (trimmed !== '') {
      filtered.push(trimmed);
    }
  });

  return {
    property,
    clean: filtered.length ? filtered.join('\n') : null,
  };
};

const formatProperty = (item: TransactionRow): string => {
  const propertyName = item.property?.nome;
  if (typeof propertyName === 'string' && propertyName.trim() !== '') {
    return propertyName.trim();
  }

  const label = item.property_label ?? (item as any).propertyLabel ?? null;
  if (typeof label === 'string' && label.trim() !== '') {
    return label.trim();
  }

  return '-';
};

const formatPropertyMcc = (item: TransactionRow): string => {
  const label =
    item.property_label_mcc ??
    (item as any).propertyLabelMcc ??
    item.property_label ??
    (item as any).propertyLabel ??
    null;
  if (typeof label === 'string' && label.trim() !== '') {
    return label.trim();
  }

  const info = extractNoteInfo(item.notes);
  if (info.property) {
    return info.property;
  }

  if (item.cost_center?.nome) {
    return item.cost_center.nome;
  }

  return '-';
};

const formatCenter = (item: TransactionRow): string => {
  const center = item.cost_center;
  if (!center) {
    return '-';
  }

  if (center.codigo) {
    return `${center.codigo} — ${center.nome}`;
  }

  return center.nome ?? '-';
};

const formatNotes = (notes?: string | null): string => {
  const info = extractNoteInfo(notes);

  if (!info.clean) {
    return '-';
  }

  return info.clean.length <= 60 ? info.clean : `${info.clean.slice(0, 57)}...`;
};

const hasRowActions = (item: TransactionRow): boolean => {
  const canClone = props.can.create;
  const canMarkPaid =
    props.can.reconcile && item.status !== 'pago' && item.status !== 'cancelado';
  const canCancel = props.can.reconcile && item.status !== 'cancelado';
  const canGenerateReceipt = item.status === 'pago';
  const canDelete = props.can.delete;

  return canClone || canMarkPaid || canCancel || canGenerateReceipt || canDelete;
};

const handleOpenDetails = (item: TransactionRow) => {
  if (deletingId.value === item.id) {
    return;
  }

  pressedArrows.clear();
  stopNavigation();
  selectRow(item);
  closeActionsMenu();
  emit('view', item);
};
</script>

<template>
  <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70">
    <header
      class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 px-4 py-3 text-sm text-slate-300"
    >
      <h2 class="font-semibold text-white">Lançamentos</h2>
      <div class="flex items-center gap-2">
        <a
          v-if="props.can.export"
          class="rounded-lg border border-slate-700 px-3 py-1 text-xs text-slate-200 transition hover:bg-slate-800"
          :href="exportUrl()"
        >
          Exportar CSV
        </a>
        <button
          v-if="props.can.create"
          type="button"
          class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-semibold text-white shadow hover:bg-indigo-500"
          @click="emit('create')"
        >
          Novo lançamento
        </button>
      </div>
    </header>

    <table class="min-w-full divide-y divide-slate-800 text-sm text-slate-100">
      <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
        <tr>
          <th class="px-4 py-3 text-left">Data</th>
          <th class="px-4 py-3 text-left">Vencimento</th>
          <th class="px-4 py-3 text-left">Descrição</th>
          <th class="px-4 py-3 text-left">Fornecedor</th>
          <th class="px-4 py-3 text-left">Valor</th>
          <th class="px-4 py-3 text-left">Status</th>
          <th class="px-4 py-3 text-left">Imóvel</th>
          <th class="px-4 py-3 text-left">Imóvel - MCC</th>
          <th class="px-4 py-3 text-left">Centro de Custo</th>
          <th class="px-4 py-3 text-left">Observações</th>
          <th class="px-4 py-3 text-right">Ações</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-800">
        <tr v-if="!hasItems" class="text-slate-400">
          <td colspan="11" class="px-4 py-6 text-center">Nenhum lançamento encontrado.</td>
        </tr>
        <tr
          v-else
          v-for="item in props.items"
          :key="item.id"
          :class="rowClasses(item)"
          :data-transaction-row="item.id"
          @click="handleRowClick(item)"
          @contextmenu.prevent="handleContextMenu($event, item)"
          @dblclick.stop="handleOpenDetails(item)"
        >
          <td class="px-4 py-3 text-slate-300">
            {{ item.data_ocorrencia_formatada ?? item.movement_date ?? item.data_ocorrencia ?? '-' }}
          </td>
          <td class="px-4 py-3 text-slate-300">
            {{ formatDate(item.due_date) }}
          </td>
          <td class="px-4 py-3 text-slate-200 max-w-xs truncate" :title="item.descricao ?? item.description ?? item.description_custom ?? '-'">
            {{
              item.descricao ??
              item.description ??
              item.description_custom ??
              '-'
            }}
          </td>
          <td class="px-4 py-3 text-slate-200">{{ item.person?.nome ?? '-' }}</td>
          <td class="px-4 py-3 text-right font-semibold text-white">
            {{
              item.valor_formatado ??
              new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL',
              }).format(Number(item.valor ?? 0))
            }}
          </td>
          <td class="px-4 py-3">
            <TransactionStatusBadge
              :status="item.status"
              :label="resolveBadgeLabel(item)"
              :category="resolveBadgeCategory(item)"
              :type="resolveItemType(item)"
            />
          </td>
          <td class="px-4 py-3 text-slate-200">{{ formatProperty(item) }}</td>
          <td class="px-4 py-3 text-slate-200">{{ formatPropertyMcc(item) }}</td>
          <td class="px-4 py-3 text-slate-200">{{ formatCenter(item) }}</td>
          <td class="px-4 py-3 text-slate-200 max-w-xs truncate" :title="formatNotes(item.notes)">
            {{ formatNotes(item.notes) }}
          </td>
          <td class="px-4 py-3 text-right text-xs">
            <div class="flex items-center justify-end gap-2">
              <button
                type="button"
                class="rounded-lg border border-slate-700 px-2 py-1 text-slate-200 transition hover:bg-slate-800 disabled:opacity-60"
                title="Ver detalhes"
                @click.stop="handleOpenDetails(item)"
                :disabled="deletingId === item.id"
              >
                Ver
              </button>
              <div
                v-if="hasRowActions(item)"
                class="relative"
                @keydown.escape.stop.prevent="closeActionsMenu()"
              >
                <button
                  type="button"
                  class="flex items-center gap-1 rounded border border-slate-600 px-2 py-1 text-slate-200 transition hover:bg-slate-800 disabled:opacity-60"
                  :class="{ 'bg-slate-800 text-white': isMenuAnchoredToButton(item) }"
                  :disabled="
                    deletingId === item.id ||
                    cloningId === item.id ||
                    payingId === item.id ||
                    cancellingId === item.id ||
                    receiptingId === item.id
                  "
                  :data-actions-trigger="item.id"
                  :aria-expanded="isMenuAnchoredToButton(item) ? 'true' : 'false'"
                  aria-haspopup="true"
                  @click.stop="toggleActionsMenu(item)"
                  title="Mais ações"
                >
                  Ações
                  <span aria-hidden="true" class="text-[11px] text-slate-400">v</span>
                </button>
                <transition
                  enter-active-class="transition transform ease-out duration-100"
                  enter-from-class="opacity-0 translate-y-1"
                  enter-to-class="opacity-100 translate-y-0"
                  leave-active-class="transition transform ease-in duration-75"
                  leave-from-class="opacity-100 translate-y-0"
                  leave-to-class="opacity-0 translate-y-1"
                >
                  <div
                    v-if="isMenuAnchoredToButton(item)"
                    class="absolute right-0 z-20 mt-2 w-44 overflow-hidden rounded-md border border-slate-700 bg-slate-900/95 shadow-lg backdrop-blur"
                    :data-actions-menu="item.id"
                  >
                    <button
                      v-for="action in getMenuActions(item)"
                      :key="action.key"
                      type="button"
                      :class="action.classes"
                      @click.stop="action.action"
                      :disabled="action.disabled"
                    >
                      <span>{{ action.label }}</span>
                      <span
                        v-if="action.loading"
                        :class="action.loadingClass"
                      >
                        ...
                      </span>
                    </button>
                  </div>
                </transition>
              </div>
            </div>
          </td>
        </tr>
      </tbody>
    </table>

    <Teleport to="body">
      <transition
        enter-active-class="transition transform ease-out duration-100"
        enter-from-class="opacity-0 translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition transform ease-in duration-75"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-1"
      >
        <div
          v-if="isContextMenuVisible && currentMenuItem"
          class="fixed z-50 w-44 overflow-hidden rounded-md border border-slate-700 bg-slate-900/95 text-xs shadow-lg backdrop-blur"
          :style="contextMenuStyle"
          :data-actions-menu="menuState.itemId ?? undefined"
          @click.stop
          @contextmenu.prevent
        >
          <button
            v-for="action in getMenuActions(currentMenuItem)"
            :key="`context-${action.key}`"
            type="button"
            :class="action.classes"
            @click.stop="action.action"
            :disabled="action.disabled"
          >
            <span>{{ action.label }}</span>
            <span
              v-if="action.loading"
              :class="action.loadingClass"
            >
              ...
            </span>
          </button>
        </div>
      </transition>
    </Teleport>

    <footer
      v-if="props.links.length > 1"
      class="flex flex-wrap items-center justify-center gap-2 border-t border-slate-800 px-4 py-3"
    >
      <button
        v-for="link in props.links"
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
    </footer>
  </section>
</template>
