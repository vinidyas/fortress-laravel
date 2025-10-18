<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ContratoFormModal from '@/Components/Contratos/ContratoFormModal.vue';
import axios from '@/bootstrap';
import type { AxiosError } from 'axios';
import { useToast } from '@/composables/useToast';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import DatePicker from '@/Components/Form/DatePicker.vue';
import { Link } from '@inertiajs/vue3';

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
    complemento: Nullable<string>;
    condominio: Nullable<{ id: number; nome: Nullable<string> }>;
  }>;
  locador: Nullable<{ id: number; nome_razao_social: string }>;
  locatario: Nullable<{ id: number; nome_razao_social: string }>;
};

type MetaPagination = { current_page: number; last_page: number; per_page: number; total: number };

const statusOptions = ['Ativo', 'EmAnalise', 'Suspenso', 'Encerrado', 'Rescindido'];

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
const currentPage = ref(1);
const toast = useToast();
const modalState = reactive({ visible: false, mode: 'create' as 'create' | 'edit', contratoId: null as number | null });

const hasResults = computed(() => contratos.value.length > 0);

const actionButtonClass = 'inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-700 bg-slate-900/60 text-slate-300 transition hover:border-indigo-500 hover:bg-indigo-500/20 hover:text-white';
const dangerActionButtonClass = 'inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-500/40 bg-rose-500/10 text-rose-300 transition hover:border-rose-500 hover:bg-rose-500/20 hover:text-rose-100';

watch(perPage, () => { fetchContratos(1); });

function openCreateModal() { modalState.mode = 'create'; modalState.contratoId = null; modalState.visible = true; }
function openEditModal(id: number) { modalState.mode = 'edit'; modalState.contratoId = id; modalState.visible = true; }
function handleModalClose() { modalState.visible = false; if (modalState.mode === 'edit') modalState.contratoId = null; }
async function handleModalSaved() { const targetPage = modalState.mode === 'create' ? 1 : currentPage.value; await fetchContratos(targetPage); }

function statusBadgeClasses(status: string): string {
  switch (status) {
    case 'Ativo': return 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/40';
    case 'Suspenso': return 'bg-amber-500/15 text-amber-300 border border-amber-500/40';
    case 'Encerrado': return 'bg-slate-500/20 text-slate-300 border border-slate-600/40';
    case 'Rescindido': return 'bg-rose-500/15 text-rose-300 border border-rose-500/40';
    case 'EmAnalise': return 'bg-indigo-500/15 text-indigo-200 border border-indigo-500/40';
    default: return 'bg-slate-500/20 text-slate-300 border border-slate-600/40';
  }
}

async function fetchContratos(page = 1) {
  loading.value = true;
  errorMessage.value = '';
  const params: Record<string, unknown> = { page, per_page: perPage.value };
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
    const rows = data.data ?? [];
    const metaData: MetaPagination | null = data.meta ?? null;
    if (metaData && rows.length === 0 && metaData.current_page > 1) { await fetchContratos(metaData.current_page - 1); return; }
    contratos.value = rows;
    meta.value = metaData;
    currentPage.value = metaData?.current_page ?? page;
  } catch (error) {
    console.error(error);
    errorMessage.value = 'Não foi possível carregar os contratos.';
  } finally {
    loading.value = false;
  }
}

function applyFilters() { fetchContratos(1); }
function resetFilters() {
  Object.assign(filters, { search: '', status: '', cidade: '', imovel_id: '', locador_id: '', locatario_id: '', dia_vencimento: '', vigencia_em: '' });
  perPage.value = 15; fetchContratos(1);
}
function changePage(page: number) { if (!meta.value) return; if (page < 1 || page > meta.value.last_page) return; fetchContratos(page); }

function formatImovelLabel(imovel: ContratoRow['imovel']): string {
  if (!imovel) return '-';

  const base = imovel.condominio?.nome?.trim();
  const fallback = base && base.length ? base : 'Sem condomínio';
  const complemento = imovel.complemento?.trim();

  return complemento && complemento.length ? `${fallback} — ${complemento}` : fallback;
}

function formatImovelInfo(imovel: ContratoRow['imovel']): string {
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

async function deleteContrato(contrato: ContratoRow) {
  const confirmed = window.confirm(`Deseja realmente excluir o contrato ${contrato.codigo_contrato}? Essa ação não pode ser desfeita.`);
  if (!confirmed) return;
  try {
    await axios.delete(`/api/contratos/${contrato.id}`);
    toast.success('Contrato excluído com sucesso.');
    await fetchContratos(currentPage.value);
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string }>;
    const message = axiosError.response?.data?.message ?? 'Não foi possível excluir o contrato.';
    toast.error(message);
  }
}

onMounted(() => { fetchContratos(); });
</script>

