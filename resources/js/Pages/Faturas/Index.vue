<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { onMounted, reactive, ref } from 'vue';

type Nullable<T> = T | null;

type FaturaRow = {
  id: number;
  contrato_id: number;
  status: string;
  competencia: string;
  vencimento: string;
  valor_total: string;
  valor_pago: Nullable<string>;
  contrato?: {
    codigo_contrato?: string;
    imovel?: {
      codigo?: string;
      cidade?: Nullable<string>;
    } | null;
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
const perPageOptions = [10, 15, 25, 50];
const perPage = ref(15);

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
    faturas.value = data.data;
    meta.value = data.meta;
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

function changePage(page: number) {
  if (!meta.value) return;
  if (page < 1 || page > meta.value.last_page) return;
  fetchFaturas(page);
}

onMounted(() => {
  fetchFaturas();
});
</script>

<template>
  <AuthenticatedLayout title="Faturas">
    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <h2 class="text-2xl font-semibold text-slate-900">Faturas</h2>
      <Link
        class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
        href="/faturas/novo"
      >
        + Nova fatura
      </Link>
    </div>

    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
      <form @submit.prevent="applyFilters" class="grid gap-4 md:grid-cols-4">
        <div>
          <label class="block text-sm font-medium text-slate-700">Busca</label>
          <input
            v-model="filters.search"
            type="search"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
            placeholder="Nosso número ou boleto"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Status</label>
          <select
            v-model="filters.status"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
          >
            <option value="">Todos</option>
            <option v-for="option in statusOptions" :key="option" :value="option">
              {{ option }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Contrato ID</label>
          <input
            v-model="filters.contrato_id"
            type="number"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Competência</label>
          <input
            v-model="filters.competencia"
            type="month"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Competência de</label>
          <input
            v-model="filters.competencia_de"
            type="month"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Competência até</label>
          <input
            v-model="filters.competencia_ate"
            type="month"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Vencimento de</label>
          <input
            v-model="filters.vencimento_de"
            type="date"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Vencimento até</label>
          <input
            v-model="filters.vencimento_ate"
            type="date"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Registros por página</label>
          <select
            v-model.number="perPage"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
            @change="applyFilters"
          >
            <option v-for="option in perPageOptions" :key="option" :value="option">
              {{ option }}
            </option>
          </select>
        </div>
        <div class="flex items-center gap-3 md:col-span-4">
          <button
            type="submit"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
            :disabled="loading"
          >
            Aplicar filtros
          </button>
          <button
            type="button"
            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            @click="resetFilters"
            :disabled="loading"
          >
            Limpar
          </button>
        </div>
      </form>
    </div>

    <div
      v-if="errorMessage"
      class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
    >
      {{ errorMessage }}
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
      <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
          <tr>
            <th
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
            >
              Contrato
            </th>
            <th
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
            >
              Imóvel
            </th>
            <th
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
            >
              Competência
            </th>
            <th
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
            >
              Vencimento
            </th>
            <th
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
            >
              Total
            </th>
            <th
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
            >
              Status
            </th>
            <th
              class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"
            >
              Ações
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
          <tr v-if="loading">
            <td colspan="7" class="px-4 py-6 text-center text-slate-500">Carregando faturas...</td>
          </tr>
          <tr v-else-if="!faturas.length">
            <td colspan="7" class="px-4 py-6 text-center text-slate-500">
              Nenhuma fatura encontrada.
            </td>
          </tr>
          <tr v-for="fatura in faturas" :key="fatura.id">
            <td class="px-4 py-3">
              <div>{{ fatura.contrato?.codigo_contrato ?? '-' }}</div>
              <div class="text-xs text-slate-500">ID {{ fatura.contrato_id }}</div>
            </td>
            <td class="px-4 py-3">
              <div>{{ fatura.contrato?.imovel?.codigo ?? '-' }}</div>
              <div class="text-xs text-slate-500">{{ fatura.contrato?.imovel?.cidade ?? '' }}</div>
            </td>
            <td class="px-4 py-3">{{ fatura.competencia }}</td>
            <td class="px-4 py-3">{{ fatura.vencimento }}</td>
            <td class="px-4 py-3">R$ {{ Number(fatura.valor_total).toFixed(2) }}</td>
            <td class="px-4 py-3">
              <span
                :class="[
                  'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                  fatura.status === 'Aberta'
                    ? 'bg-amber-100 text-amber-700'
                    : fatura.status === 'Paga'
                      ? 'bg-emerald-100 text-emerald-700'
                      : 'bg-rose-100 text-rose-700',
                ]"
              >
                {{ fatura.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <Link
                :href="`/faturas/${fatura.id}`"
                class="text-sm font-semibold text-indigo-600 hover:text-indigo-500"
                >Visualizar</Link
              >
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-if="meta"
      class="mt-4 flex flex-col items-center justify-between gap-3 text-sm text-slate-600 md:flex-row"
    >
      <div>
        Mostrando página {{ meta.current_page }} de {{ meta.last_page }} -
        {{ meta.total }} registros
      </div>
      <div class="flex items-center gap-2">
        <button
          class="rounded-md border border-slate-300 px-3 py-1 hover:bg-slate-50"
          :disabled="loading || meta.current_page <= 1"
          @click="changePage(meta.current_page - 1)"
        >
          Anterior
        </button>
        <button
          class="rounded-md border border-slate-300 px-3 py-1 hover:bg-slate-50"
          :disabled="loading || meta.current_page >= meta.last_page"
          @click="changePage(meta.current_page + 1)"
        >
          Próxima
        </button>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
