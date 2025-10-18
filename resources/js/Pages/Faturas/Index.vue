<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import DatePicker from '@/Components/Form/DatePicker.vue';
import GenerateInvoiceModal from '@/Components/Faturas/GenerateInvoiceModal.vue';

type Nullable<T> = T | null;

type ContratoImovel = Nullable<{
  codigo?: string;
  cidade?: Nullable<string>;
  bairro?: Nullable<string>;
  complemento?: Nullable<string>;
  condominio?: {
    id?: number;
    nome?: Nullable<string>;
  } | null;
}>;

type FaturaRow = {
  id: number;
  contrato_id: number;
  status: string;
  competencia: string;
  vencimento: string;
  valor_total: string;
  valor_pago: Nullable<string>;
  anexos_count?: number;
  anexos?: {
    id: number;
    display_name: string;
    url: string;
  }[];
  email?: {
    last_sent_at?: Nullable<string>;
    last_status?: Nullable<string>;
  } | null;
  contrato?: {
    codigo_contrato?: string;
    imovel?: ContratoImovel;
  } | null;
};

type MetaPagination = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

const statusOptions = ['Aberta', 'Paga', 'Cancelada'];

const filters = reactive({
  search: '',
  status: '',
  contrato_id: '',
  competencia: '',
  competencia_de: '',
  competencia_ate: '',
  vencimento_de: '',
  vencimento_ate: '',
});

const faturas = ref<FaturaRow[]>([]);
const meta = ref<MetaPagination | null>(null);
const loading = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const isGenerating = ref(false);
const showSingleInvoiceModal = ref(false);
const perPageOptions = [10, 15, 25, 50];
const perPage = ref(15);
const currentPage = ref(1);

const hasResults = computed(() => faturas.value.length > 0);
const actionButtonClass =
  'inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-700 bg-slate-900/60 text-slate-300 transition hover:border-indigo-500 hover:bg-indigo-500/20 hover:text-white';
const dangerActionButtonClass =
  'inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-500/40 bg-rose-500/10 text-rose-300 transition hover:border-rose-500 hover:bg-rose-500/20 hover:text-rose-100';

watch(perPage, () => {
  fetchFaturas(1);
});

function statusBadgeClasses(status: string): string {
  switch (status) {
    case 'Aberta':
      return 'bg-amber-500/15 text-amber-300 border border-amber-500/40';
    case 'Paga':
      return 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/40';
    case 'Cancelada':
      return 'bg-rose-500/15 text-rose-300 border border-rose-500/40';
    default:
      return 'bg-slate-500/15 text-slate-300 border border-slate-500/40';
  }
}

async function fetchFaturas(page = 1) {
  loading.value = true;
  errorMessage.value = '';

  const params: Record<string, unknown> = {
    page,
    per_page: perPage.value,
  };

  if (filters.search) params['filter[search]'] = filters.search;
  if (filters.status) params['filter[status]'] = filters.status;
  if (filters.contrato_id) params['filter[contrato_id]'] = filters.contrato_id;
  if (filters.competencia) params['filter[competencia]'] = filters.competencia;
  if (filters.competencia_de) params['filter[competencia_de]'] = filters.competencia_de;
  if (filters.competencia_ate) params['filter[competencia_ate]'] = filters.competencia_ate;
  if (filters.vencimento_de) params['filter[vencimento_de]'] = filters.vencimento_de;
  if (filters.vencimento_ate) params['filter[vencimento_ate]'] = filters.vencimento_ate;

  try {
    const { data } = await axios.get('/api/faturas', { params });
    const rows: FaturaRow[] = data.data ?? [];
    const metaData: MetaPagination | null = data.meta ?? null;

    if (metaData && rows.length === 0 && metaData.current_page > 1) {
      await fetchFaturas(metaData.current_page - 1);

      return;
    }

    faturas.value = rows;
    meta.value = metaData;
    currentPage.value = metaData?.current_page ?? page;
  } catch (error) {
    console.error(error);
    errorMessage.value = 'Não foi possível carregar as faturas.';
  } finally {
    loading.value = false;
  }
}

function applyFilters() {
  fetchFaturas(1);
}

function resetFilters() {
  Object.assign(filters, {
    search: '',
    status: '',
    contrato_id: '',
    competencia: '',
    competencia_de: '',
    competencia_ate: '',
    vencimento_de: '',
    vencimento_ate: '',
  });
  perPage.value = 15;
  fetchFaturas(1);
}

