<template>
  <PortalLayout>
    <section class="space-y-6">
      <header class="flex flex-col gap-2">
        <h2 class="text-2xl font-semibold text-white">Resumo do aluguel</h2>
        <p class="text-sm text-slate-400">Veja a situação atual dos seus contratos e faturas.</p>
      </header>
      <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-5 shadow-lg">
          <p class="text-xs uppercase tracking-wide text-slate-400">Contratos ativos</p>
          <p class="mt-2 text-3xl font-semibold text-white">{{ metrics.activeContracts }}</p>
        </div>
        <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-5 shadow-lg">
          <p class="text-xs uppercase tracking-wide text-slate-400">Faturas em aberto</p>
          <p class="mt-2 text-3xl font-semibold text-amber-300">{{ metrics.openInvoices }}</p>
        </div>
        <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-5 shadow-lg">
          <p class="text-xs uppercase tracking-wide text-slate-400">Pagas nos últimos 30 dias</p>
          <p class="mt-2 text-3xl font-semibold text-emerald-300">{{ metrics.paidLast30 }}</p>
        </div>
      </div>
      <section class="rounded-xl border border-slate-800 bg-slate-900/60 p-5">
        <header class="mb-4 flex items-center justify-between">
          <h3 class="text-lg font-semibold text-white">Próximos vencimentos</h3>
          <a class="text-sm font-semibold text-indigo-300 hover:text-indigo-100" :href="route('portal.invoices', undefined, false)">Ver todas as faturas</a>
        </header>
        <ul v-if="upcomingInvoices.length" class="space-y-3">
          <li v-for="invoice in upcomingInvoices" :key="invoice.id" class="flex items-center justify-between rounded-lg border border-slate-800/60 bg-slate-900/70 px-4 py-3 text-sm">
            <div>
              <p class="font-medium text-white">Fatura #{{ invoice.id }}</p>
              <p class="text-xs text-slate-400">Vencimento: {{ formatDate(invoice.vencimento) }}</p>
            </div>
            <div class="text-right">
              <p class="font-semibold text-indigo-200">R$ {{ formatCurrency(invoice.valor_total) }}</p>
              <p class="text-xs text-slate-400">Contrato {{ invoice.contrato_id }}</p>
            </div>
          </li>
        </ul>
        <p v-else class="text-sm text-slate-400">Nenhuma fatura pendente nos próximos dias.</p>
      </section>
    </section>
  </PortalLayout>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { usePortalContractsStore } from '@/Stores/portal/contracts';
import { usePortalInvoicesStore } from '@/Stores/portal/invoices';
import axios from '@/bootstrap';
import { route } from 'ziggy-js';

const contractsStore = usePortalContractsStore();
const invoicesStore = usePortalInvoicesStore();

onMounted(async () => {
  if (contractsStore.contracts.length === 0) {
    await fetchContracts();
  }
  if (invoicesStore.invoices.length === 0) {
    await fetchInvoices();
  }
});

async function fetchContracts() {
  try {
    contractsStore.setLoading(true);
    const endpoint = route('portal.contracts.index', undefined, false);
    const { data } = await axios.get(endpoint);
    contractsStore.setContracts(data?.data || []);
  } catch (error: any) {
    contractsStore.setError(error?.response?.data?.message || 'Não foi possível carregar os contratos.');
  } finally {
    contractsStore.setLoading(false);
  }
}

async function fetchInvoices() {
  try {
    invoicesStore.setLoading(true);
    const endpoint = route('portal.invoices.index', undefined, false);
    const { data } = await axios.get(endpoint);
    invoicesStore.setInvoices(data?.data || []);
  } catch (error: any) {
    invoicesStore.setError(error?.response?.data?.message || 'Não foi possível carregar as faturas.');
  } finally {
    invoicesStore.setLoading(false);
  }
}

const metrics = computed(() => {
  const contracts = contractsStore.contracts;
  const invoices = invoicesStore.invoices;

  return {
    activeContracts: contracts.length,
    openInvoices: invoices.filter((invoice) => invoice.status?.toLowerCase() !== 'paga').length,
    paidLast30: invoices.filter((invoice) => {
      if (!invoice.pago_em) return false;
      const paidDate = new Date(invoice.pago_em);
      const thirtyDaysAgo = new Date();
      thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
      return paidDate >= thirtyDaysAgo;
    }).length,
  };
});

const upcomingInvoices = computed(() => {
  return invoicesStore.invoices
    .filter((invoice) => invoice.status?.toLowerCase() !== 'paga' && invoice.vencimento)
    .sort((a, b) => (a.vencimento ?? '').localeCompare(b.vencimento ?? ''))
    .slice(0, 5);
});

function formatDate(date?: string | null) {
  if (!date) return '—';
  return new Date(date).toLocaleDateString('pt-BR');
}

function formatCurrency(value?: number | null) {
  if (typeof value !== 'number') return '0,00';
  return value.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
}
</script>
