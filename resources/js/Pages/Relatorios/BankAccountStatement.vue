<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import DatePicker from '@/Components/Form/DatePicker.vue';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { useNotificationStore } from '@/Stores/notifications';

interface AccountOption {
  id: number;
  nome: string;
  saldo_inicial?: number | string | null;
  data_saldo_inicial?: string | null;
  ativo?: boolean;
}

interface StatementRow {
  id: number;
  movement_date?: string | null;
  due_date?: string | null;
  description?: string | null;
  type?: string | null;
  amount?: number | null;
  signed_amount: number;
  absolute_amount: number;
  amount_in: number;
  amount_out: number;
  balance_after: number;
  person?: { id: number; nome: string } | null;
  property?: { id: number; nome: string | null } | null;
}

interface StatementPayload {
  account: {
    id: number;
    nome: string;
    saldo_inicial: number;
    data_saldo_inicial?: string | null;
  };
  period: { from?: string | null; to?: string | null };
  opening_balance: number;
  opening_balance_base: number;
  closing_balance: number;
  totals: { inflow: number; outflow: number; net: number };
  data: StatementRow[];
}

const props = defineProps<{
  accounts: AccountOption[];
  canUpdateBalance: boolean;
}>();

const notificationStore = useNotificationStore();

const accounts = ref<AccountOption[]>(props.accounts.map((account) => ({ ...account })));

const filters = reactive({
  financial_account_id: '' as string | number,
  date_from: '',
  date_to: '',
  opening_balance: '',
});

const balanceForm = reactive({
  saldo_inicial: '',
  data_saldo_inicial: '',
});

const loading = ref(false);
const errorMessage = ref('');
const statement = ref<StatementPayload | null>(null);
const showBalanceEditor = ref(false);

const selectedAccount = computed(() => {
  if (!filters.financial_account_id) return null;
  const id = Number(filters.financial_account_id);
  return accounts.value.find((account) => account.id === id) ?? null;
});

const formatCurrency = (value: number | string | null | undefined) => {
  const numeric =
    typeof value === 'string'
      ? Number.parseFloat(value || '0')
      : typeof value === 'number'
        ? value
        : 0;

  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(
    Number.isFinite(numeric) ? numeric : 0,
  );
};

const totalRowLabel = computed(() => {
  if (!statement.value) return '';
  return statement.value.totals.inflow >= statement.value.totals.outflow
    ? 'TOTAL DE RECEITAS'
    : 'TOTAL DAS DESPESAS';
});

const totalRowValue = computed(() => {
  if (!statement.value) return 0;
  return statement.value.totals.inflow >= statement.value.totals.outflow
    ? statement.value.totals.inflow
    : statement.value.totals.outflow;
});

const totalRowClass = computed(() =>
  statement.value && statement.value.totals.inflow >= statement.value.totals.outflow
    ? 'text-emerald-300'
    : 'text-rose-300',
);

const formatDate = (value?: string | null) => {
  if (!value) return '—';
  try {
    return new Date(value).toLocaleDateString('pt-BR');
  } catch {
    return value;
  }
};

const resetFilters = () => {
  filters.date_from = '';
  filters.date_to = '';
  filters.opening_balance = '';
  errorMessage.value = '';
  statement.value = null;
};

const loadStatement = async () => {
  if (!filters.financial_account_id) {
    notificationStore.info('Selecione uma conta bancária para gerar o extrato.');
    return;
  }

  loading.value = true;
  errorMessage.value = '';

  try {
    const params: Record<string, any> = {
      financial_account_id: filters.financial_account_id,
    };

    if (filters.date_from) params.date_from = filters.date_from;
    if (filters.date_to) params.date_to = filters.date_to;
    if (filters.opening_balance.trim() !== '') params.opening_balance = filters.opening_balance;

    const { data } = await axios.get('/api/reports/bank-account-statement', { params });

    statement.value = data;
  } catch (error: any) {
    const message =
      error?.response?.data?.message ?? 'Não foi possível carregar o extrato da conta bancária.';
    errorMessage.value = message;
    statement.value = null;
  } finally {
    loading.value = false;
  }
};

const openBalanceEditor = () => {
  const account = selectedAccount.value;
  if (!account) return;

  balanceForm.saldo_inicial = account.saldo_inicial != null ? String(account.saldo_inicial) : '';
  balanceForm.data_saldo_inicial = account.data_saldo_inicial ?? '';
  showBalanceEditor.value = true;

  nextTick(() => {
    const input = document.getElementById('saldo_inicial_input');
    if (input) input.focus();
  });
};

