<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { formatDate } from '@/utils/date';

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

interface RecentPerson {
  id: number;
  name: string;
  document: string | null;
  type: string;
  roles: string[];
  createdAt: string | null;
}

const props = defineProps<{
  metrics?: Partial<Metrics>;
  expiringContracts: ExpiringContract[];
  openInvoices: OpenInvoice[];
  recentPeople: RecentPerson[];
}>();

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
</script>

<template>
  <AuthenticatedLayout title="Dashboard">
    <div class="space-y-10 text-slate-100">
      <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        <article
          class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
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
          class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
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
          class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
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
          class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
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

      <section class="grid gap-6 xl:grid-cols-2">
        <div
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
          class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
        >
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-white">Faturas em aberto</p>
              <p class="text-xs text-slate-400">Até 5 faturas mais recentes</p>
            </div>
          </div>

          <div class="mt-6 overflow-hidden rounded-xl border border-slate-800">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
              <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th class="px-4 py-3 text-left">Contrato</th>
                  <th class="px-4 py-3 text-left">Imóvel</th>
                  <th class="px-4 py-3 text-left">Vencimento</th>
                  <th class="px-4 py-3 text-right">Valor</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800 bg-slate-950/40 text-slate-200">
                <tr v-if="!openInvoices.length">
                  <td colspan="4" class="px-4 py-6 text-center text-slate-400">
                    Nenhuma fatura em aberto.
                  </td>
                </tr>
                <tr v-for="invoice in openInvoices" :key="invoice.id">
                  <td class="px-4 py-4">
                    <p class="font-medium text-white">{{ invoice.contract ?? '-' }}</p>
                    <p class="text-xs text-slate-400">
                      Competência {{ formatDate(invoice.competencia) }}
                    </p>
                  </td>
                  <td class="px-4 py-4 text-slate-300">{{ invoice.property ?? '-' }}</td>
                  <td class="px-4 py-4 text-xs text-slate-300">
                    <p>{{ formatDate(invoice.dueDate) }}</p>
                    <p class="text-rose-300">{{ formatDays(invoice.lateDays) }}</p>
                  </td>
                  <td class="px-4 py-4 text-right font-semibold text-emerald-300">
                    {{ formatCurrency(invoice.amount) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section
        class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-semibold text-white">Pessoas adicionadas recentemente</p>
            <p class="text-xs text-slate-400">Últimas 5 entradas</p>
          </div>
        </div>

        <div class="mt-6 overflow-hidden rounded-xl border border-slate-800">
          <table class="min-w-full divide-y divide-slate-800 text-sm">
            <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th class="px-4 py-3 text-left">Nome</th>
                <th class="px-4 py-3 text-left">Documento</th>
                <th class="px-4 py-3 text-left">Tipo</th>
                <th class="px-4 py-3 text-left">Pap?is</th>
                <th class="px-4 py-3 text-left">Criado em</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800 bg-slate-950/40 text-slate-200">
              <tr v-if="!recentPeople.length">
                <td colspan="5" class="px-4 py-6 text-center text-slate-400">
                  Nenhuma pessoa cadastrada recentemente.
                </td>
              </tr>
              <tr v-for="person in recentPeople" :key="person.id">
                <td class="px-4 py-4">
                  <p class="font-medium text-white">{{ person.name }}</p>
                </td>
                <td class="px-4 py-4 text-xs text-slate-300">{{ person.document ?? '-' }}</td>
                <td class="px-4 py-4 text-xs text-slate-300">{{ person.type }}</td>
                <td class="px-4 py-4 text-xs text-slate-300">
                  <span v-if="person.roles.length">{{ person.roles.join(', ') }}</span>
                  <span v-else>-</span>
                </td>
                <td class="px-4 py-4 text-xs text-slate-300">{{ formatDate(person.createdAt) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </AuthenticatedLayout>
</template>
