<script setup lang="ts">
const props = defineProps<{ tipo: 'credito' | 'debito' }>();

const label = props.tipo === 'credito' ? 'Crédito' : 'Débito';
const classes =
    props.tipo === 'credito'
        ? 'bg-emerald-500/15 border border-emerald-400/40 text-emerald-200'
        : 'bg-rose-500/15 border border-rose-400/40 text-rose-200';
</script>

<template>
    <span :class="['inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide', classes]">
        {{ label }}
    </span>
</template>
