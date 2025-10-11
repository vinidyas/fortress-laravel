<script setup lang="ts">
const props = defineProps<{ status: 'pendente' | 'conciliado' | 'cancelado' }>();

const options = {
  pendente: {
    label: 'Pendente',
    classes: 'bg-amber-500/15 border border-amber-400/40 text-amber-200',
  },
  conciliado: {
    label: 'Conciliado',
    classes: 'bg-emerald-500/15 border border-emerald-400/40 text-emerald-200',
  },
  cancelado: {
    label: 'Cancelado',
    classes: 'bg-rose-500/15 border border-rose-400/40 text-rose-200',
  },
} as const;

const current = options[props.status];
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
