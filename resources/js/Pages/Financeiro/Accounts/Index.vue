<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AccountFormModal from '@/Components/Financeiro/AccountFormModal.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { useToast } from '@/composables/useToast';
import { computed, ref } from 'vue';

type AccountRow = {
  id: number;
  nome: string;
  apelido?: string | null;
  tipo: string;
  categoria: string | null;
  instituicao?: string | null;
  banco?: string | null;
  agencia?: string | null;
  numero?: string | null;
  carteira?: string | null;
  moeda?: string | null;
  saldo_inicial: string | number;
  saldo_atual: string | number;
  limite_credito?: string | number | null;
  data_saldo_inicial?: string | null;
  permite_transf: boolean;
  padrao_recebimento: boolean;
  padrao_pagamento: boolean;
  ativo: boolean;
  observacoes?: string | null;
};

const props = defineProps<{
  accounts: {
    data: AccountRow[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
  };
  can: { create: boolean; update: boolean; delete: boolean };
}>();

const toast = useToast();
const showFormModal = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const currentAccount = ref<AccountRow | null>(null);
const loadingAccount = ref(false);
const hasActions = computed(() => props.can.update || props.can.delete);

const tipoLabel = (tipo: string) =>
  ({
    conta_corrente: 'Conta corrente',
    poupanca: 'Poupança',
    investimento: 'Investimento',
    caixa: 'Caixa',
    outro: 'Outro',
  })[tipo] ?? tipo;

const categoriaLabel = (categoria: string | null | undefined) =>
  ({
    operacional: 'Operacional',
    reserva: 'Reserva',
    investimento: 'Investimento',
  }[categoria ?? ''] ?? '—');

const formatCurrency = (value: string | number | null | undefined, currency = 'BRL') =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency }).format(Number(value ?? 0));

const formatDate = (value: string | null | undefined) => {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleDateString('pt-BR');
};

const openCreateModal = () => {
  if (!props.can.create) {
    return;
  }

  formMode.value = 'create';
  currentAccount.value = null;
  showFormModal.value = true;
};

const openEditModal = async (account: AccountRow) => {
  if (!props.can.update) {
    return;
  }

  try {
    loadingAccount.value = true;
    const { data } = await axios.get(`/api/financeiro/accounts/${account.id}`);
    const details = (data?.data ?? account) as AccountRow;
    formMode.value = 'edit';
    currentAccount.value = details;
    showFormModal.value = true;
  } catch (error: any) {
    const message = error?.response?.data?.message ?? 'Não foi possível carregar os dados da conta.';
    toast.error(message);
  } finally {
    loadingAccount.value = false;
  }
};

const closeModal = () => {
  showFormModal.value = false;
};