<template>
  <AuthenticatedLayout title="Contratos">
    <div class="space-y-8 text-slate-100">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-2xl font-semibold text-white">Contratos</h2>
          <p class="text-sm text-slate-400">Acompanhe contratos, vigências e status em tempo real.</p>
        </div>

        <button type="button" class="inline-flex items-center justify-center rounded-xl border border-indigo-500/40 bg-indigo-600/70 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500/80" @click="openCreateModal">
          + Novo contrato
        </button>
      </div>

      <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
        <form @submit.prevent="applyFilters" class="grid gap-5 lg:grid-cols-6">
          <div class="lg:col-span-2">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Busca</label>
            <input v-model="filters.search" type="search" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40" placeholder="Código do contrato" />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Status</label>
            <select v-model="filters.status" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
              <option value="">Todos</option>
              <option v-for="option in statusOptions" :key="option" :value="option">{{ option }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Cidade do imóvel</label>
            <input v-model="filters.cidade" type="text" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40" />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Imóvel ID</label>
            <input v-model="filters.imovel_id" type="number" min="1" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40" />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Locador ID</label>
            <input v-model="filters.locador_id" type="number" min="1" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40" />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Locatário ID</label>
            <input v-model="filters.locatario_id" type="number" min="1" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40" />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Dia de vencimento</label>
            <input v-model="filters.dia_vencimento" type="number" min="1" max="28" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40" />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Vigência em</label>
            <DatePicker v-model="filters.vigencia_em" placeholder="dd/mm/aaaa" />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Registros por página</label>
            <select v-model.number="perPage" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
              <option v-for="option in perPageOptions" :key="option" :value="option">{{ option }}</option>
            </select>
          </div>
          <div class="flex items-center gap-3 lg:col-span-6">
            <button type="submit" class="rounded-xl border border-indigo-500/40 bg-indigo-600/80 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-900/30 transition hover:bg-indigo-500/80" :disabled="loading">Aplicar filtros</button>
            <button type="button" class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800/60" @click="resetFilters" :disabled="loading">Limpar</button>
          </div>
        </form>
      </section>

      <div v-if="errorMessage" class="rounded-xl border border-rose-500/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-200">{{ errorMessage }}</div>

      <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/80 shadow-xl shadow-black/40">
        <table class="min-w-full divide-y divide-slate-800 text-sm">
          <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
            <tr>
              <th class="px-4 py-3 text-left">Código</th>
              <th class="px-4 py-3 text-left">Imóvel</th>
              <th class="px-4 py-3 text-left">Locador</th>
              <th class="px-4 py-3 text-left">Locatário</th>
              <th class="px-4 py-3 text-left">Início / Fim</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-right">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800 bg-slate-950/50 text-slate-200">
            <tr v-if="loading">
              <td colspan="7" class="px-4 py-6 text-center text-slate-400">Carregando contratos...</td>
            </tr>
            <tr v-else-if="!hasResults">
              <td colspan="7" class="px-4 py-6 text-center text-slate-400">Nenhum contrato encontrado.</td>
            </tr>
            <tr v-else v-for="contrato in contratos" :key="contrato.id" class="hover:bg-slate-900/60">
              <td class="px-4 py-3 font-semibold text-white">{{ contrato.codigo_contrato }}</td>
              <td class="px-4 py-3">
                <div class="text-slate-200">{{ formatImovelLabel(contrato.imovel) }}</div>
                <div class="text-xs text-slate-500">{{ formatImovelInfo(contrato.imovel) }}</div>
              </td>
              <td class="px-4 py-3 text-slate-200">{{ contrato.locador?.nome_razao_social ?? '-' }}</td>
              <td class="px-4 py-3 text-slate-200">{{ contrato.locatario?.nome_razao_social ?? '-' }}</td>
              <td class="px-4 py-3">
                <div class="text-slate-200">{{ contrato.data_inicio }}</div>
                <div class="text-xs text-slate-500">{{ contrato.data_fim ?? 'Sem data fim' }}</div>
              </td>
              <td class="px-4 py-3">
                <span :class="['inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold', statusBadgeClasses(contrato.status)]">{{ contrato.status }}</span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-2">
                  <Link
                    :href="`/contratos/${contrato.id}/visualizar`"
                    :class="actionButtonClass"
                    title="Ver contrato"
                  >
                    <span class="sr-only">Ver contrato</span>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75 9.75 6.75 9.75 6.75-3.75 6.75-9.75 6.75S2.25 12 2.25 12z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                  </Link>
                  <button type="button" :class="actionButtonClass" title="Editar contrato" @click="openEditModal(contrato.id)">
                    <span class="sr-only">Editar contrato</span>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a1.5 1.5 0 0 1 2.121 2.121l-9.193 9.193a3 3 0 0 1-1.157.722l-3.057 1.019 1.019-3.057a3 3 0 0 1 .722-1.157l9.193-9.193z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12.75V19.5A1.5 1.5 0 0 1 18 21H5.25A1.5 1.5 0 0 1 3.75 19.5V6A1.5 1.5 0 0 1 5.25 4.5H12" />
                    </svg>
                  </button>
                  <button type="button" :class="dangerActionButtonClass" title="Excluir contrato" @click="deleteContrato(contrato)">
                    <span class="sr-only">Excluir contrato</span>
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

      <div v-if="meta" class="flex flex-col items-center justify-between gap-3 rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-4 text-sm text-slate-300 shadow-xl shadow-black/40 sm:flex-row">
        <div>
          Mostrando página {{ meta.current_page }} de {{ meta.last_page }} - {{ meta.total }} registros
        </div>
        <div class="flex items-center gap-2">
          <button class="rounded-lg border border-slate-700 px-3 py-2 transition hover:bg-slate-800/70" :disabled="loading || meta.current_page <= 1" @click="changePage(meta.current_page - 1)">Anterior</button>
          <button class="rounded-lg border border-slate-700 px-3 py-2 transition hover:bg-slate-800/70" :disabled="loading || meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)">Próxima</button>
        </div>
      </div>
    </div>
    <ContratoFormModal :show="modalState.visible" :mode="modalState.mode" :contrato-id="modalState.contratoId" @close="handleModalClose" @saved="handleModalSaved" />
  </AuthenticatedLayout>
</template>
