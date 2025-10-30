<script setup lang="ts">
import { computed } from 'vue';
import {
  resolveStatusCategory,
  resolveStatusLabel,
  type TransactionStatusCategory,
  type TransactionStatusCode,
  type TransactionType,
} from '@/utils/financeiro/status';

const CATEGORY_STYLES: Record<TransactionStatusCategory, string> = {
  open: 'bg-slate-500/15 border border-slate-400/40 text-slate-200',
  overdue: 'bg-amber-600/15 border border-amber-500/40 text-amber-200',
  settled: 'bg-emerald-500/15 border border-emerald-400/40 text-emerald-200',
  cancelled: 'bg-rose-500/15 border border-rose-400/40 text-rose-200',
};

const isCategory = (value: unknown): value is TransactionStatusCategory =>
  typeof value === 'string' && value in CATEGORY_STYLES;

const props = withDefaults(
  defineProps<{
    status?: TransactionStatusCode | string | null;
    label?: string | null;
    category?: TransactionStatusCategory | string | null;
    type?: TransactionType | string | null;
  }>(),
  {
    status: undefined,
    label: undefined,
    category: undefined,
    type: undefined,
  }
);

const resolvedCategory = computed<TransactionStatusCategory>(() =>
  isCategory(props.category) ? props.category : resolveStatusCategory(props.status)
);

const resolvedLabel = computed(
  () => props.label ?? resolveStatusLabel(props.status, props.type)
);

const resolvedClasses = computed(
  () => CATEGORY_STYLES[resolvedCategory.value] ?? CATEGORY_STYLES.open
);
</script>

<template>
  <span
    :class="[
      'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide',
      resolvedClasses,
    ]"
  >
    {{ resolvedLabel }}
  </span>
</template>
