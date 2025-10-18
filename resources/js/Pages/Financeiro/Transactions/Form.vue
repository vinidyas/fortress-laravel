<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TransactionForm from '@/Components/Financeiro/TransactionForm.vue';
import type { TransactionPayload, TransactionOption } from '@/Components/Financeiro/TransactionForm.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
  mode: 'create' | 'edit';
  transaction: TransactionPayload | null;
  accounts: TransactionOption[];
  costCenters: TransactionOption[];
  permissions: {
    update: boolean;
    delete: boolean;
    reconcile: boolean;
  };
}>();
</script>

<template>
  <AuthenticatedLayout :title="props.mode === 'create' ? 'Novo lançamento' : 'Editar lançamento'">
    <Head :title="props.mode === 'create' ? 'Novo lançamento' : 'Editar lançamento'" />

    <TransactionForm
      :mode="props.mode"
      :transaction="props.transaction"
      :accounts="props.accounts"
      :cost-centers="props.costCenters"
      :permissions="props.permissions"
    />
  </AuthenticatedLayout>
</template>