async function generateCurrentMonthInvoices() {
  if (isGenerating.value) return;

  isGenerating.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  try {
    const { data } = await axios.post('/api/faturas/generate-month');
    successMessage.value = data.message ?? 'Faturas geradas com sucesso.';
    await fetchFaturas(1);
  } catch (error) {
    console.error(error);
    if (axios.isAxiosError(error) && error.response?.data?.message) {
      errorMessage.value = error.response.data.message;
    } else {
      errorMessage.value = 'Não foi possível gerar as faturas do mês.';
    }
  } finally {
    isGenerating.value = false;
  }
}

function openSingleInvoiceModal() {
  showSingleInvoiceModal.value = true;
}

function closeSingleInvoiceModal() {
  showSingleInvoiceModal.value = false;
}

function handleInvoiceGenerated(payload: { message: string }) {
  successMessage.value = payload.message ?? 'Fatura processada com sucesso.';
  errorMessage.value = '';
  showSingleInvoiceModal.value = false;
  void fetchFaturas(1);
}

function changePage(page: number) {
  if (!meta.value) return;
  if (page < 1 || page > meta.value.last_page) return;
  fetchFaturas(page);
}

function formatImovelLabel(imovel: ContratoImovel): string {
  if (!imovel) return '-';

  const base = imovel.condominio?.nome?.trim();
  const fallback = base && base.length ? base : 'Sem condomínio';
  const complemento = imovel.complemento?.trim();

  return complemento && complemento.length ? `${fallback} — ${complemento}` : fallback;
}

function formatImovelInfo(imovel: ContratoImovel): string {
  if (!imovel) return '-';

  const parts: string[] = [];

  if (imovel.codigo) {
    parts.push(`Código ${imovel.codigo}`);
  }

  if (imovel.cidade) {
    parts.push(imovel.cidade);
  }

  if (imovel.bairro) {
    parts.push(imovel.bairro);
  }

  return parts.length > 0 ? parts.join(' • ') : '-';
}

function extractDateParts(value: Nullable<string>): [string | null, string | null, string | null] {
  if (!value) return [null, null, null];

  const dateSegment = value.includes('T') ? value.split('T')[0] : value;
  const parts = dateSegment.split('-');

  if (parts.length !== 3) {
    return [null, null, null];
  }

  return parts as [string, string, string];
}

function formatCompetencia(value: Nullable<string>): string {
  const [year, month] = extractDateParts(value);

  if (!year || !month) {
    const date = value ? new Date(value) : null;
    return date && !Number.isNaN(date.getTime())
      ? date.toLocaleDateString('pt-BR', { month: '2-digit', year: 'numeric' })
      : '-';
  }

  return `${month.padStart(2, '0')}/${year}`;
}

function formatVencimento(value: Nullable<string>): string {
  const [year, month, day] = extractDateParts(value);

  if (!year || !month || !day) {
    const date = value ? new Date(value) : null;
    return date && !Number.isNaN(date.getTime())
      ? date.toLocaleDateString('pt-BR')
      : '-';
  }

  return `${day.padStart(2, '0')}/${month.padStart(2, '0')}/${year}`;
}

function formatDateTime(value: Nullable<string>): string {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return '-';
  }
  return date.toLocaleString('pt-BR');
}

function formatEmailStatus(status: Nullable<string>): string {
  if (!status) return '—';
  const normalized = status.toLowerCase();
  if (normalized === 'sent') return 'Enviado';
  if (normalized === 'failed') return 'Falhou';
  return status;
}