const saveBalance = async () => {
  const account = selectedAccount.value;
  if (!account) return;

  try {
    const payload: Record<string, any> = {
      saldo_inicial: balanceForm.saldo_inicial || 0,
    };

    if (balanceForm.data_saldo_inicial) {
      payload.data_saldo_inicial = balanceForm.data_saldo_inicial;
    }

    const { data } = await axios.patch(
      `/api/financial-accounts/${account.id}/initial-balance`,
      payload,
    );

    const updated = data?.account;
    if (updated) {
      const idx = accounts.value.findIndex((item) => item.id === account.id);
      if (idx >= 0) {
        accounts.value[idx] = {
          ...accounts.value[idx],
          saldo_inicial: updated.saldo_inicial,
          data_saldo_inicial: updated.data_saldo_inicial,
        };
      }
    }

    notificationStore.success('Saldo inicial atualizado com sucesso.');
    showBalanceEditor.value = false;

    if (statement.value) {
      await loadStatement();
    }
  } catch (error: any) {
    const message =
      error?.response?.data?.message ?? 'Não foi possível atualizar o saldo inicial da conta.';
    notificationStore.error(message);
  }
};

const cancelBalanceEdit = () => {
  showBalanceEditor.value = false;
};

watch(
  () => filters.financial_account_id,
  () => {
    const account = selectedAccount.value;
    if (account) {
      balanceForm.saldo_inicial =
        account.saldo_inicial != null ? String(account.saldo_inicial) : '';
      balanceForm.data_saldo_inicial = account.data_saldo_inicial ?? '';
    }
  },
);

onMounted(() => {
  let shouldLoad = false;

  if (!filters.financial_account_id && accounts.value.length === 1) {
    filters.financial_account_id = accounts.value[0].id;
    shouldLoad = true;
  } else if (filters.financial_account_id) {
    shouldLoad = true;
  }

  if (shouldLoad) {
    nextTick(() => {
      loadStatement();
    });
  }
});
</script>

