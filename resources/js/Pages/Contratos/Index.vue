<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ContratoFormModal from '@/Components/Contratos/ContratoFormModal.vue';
import { Link } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { computed, onMounted, reactive, ref, watch } from 'vue';

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
const showCreateModal = ref(false);
const perPageOptions = [10, 15, 25, 50];
const perPage = ref(15);

const hasResults = computed(() => contratos.value.length > 0);

watch(perPage, () => {
  fetchContratos(1);
});

function openCreateModal() {
  showCreateModal.value = true;
}

function closeCreateModal() {
  showCreateModal.value = false;
}

async function handleContratoCreated() {
  showCreateModal.value = false;
  await fetchContratos(1);
}

function statusBadgeClasses(status: string): string {
  switch (status) {
    case 'Ativo':
      return 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/40';
    case 'Suspenso':
      return 'bg-amber-500/15 text-amber-300 border border-amber-500/40';
    case 'Encerrado':
      return 'bg-slate-500/20 text-slate-300 border border-slate-600/40';
    default:
      return 'bg-slate-500/20 text-slate-300 border border-slate-600/40';
  }
}

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
    errorMessage.value = 'Nao foi possivel carregar os contratos.';
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
    <div class="space-y-8 text-slate-100">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-2xl font-semibold text-white">Contratos</h2>
          <p class="text-sm text-slate-400">Acompanhe contratos, vigencias e status em tempo real.</p>
        </div>

        <button
          type="button"
          class="inline-flex items-center justify-center rounded-xl border border-indigo-500/40 bg-indigo-600/70 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500/80"
          @click="openCreateModal"
        >
          + Novo contrato
        </button>
      </div>

      <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
        <form @submit.prevent="applyFilters" class="grid gap-5 lg:grid-cols-6">
          <div class="lg:col-span-2">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Busca</label>
            <input
              v-model="filters.search"
              type="search"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
              placeholder="Codigo do contrato"
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
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Cidade do imovel</label>
            <input
              v-model="filters.cidade"
              type="text"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Imovel ID</label>
            <input
              v-model="filters.imovel_id"
              type="number"
              min="1"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Locador ID</label>
            <input
              v-model="filters.locador_id"
              type="number"
              min="1"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Locatario ID</label>
            <input
              v-model="filters.locatario_id"
              type="number"
              min="1"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Dia de vencimento</label>
            <input
              v-model="filters.dia_vencimento"
              type="number"
              min="1"
              max="28"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Vigencia em</label>
            <input
              v-model="filters.vigencia_em"
              type="date"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Registros por pagina</label>
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
        v-if="errorMessage"
        class="rounded-xl border border-rose-500/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-200"
      >
        {{ errorMessage }}
      </div>

      <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/80 shadow-xl shadow-black/40">
        <table class="min-w-full divide-y divide-slate-800 text-sm">
          <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
            <tr>
              <th class="px-4 py-3 text-left">Codigo</th>
              <th class="px-4 py-3 text-left">Imovel</th>
              <th class="px-4 py-3 text-left">Locador</th>
              <th class="px-4 py-3 text-left">Locatario</th>
              <th class="px-4 py-3 text-left">Inicio / Fim</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-right">Acoes</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800 bg-slate-950/50 text-slate-200">
            <tr v-if="loading">
              <td colspan="7" class="px-4 py-6 text-center text-slate-400">
                Carregando contratos...
              </td>
            </tr>
            <tr v-else-if="!hasResults">
              <td colspan="7" class="px-4 py-6 text-center text-slate-400">
                Nenhum contrato encontrado.
              </td>
            </tr>
            <tr v-else v-for="contrato in contratos" :key="contrato.id" class="hover:bg-slate-900/60">
              <td class="px-4 py-3 font-semibold text-white">{{ contrato.codigo_contrato }}</td>
              <td class="px-4 py-3">
                <div class="text-slate-200">{{ contrato.imovel?.codigo ?? '-' }}</div>
                <div class="text-xs text-slate-500">{{ contrato.imovel?.cidade ?? '-' }}</div>
              </td>
              <td class="px-4 py-3 text-slate-200">{{ contrato.locador?.nome_razao_social ?? '-' }}</td>
              <td class="px-4 py-3 text-slate-200">{{ contrato.locatario?.nome_razao_social ?? '-' }}</td>
              <td class="px-4 py-3">
                <div class="text-slate-200">{{ contrato.data_inicio }}</div>
                <div class="text-xs text-slate-500">{{ contrato.data_fim ?? 'Sem data fim' }}</div>
              </td>
              <td class="px-4 py-3">
                <span
                  :class="[
                    'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold',
                    statusBadgeClasses(contrato.status),
                  ]"
                >
                  {{ contrato.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <Link
                  :href="`/contratos/${contrato.id}`"
                  class="rounded-lg border border-indigo-500/40 bg-indigo-500/20 px-3 py-1.5 text-xs font-semibold text-indigo-200 transition hover:border-indigo-400 hover:text-white"
                >
                  Editar
                </Link>
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
          Mostrando pagina {{ meta.current_page }} de {{ meta.last_page }} -
          {{ meta.total }} registros
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
            Proxima
          </button>
        </div>
      </div>
    </div>
    <ContratoFormModal :show="showCreateModal" @close="closeCreateModal" @created="handleContratoCreated" />
  </AuthenticatedLayout>
</template>