const refreshAccounts = () => {
  router.visit('/financeiro/contas', {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const handleCreated = () => {
  showFormModal.value = false;
  refreshAccounts();
};

const handleUpdated = () => {
  showFormModal.value = false;
  refreshAccounts();
};
</script>

<template>
  <AuthenticatedLayout title="Contas financeiras">
    <Head title="Contas financeiras" />

    <section
      class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
    >
      <header class="mb-4 flex items-center justify-between">
        <div>
          <h1 class="text-lg font-semibold text-white">Contas financeiras</h1>
          <p class="text-sm text-slate-400">Gerencie as contas utilizadas nos lancamentos.</p>
        </div>
        <button
          v-if="props.can.create"
          type="button"
          class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500"
          @click="openCreateModal"
        >
          <svg
            class="h-4 w-4"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16M4 12h16" />
          </svg>
          Nova conta
        </button>
      </header>

      <div class="overflow-hidden rounded-xl border border-slate-800">
        <table class="min-w-full divide-y divide-slate-800 text-sm text-slate-100">
          <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
            <tr>
              <th class="px-4 py-3 text-left">Conta</th>
              <th class="px-4 py-3 text-left">Categoria / Tipo</th>
              <th class="px-4 py-3 text-left">Banco</th>
              <th class="px-4 py-3 text-left">Saldos</th>
              <th class="px-4 py-3 text-left">Preferências</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th v-if="hasActions" class="px-4 py-3 text-right">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-if="props.accounts.data.length === 0">
              <td :colspan="hasActions ? 7 : 6" class="px-4 py-6 text-center text-slate-400">
                Nenhuma conta cadastrada.
              </td>
            </tr>
            <tr v-for="account in props.accounts.data" :key="account.id" class="align-top">
              <td class="px-4 py-3">
                <div class="font-semibold text-white">{{ account.nome }}</div>
                <div v-if="account.apelido" class="text-xs text-slate-400">Apelido: {{ account.apelido }}</div>
                <div v-if="account.instituicao" class="text-xs text-slate-400">
                  {{ account.instituicao }}
                </div>
                <div v-if="account.observacoes" class="text-xs text-slate-500">
                  Observações: {{ account.observacoes }}
                </div>
              </td>
              <td class="px-4 py-3 text-slate-300">
                <div>{{ categoriaLabel(account.categoria) }}</div>
                <div class="text-xs text-slate-500">{{ tipoLabel(account.tipo) }}</div>
                <div v-if="account.moeda" class="text-xs text-slate-500">Moeda: {{ (account.moeda || '').toUpperCase() }}</div>
              </td>
              <td class="px-4 py-3 text-slate-300">
                <div v-if="account.banco">{{ account.banco }}</div>
                <div class="text-xs text-slate-500">
                  <span v-if="account.agencia">Agência: {{ account.agencia }}</span>
                  <span v-if="account.agencia && account.numero"> • </span>
                  <span v-if="account.numero">Conta: {{ account.numero }}</span>
                  <span v-if="account.carteira">
                    <span v-if="account.agencia || account.numero"> • </span>
                    Carteira: {{ account.carteira }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-3 text-slate-300">
                <div>Inicial: {{ formatCurrency(account.saldo_inicial, account.moeda ?? 'BRL') }}</div>
                <div>Atual: {{ formatCurrency(account.saldo_atual, account.moeda ?? 'BRL') }}</div>
                <div class="text-xs text-slate-500">
                  Limite:
                  {{
                    account.limite_credito !== null && account.limite_credito !== undefined
                      ? formatCurrency(account.limite_credito, account.moeda ?? 'BRL')
                      : '—'
                  }}
                </div>
                <div class="text-xs text-slate-500">
                  Data base: {{ formatDate(account.data_saldo_inicial) }}
                </div>
              </td>
              <td class="px-4 py-3 text-xs text-slate-300">
                <div class="flex flex-col gap-1">
                  <span
                    :class="[
                      'inline-flex items-center gap-2 rounded-full px-3 py-1',
                      account.permite_transf
                        ? 'bg-emerald-500/10 border border-emerald-500/40 text-emerald-200'
                        : 'bg-slate-700/40 border border-slate-700 text-slate-300',
                    ]"
                  >
                    <span class="h-1.5 w-1.5 rounded-full" :class="account.permite_transf ? 'bg-emerald-300' : 'bg-slate-500'"></span>
                    Transf. {{ account.permite_transf ? 'permitida' : 'bloqueada' }}
                  </span>
                  <span
                    :class="[
                      'inline-flex items-center gap-2 rounded-full px-3 py-1',
                      account.padrao_recebimento
                        ? 'bg-indigo-500/10 border border-indigo-500/40 text-indigo-200'
                        : 'bg-slate-700/40 border border-slate-700 text-slate-300',
                    ]"
                  >
                    <span class="h-1.5 w-1.5 rounded-full" :class="account.padrao_recebimento ? 'bg-indigo-300' : 'bg-slate-500'"></span>
                    Padrão recebimento
                  </span>
                  <span
                    :class="[
                      'inline-flex items-center gap-2 rounded-full px-3 py-1',
                      account.padrao_pagamento
                        ? 'bg-amber-500/10 border border-amber-500/40 text-amber-200'
                        : 'bg-slate-700/40 border border-slate-700 text-slate-300',
                    ]"
                  >
                    <span class="h-1.5 w-1.5 rounded-full" :class="account.padrao_pagamento ? 'bg-amber-300' : 'bg-slate-500'"></span>
                    Padrão pagamento
                  </span>
                </div>
              </td>
              <td class="px-4 py-3">
                <span
                  :class="[
                    'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                    account.ativo
                      ? 'bg-emerald-500/10 border border-emerald-500/40 text-emerald-200'
                      : 'bg-slate-700/40 border border-slate-700 text-slate-300',
                  ]"
                >
                  {{ account.ativo ? 'Ativa' : 'Inativa' }}
                </span>
              </td>
              <td v-if="hasActions" class="px-4 py-3 text-right">
                <div class="flex justify-end gap-2">
                  <button
                    v-if="props.can.update"
                    type="button"
                    class="rounded-lg border border-slate-700 px-3 py-1 text-xs text-slate-200 transition hover:bg-slate-800 disabled:opacity-60"
                    :disabled="loadingAccount"
                    @click="openEditModal(account)"
                  >
                    Editar
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <AccountFormModal
      :show="showFormModal"
      :mode="formMode"
      :account="currentAccount"
      @close="closeModal"
      @created="handleCreated"
      @updated="handleUpdated"
    />
  </AuthenticatedLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
