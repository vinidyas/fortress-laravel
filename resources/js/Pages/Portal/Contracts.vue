<template>
  <PortalLayout>
    <section class="space-y-6">
      <header class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 class="text-2xl font-semibold text-white">Contratos</h2>
          <p class="text-sm text-slate-400">Veja os contratos vigentes e seus detalhes principais.</p>
        </div>
        <div class="text-xs uppercase tracking-wide text-slate-400">
          <span class="rounded-full border border-slate-700 px-3 py-1 text-slate-200">{{ contractsStore.contracts.length }} ativo(s)</span>
        </div>
      </header>

      <div v-if="loading" class="rounded-xl border border-slate-800 bg-slate-900/50 p-6 text-sm text-slate-300">
        Carregando contratos...
      </div>
      <div v-else-if="contracts.length === 0" class="rounded-xl border border-slate-800 bg-slate-900/50 p-6 text-sm text-slate-300">
        Nenhum contrato encontrado para o seu usuário.
      </div>
      <div v-else class="space-y-4">
        <article
          v-for="contract in contracts"
          :key="contract.id"
          class="rounded-xl border border-slate-800 bg-slate-900/70 p-5 shadow-lg shadow-black/25"
        >
          <header class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
            <div>
              <h3 class="text-lg font-semibold text-white">Contrato {{ contract.codigo || contract.id }}</h3>
              <p class="text-xs text-slate-400">
                Vigência: {{ formatDate(contract.data_inicio) }} — {{ contract.data_fim ? formatDate(contract.data_fim) : 'indeterminado' }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <span
                class="inline-flex items-center justify-center rounded-full px-3 py-1 text-xs font-semibold"
                :class="statusBadgeClass(contract.status)"
              >
                {{ contract.status || '—' }}
              </span>
            </div>
          </header>

          <dl class="mt-4 grid gap-3 text-sm text-slate-300 md:grid-cols-2">
            <div>
              <dt class="text-xs uppercase tracking-wide text-slate-500">Valor aluguel</dt>
              <dd class="text-base font-semibold text-indigo-200">R$ {{ formatCurrency(contract.valor_aluguel) }}</dd>
            </div>
            <div>
              <dt class="text-xs uppercase tracking-wide text-slate-500">Dia de vencimento</dt>
              <dd class="text-base text-slate-100">{{ contract.dia_vencimento || '—' }}</dd>
            </div>
            <div>
              <dt class="text-xs uppercase tracking-wide text-slate-500">Imóvel</dt>
              <dd class="text-slate-100">{{ contract.imovel?.endereco || '—' }}</dd>
            </div>
            <div>
              <dt class="text-xs uppercase tracking-wide text-slate-500">Locador</dt>
              <dd class="text-slate-100">{{ contract.locador?.nome || '—' }}</dd>
            </div>
          </dl>
        </article>
      </div>
    </section>
  </PortalLayout>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { usePortalContractsStore } from '@/Stores/portal/contracts';
import axios from '@/bootstrap';
import { route } from 'ziggy-js';

const contractsStore = usePortalContractsStore();

const loading = computed(() => contractsStore.loading);
const contracts = computed(() => contractsStore.contracts);

onMounted(async () => {
  if (contractsStore.contracts.length > 0) {
    return;
  }

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
});

function formatDate(date?: string | null) {
  if (!date) return '—';
  return new Date(date).toLocaleDateString('pt-BR');
}

function formatCurrency(value?: number | null) {
  if (typeof value !== 'number') return '0,00';
  return value.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
}

function statusBadgeClass(status?: string | null) {
  const normalized = (status || '').toLowerCase();
  if (normalized.includes('ativo')) return 'bg-emerald-500/20 text-emerald-200 border border-emerald-500/40';
  if (normalized.includes('suspenso')) return 'bg-amber-500/20 text-amber-200 border border-amber-500/40';
  if (normalized.includes('cancel')) return 'bg-rose-500/20 text-rose-200 border border-rose-500/40';
  return 'bg-slate-800/60 text-slate-200 border border-slate-700/40';
}
</script>
