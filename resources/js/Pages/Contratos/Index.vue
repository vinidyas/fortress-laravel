<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { onMounted, reactive, ref } from 'vue';

type Nullable<T> = T | null;

type ContratoRow = {
  id: number;
  codigo_contrato: string;
  status: string;
  data_inicio: string;
  data_fim: Nullable<string>;
  dia_vencimento: number;
  valor_aluguel: string;
  imovel: Nullable<{
    id: number;
    codigo: string;
    cidade: Nullable<string>;
    bairro: Nullable<string>;
  }>;
  locador: Nullable<{ id: number; nome_razao_social: string }>;
  locatario: Nullable<{ id: number; nome_razao_social: string }>;
};

type MetaPagination = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

const statusOptions = ['Ativo', 'Suspenso', 'Encerrado'];

const filters = reactive({
  search: '',
  status: '',
  cidade: '',
  imovel_id: '',
  locador_id: '',
  locatario_id: '',
  dia_vencimento: '',
  vigencia_em: '',
});

const contratos = ref<ContratoRow[]>([]);
const meta = ref<MetaPagination | null>(null);
const loading = ref(false);
const errorMessage = ref('');
const perPageOptions = [10, 15, 25, 50];
const perPage = ref(15);

async function fetchContratos(page = 1) {
  loading.value = true;
  errorMessage.value = '';

  const params: Record<string, unknown> = {
    page,
    per_page: perPage.value,
  };

  if (filters.search) params['filter[search]'] = filters.search;
  if (filters.status) params['filter[status]'] = filters.status;
  if (filters.cidade) params['filter[cidade]'] = filters.cidade;
  if (filters.imovel_id) params['filter[imovel_id]'] = filters.imovel_id;
  if (filters.locador_id) params['filter[locador_id]'] = filters.locador_id;
  if (filters.locatario_id) params['filter[locatario_id]'] = filters.locatario_id;
  if (filters.dia_vencimento) params['filter[dia_vencimento]'] = filters.dia_vencimento;
  if (filters.vigencia_em) params['filter[vigencia_em]'] = filters.vigencia_em;

  try {
    const { data } = await axios.get('/api/contratos', { params });
    contratos.value = data.data;
    meta.value = data.meta;
  } catch (error) {
    console.error(error);
    errorMessage.value = 'Não foi possível carregar os contratos.';
  } finally {
    loading.value = false;
  }
}

function applyFilters() {
  fetchContratos(1);
}

function resetFilters() {
  Object.assign(filters, {
    search: '',
    status: '',
    cidade: '',
    imovel_id: '',
    locador_id: '',
    locatario_id: '',
    dia_vencimento: '',
    vigencia_em: '',
  });
  perPage.value = 15;
  fetchContratos(1);
}

function changePage(page: number) {
  if (!meta.value) return;
  if (page < 1 || page > meta.value.last_page) return;
  fetchContratos(page);
}

onMounted(() => {
  fetchContratos();
});
</script>

<template>
  <AuthenticatedLayout title="Contratos">
    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <h2 class="text-2xl font-semibold text-slate-900">Contratos</h2>
      <Link
        class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
        href="/contratos/novo"
      >
        + Novo
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
            placeholder="Código"
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
          <label class="block text-sm font-medium text-slate-700">Cidade do imóvel</label>
          <input
            v-model="filters.cidade"
            type="text"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Imóvel ID</label>
          <input
            v-model="filters.imovel_id"
            type="number"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Locador ID</label>
          <input
            v-model="filters.locador_id"
            type="number"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Locatário ID</label>
          <input
            v-model="filters.locatario_id"
            type="number"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Dia vencimento</label>
          <input
            v-model="filters.dia_vencimento"
            type="number"
            min="1"
            max="28"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Vigência em</label>
          <input
            v-model="filters.vigencia_em"
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
              Código
            </th>
            <th
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
            >
              Imóvel
            </th>
            <th
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
            >
              Locador
            </th>
            <th
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
            >
              Locatário
            </th>
            <th
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
            >
              Inicio / Fim
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
            <td colspan="7" class="px-4 py-6 text-center text-slate-500">
              Carregando contratos...
            </td>
          </tr>
          <tr v-else-if="!contratos.length">
            <td colspan="7" class="px-4 py-6 text-center text-slate-500">
              Nenhum contrato encontrado.
            </td>
          </tr>
          <tr v-for="contrato in contratos" :key="contrato.id">
            <td class="px-4 py-3 font-medium text-slate-900">{{ contrato.codigo_contrato }}</td>
            <td class="px-4 py-3">
              <div>{{ contrato.imovel?.codigo ?? '-' }}</div>
              <div class="text-xs text-slate-500">{{ contrato.imovel?.cidade ?? '' }}</div>
            </td>
            <td class="px-4 py-3">{{ contrato.locador?.nome_razao_social ?? '-' }}</td>
            <td class="px-4 py-3">{{ contrato.locatario?.nome_razao_social ?? '-' }}</td>
            <td class="px-4 py-3">
              <div>{{ contrato.data_inicio }}</div>
              <div class="text-xs text-slate-500">{{ contrato.data_fim ?? 'Sem data fim' }}</div>
            </td>
            <td class="px-4 py-3">
              <span
                :class="[
                  'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                  contrato.status === 'Ativo'
                    ? 'bg-emerald-100 text-emerald-700'
                    : contrato.status === 'Suspenso'
                      ? 'bg-amber-100 text-amber-700'
                      : 'bg-slate-200 text-slate-700',
                ]"
              >
                {{ contrato.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <Link
                :href="`/contratos/${contrato.id}`"
                class="text-sm font-semibold text-indigo-600 hover:text-indigo-500"
                >Editar</Link
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