<template>
  <AuthenticatedLayout title="Extrato por Conta">
    <Head title="Extrato por Conta Bancária" />

    <section
      class="space-y-6 rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
    >
      <header class="flex flex-col gap-1">
        <h1 class="text-xl font-semibold text-white">Extrato por Conta Bancária</h1>
        <p class="text-sm text-slate-400">
          Consulte o saldo inicial e final de uma conta bancária em um período específico,
          consolidando receitas e despesas relacionadas.
        </p>
      </header>

      <form class="grid gap-4 md:grid-cols-5" @submit.prevent="loadStatement">
        <div class="md:col-span-2">
          <label class="text-xs font-semibold text-slate-400">Conta bancária</label>
          <select
            v-model="filters.financial_account_id"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
          >
            <option value="">Selecione</option>
            <option
              v-for="account in accounts"
              :key="account.id"
              :value="account.id"
              :disabled="account.ativo === false"
            >
              {{ account.nome }}
              <span v-if="account.ativo === false">(inativa)</span>
            </option>
          </select>
        </div>

        <div>
          <label class="text-xs font-semibold text-slate-400">Período inicial</label>
          <DatePicker v-model="filters.date_from" placeholder="dd/mm/aaaa" />
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-400">Período final</label>
          <DatePicker v-model="filters.date_to" placeholder="dd/mm/aaaa" />
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-400">Saldo inicial personalizado</label>
          <input
            v-model="filters.opening_balance"
            type="number"
            step="0.01"
            placeholder="Opcional"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
          />
        </div>

        <div class="flex items-end gap-3 md:col-span-2">
          <button
            type="submit"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 disabled:opacity-60"
            :disabled="loading"
          >
            {{ loading ? 'Carregando...' : 'Gerar extrato' }}
          </button>
          <button
            type="button"
            class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-800 disabled:opacity-60"
            :disabled="loading"
            @click="resetFilters"
          >
            Limpar filtros
          </button>
        </div>
      </form>

      <div v-if="selectedAccount" class="rounded-xl border border-slate-800 bg-slate-900/70 p-4">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
          <div>
            <p class="text-sm text-slate-400">Saldo inicial cadastrado</p>
            <p class="text-base font-semibold text-white">
              {{ formatCurrency(selectedAccount.saldo_inicial ?? 0) }}
              <span v-if="selectedAccount.data_saldo_inicial" class="text-xs font-normal text-slate-400">
                ({{ formatDate(selectedAccount.data_saldo_inicial) }})
              </span>
            </p>
          </div>
          <div v-if="canUpdateBalance" class="flex items-center gap-3">
            <button
              type="button"
              class="rounded-lg border border-indigo-500 px-3 py-1.5 text-xs font-medium text-indigo-200 hover:bg-indigo-500/10"
              @click="openBalanceEditor"
            >
              Atualizar saldo inicial
            </button>
          </div>
        </div>

        <transition name="fade">
          <form
            v-if="showBalanceEditor && canUpdateBalance"
            class="mt-4 grid gap-3 md:grid-cols-3"
            @submit.prevent="saveBalance"
          >
            <div>
              <label class="text-xs font-semibold text-slate-400">Novo saldo inicial</label>
              <input
                id="saldo_inicial_input"
                v-model="balanceForm.saldo_inicial"
                type="number"
                step="0.01"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
                required
              />
            </div>
            <div>
              <label class="text-xs font-semibold text-slate-400">Data de referência</label>
              <DatePicker v-model="balanceForm.data_saldo_inicial" placeholder="dd/mm/aaaa" />
            </div>
            <div class="flex items-end gap-2">
              <button
                type="submit"
                class="w-full rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
              >
                Salvar
              </button>
              <button
                type="button"
                class="w-full rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-200 hover:bg-slate-800"
                @click="cancelBalanceEdit"
              >
                Cancelar
              </button>
            </div>
          </form>
        </transition>
      </div>

      <p
        v-if="errorMessage"
        class="rounded-lg border border-rose-500/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-200"
      >
        {{ errorMessage }}
      </p>

      <div v-if="statement" class="grid gap-4 md:grid-cols-4">
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Saldo inicial computado</p>
          <p class="text-lg font-semibold text-slate-200">
            {{ formatCurrency(statement.opening_balance) }}
          </p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Saldo base cadastrado</p>
          <p class="text-lg font-semibold text-slate-200">
            {{ formatCurrency(statement.opening_balance_base) }}
          </p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Entradas</p>
          <p class="text-lg font-semibold text-emerald-300">
            {{ formatCurrency(statement.totals.inflow) }}
          </p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm">
          <p class="text-slate-400">Saídas</p>
          <p class="text-lg font-semibold text-rose-300">
            {{ formatCurrency(statement.totals.outflow) }}
          </p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm md:col-span-2">
          <p class="text-slate-400">Saldo final</p>
          <p
            :class="[
              'text-2xl font-semibold',
              statement.closing_balance >= 0 ? 'text-emerald-300' : 'text-rose-300',
            ]"
          >
            {{ formatCurrency(statement.closing_balance) }}
          </p>
        </article>
        <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm md:col-span-2">
          <p class="text-slate-400">Resultado no período</p>
          <p
            :class="[
              'text-2xl font-semibold',
              statement.totals.net >= 0 ? 'text-emerald-300' : 'text-rose-300',
            ]"
          >
            {{ formatCurrency(statement.totals.net) }}
          </p>
        </article>
      </div>

      <div class="overflow-hidden rounded-2xl border border-slate-800">
        <table class="min-w-full table-fixed divide-y divide-slate-800 text-sm text-slate-100">
          <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
            <tr>
              <th class="px-4 py-3 text-left w-[10%]">Data</th>
              <th class="px-4 py-3 text-left w-[28%]">Descrição</th>
              <th class="px-4 py-3 text-left w-[20%]">Pessoa</th>
              <th class="px-4 py-3 text-left w-[18%]">Imóvel</th>
              <th class="px-4 py-3 text-right w-[12%]">Entradas</th>
              <th class="px-4 py-3 text-right w-[12%]">Saídas</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-if="loading">
              <td colspan="6" class="px-6 py-8 text-center text-slate-400">Carregando dados...</td>
            </tr>
            <tr v-else-if="!statement || !statement.data.length">
              <td colspan="6" class="px-6 py-8 text-center text-slate-400">
                Nenhum lançamento encontrado para os filtros selecionados.
              </td>
            </tr>
            <tr v-else v-for="row in statement.data" :key="row.id">
              <td class="px-4 py-3 text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                {{ formatDate(row.movement_date) }}
              </td>
              <td class="px-4 py-3 whitespace-nowrap overflow-hidden text-ellipsis">
                <span class="font-semibold text-white">{{ row.description ?? '-' }}</span>
                <span v-if="row.due_date" class="block text-xs text-slate-400">
                  Venc: {{ formatDate(row.due_date) }}
                </span>
              </td>
              <td class="px-4 py-3 text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                {{ row.person?.nome ?? '—' }}
              </td>
              <td class="px-4 py-3 text-slate-300 whitespace-nowrap overflow-hidden text-ellipsis">
                {{ row.property?.nome ?? '—' }}
              </td>
              <td class="px-4 py-3 text-right text-emerald-300 whitespace-nowrap">
                {{ row.amount_in > 0 ? formatCurrency(row.amount_in) : '—' }}
              </td>
              <td class="px-4 py-3 text-right text-rose-300 whitespace-nowrap">
                {{ row.amount_out > 0 ? formatCurrency(row.amount_out) : '—' }}
              </td>
            </tr>
            <tr
              v-if="statement && statement.data.length"
              class="bg-slate-900/60 font-semibold uppercase tracking-wide text-slate-100"
            >
              <td colspan="5" class="px-4 py-3 text-right">
                {{ totalRowLabel }}
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap" :class="totalRowClass">
                {{ formatCurrency(totalRowValue) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </AuthenticatedLayout>
</template>
