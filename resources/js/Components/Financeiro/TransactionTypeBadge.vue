<script setup lang="ts">
const props = defineProps<{ tipo: 'receita' | 'despesa' | 'transferencia' | 'credito' | 'debito' }>();

const map = {
  receita: {
    label: 'Receita',
    classes: 'bg-emerald-500/15 border border-emerald-400/40 text-emerald-200',
  },
  despesa: {
    label: 'Despesa',
    classes: 'bg-rose-500/15 border border-rose-400/40 text-rose-200',
  },
  transferencia: {
    label: 'Transferência',
    classes: 'bg-sky-500/15 border border-sky-400/40 text-sky-200',
  },
  credito: {
    label: 'Crédito',
    classes: 'bg-emerald-500/15 border border-emerald-400/40 text-emerald-200',
  },
  debito: {
    label: 'Débito',
    classes: 'bg-rose-500/15 border border-rose-400/40 text-rose-200',
  },
} as const;

const current = map[props.tipo] ?? map.receita;
</script>

<template>
  <span
    :class="[
      'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide',
      current.classes,
    ]"
  >
    {{ current.label }}
  </span>
</template>