function formatCurrency(value: Nullable<string | number>): string {
  if (value === null || value === undefined || value === '') {
    return 'R$ 0,00';
  }

  const numericValue = typeof value === 'number' ? value : Number.parseFloat(value);

  if (Number.isNaN(numericValue)) {
    return 'R$ 0,00';
  }

  return numericValue.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

async function deleteFatura(fatura: FaturaRow) {
  const confirmed = window.confirm(
    `Deseja realmente excluir a fatura ${fatura.id}? Essa ação não pode ser desfeita.`
  );

  if (!confirmed) {
    return;
  }

  try {
    await axios.delete(`/api/faturas/${fatura.id}`);
    successMessage.value = 'Fatura excluída com sucesso.';
    await fetchFaturas(currentPage.value);
  } catch (error) {
    console.error(error);
    if (axios.isAxiosError(error) && error.response?.data?.message) {
      errorMessage.value = error.response.data.message;
    } else {
      errorMessage.value = 'Não foi possível excluir a fatura.';
    }
  }
}

onMounted(() => {
  fetchFaturas();
});
</script>

<template>
  <AuthenticatedLayout title="Faturas">
    <div class="space-y-8 text-slate-100">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-2xl font-semibold text-white">Faturas</h2>
          <p class="text-sm text-slate-400">
            Controle o faturamento mensal, vencimentos e status de recebimento.
          </p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-xl border border-emerald-500/40 bg-emerald-600/80 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-emerald-900/30 transition hover:bg-emerald-500/80 disabled:cursor-not-allowed disabled:opacity-60"
            @click="generateCurrentMonthInvoices"
            :disabled="isGenerating"
          >
            {{ isGenerating ? 'Gerando...' : 'Gerar Faturas do mês' }}
          </button>
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-xl border border-indigo-500/40 bg-indigo-600/70 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500/80"
            @click="openSingleInvoiceModal"
          >
            + Nova fatura
          </button>
        </div>
      </div>

      <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
        <form @submit.prevent="applyFilters" class="grid gap-5 lg:grid-cols-6">
          <div class="lg:col-span-2">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Busca</label>
            <input
              v-model="filters.search"
              type="search"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
              placeholder="Nosso número ou boleto"
            />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Status</label>
            <select
              v-model="filters.status"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            >
              <option value="">Todos</option>
              <option v-for="option in statusOptions" :key="option" :value="option">
                {{ option }}
              </option>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Contrato ID</label>
            <input
              v-model="filters.contrato_id"
              type="number"
              min="1"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Competência</label>
            <DatePicker v-model="filters.competencia" mode="month" placeholder="mm/aaaa" />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Competência de</label>
            <DatePicker v-model="filters.competencia_de" mode="month" placeholder="mm/aaaa" />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Competência até</label>
            <DatePicker v-model="filters.competencia_ate" mode="month" placeholder="mm/aaaa" />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Vencimento de</label>
            <DatePicker v-model="filters.vencimento_de" placeholder="dd/mm/aaaa" />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Vencimento até</label>
            <DatePicker v-model="filters.vencimento_ate" placeholder="dd/mm/aaaa" />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Registros por página</label>
            <select
              v-model.number="perPage"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            >
              <option v-for="option in perPageOptions" :key="option" :value="option">
                {{ option }}
              </option>
            </select>
          </div>
          <div class="flex items-center gap-3 lg:col-span-6">
            <button
              type="submit"
              class="rounded-xl border border-indigo-500/40 bg-indigo-600/80 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-900/30 transition hover:bg-indigo-500/80"
              :disabled="loading"
            >
              Aplicar filtros
            </button>
            <button
              type="button"
              class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800/60"
              @click="resetFilters"
              :disabled="loading"
            >
              Limpar
            </button>
          </div>
        </form>
      </section>

      <div
        v-if="successMessage"
        class="rounded-xl border border-emerald-500/40 bg-emerald-500/15 px-4 py-3 text-sm text-emerald-200"
      >
        {{ successMessage }}
      </div>

      <div
        v-if="errorMessage"
        class="rounded-xl border border-rose-500/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-200"
      >
        {{ errorMessage }}
      </div>

      <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/80 shadow-xl shadow-black/40">
        <table class="min-w-full divide-y divide-slate-800 text-sm">
          <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
            <tr>
              <th class="px-4 py-3 text-left">Contrato</th>
              <th class="px-4 py-3 text-left">Imóvel</th>
              <th class="px-4 py-3 text-left">Competência</th>
              <th class="px-4 py-3 text-left">Vencimento</th>
              <th class="px-4 py-3 text-left">Total</th>
              <th class="px-4 py-3 text-left">Anexo</th>
              <th class="px-4 py-3 text-left">Envio</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-right">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800 bg-slate-950/50 text-slate-200">
            <tr v-if="loading">
              <td colspan="9" class="px-4 py-6 text-center text-slate-400">Carregando faturas...</td>
            </tr>
            <tr v-else-if="!hasResults">
              <td colspan="9" class="px-4 py-6 text-center text-slate-400">Nenhuma fatura encontrada.</td>
            </tr>
            <tr v-else v-for="fatura in faturas" :key="fatura.id" class="hover:bg-slate-900/60">
              <td class="px-4 py-3">
                <template v-if="fatura.contrato">
                  <Link
                    :href="`/contratos/${fatura.contrato_id}/visualizar`"
                    class="inline-flex items-center gap-1 rounded-full border border-indigo-500/30 bg-indigo-500/10 px-2.5 py-0.5 text-xs font-semibold text-indigo-200 transition hover:border-indigo-400/60 hover:bg-indigo-500/20 hover:text-white"
                  >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h9l7 7v9a2 2 0 01-2 2z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v6h6" />
                    </svg>
                    {{ fatura.contrato.codigo_contrato ?? `Contrato #${fatura.contrato_id}` }}
                  </Link>
                </template>
                <span v-else class="text-xs text-slate-500">—</span>
              </td>
              <td class="px-4 py-3">
                <div class="text-slate-200">{{ formatImovelLabel(fatura.contrato?.imovel ?? null) }}</div>
                <div class="text-xs text-slate-500">{{ formatImovelInfo(fatura.contrato?.imovel ?? null) }}</div>
              </td>
              <td class="px-4 py-3 text-slate-200">{{ formatCompetencia(fatura.competencia) }}</td>
              <td class="px-4 py-3 text-slate-200">{{ formatVencimento(fatura.vencimento) }}</td>
              <td class="px-4 py-3 font-semibold text-slate-100">
                {{ formatCurrency(fatura.valor_total) }}
              </td>
              <td class="px-4 py-3">
                <template v-if="(fatura.anexos_count ?? 0) > 0">
                  <a
                    v-if="fatura.anexos?.[0]"
                    :href="fatura.anexos[0].url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex max-w-[220px] items-center gap-2 truncate rounded-lg border border-slate-700 px-3 py-1 text-xs font-semibold text-slate-200 transition hover:border-indigo-500 hover:bg-indigo-500/20 hover:text-white"
                    :title="fatura.anexos[0].display_name"
                  >
                    <svg class="h-3.5 w-3.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6a3 3 0 116 0v6" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 11V5m0 12l-3-3m3 3l3-3" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M7 20h10" />
                    </svg>
                    <span class="truncate">{{ fatura.anexos[0].display_name }}</span>
                  </a>
                  <span
                    v-else
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-3 py-1 text-xs font-semibold text-slate-200"
                  >
                    {{ fatura.anexos_count }} anexos
                  </span>
                </template>
                <span v-else class="text-xs text-slate-500">Sem anexos</span>
              </td>
              <td class="px-4 py-3 text-xs">
                <template v-if="fatura.email?.last_sent_at">
                  <span class="block font-semibold text-emerald-300">{{ formatDateTime(fatura.email.last_sent_at) }}</span>
                  <span class="block text-slate-400">Status: {{ formatEmailStatus(fatura.email.last_status) }}</span>
                </template>
                <span v-else class="text-slate-500">Nunca enviado</span>
              </td>
              <td class="px-4 py-3">
                <span
                  :class="[
                    'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold',
                    statusBadgeClasses(fatura.status),
                  ]"
                >
                  {{ fatura.status }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-2">
                  <Link
                    :href="`/faturas/${fatura.id}`"
                    :class="actionButtonClass"
                    title="Ver fatura"
                  >
                    <span class="sr-only">Ver fatura</span>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75 9.75 6.75 9.75 6.75-3.75 6.75-9.75 6.75S2.25 12 2.25 12z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                  </Link>
                  <Link
                    :href="`/faturas/${fatura.id}?mode=edit`"
                    :class="actionButtonClass"
                    title="Editar fatura"
                  >
                    <span class="sr-only">Editar fatura</span>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a1.5 1.5 0 0 1 2.121 2.121l-9.193 9.193a3 3 0 0 1-1.157.722l-3.057 1.019 1.019-3.057a3 3 0 0 1 .722-1.157l9.193-9.193z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12.75V19.5A1.5 1.5 0 0 1 18 21H5.25A1.5 1.5 0 0 1 3.75 19.5V6A1.5 1.5 0 0 1 5.25 4.5H12" />
                    </svg>
                  </Link>
                  <button
                    type="button"
                    :class="dangerActionButtonClass"
                    title="Excluir fatura"
                    @click="deleteFatura(fatura)"
                  >
                    <span class="sr-only">Excluir fatura</span>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 7V4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5V7" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7H15.75L15 19.5a1.5 1.5 0 0 1-1.494 1.401h-2.012A1.5 1.5 0 0 1 10 19.5L9.25 7" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <div
        v-if="meta"
        class="flex flex-col items-center justify-between gap-3 rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-4 text-sm text-slate-300 shadow-xl shadow-black/40 sm:flex-row"
      >
        <div>
          Mostrando página {{ meta.current_page }} de {{ meta.last_page }} - {{ meta.total }} registros
        </div>
        <div class="flex items-center gap-2">
          <button
            class="rounded-lg border border-slate-700 px-3 py-2 transition hover:bg-slate-800/70"
            :disabled="loading || meta.current_page <= 1"
            @click="changePage(meta.current_page - 1)"
          >
            Anterior
          </button>
          <button
            class="rounded-lg border border-slate-700 px-3 py-2 transition hover:bg-slate-800/70"
            :disabled="loading || meta.current_page >= meta.last_page"
            @click="changePage(meta.current_page + 1)"
          >
            Próxima
          </button>
        </div>
      </div>
    </div>
    <GenerateInvoiceModal
      :show="showSingleInvoiceModal"
      @close="closeSingleInvoiceModal"
      @generated="handleInvoiceGenerated"
    />
  </AuthenticatedLayout>
</template>
