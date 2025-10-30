<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { lookupCep, normalizeCep } from '@/utils/cep';
import { useToast } from '@/composables/useToast';

type Nullable<T> = T | null;

type CondominioRow = {
  id: number;
  nome: string;
  cnpj: Nullable<string>;
  cidade: Nullable<string>;
  estado: Nullable<string>;
  telefone: Nullable<string>;
  email: Nullable<string>;
};

type MetaPagination = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

const filters = reactive({
  search: '',
  cidade: '',
  estado: '',
});

const items = ref<CondominioRow[]>([]);
const meta = ref<MetaPagination | null>(null);
const loading = ref(false);
const errorMessage = ref('');
const perPageOptions = [10, 15, 25, 50];
const perPage = ref(15);
const currentPage = ref(1);

const hasResults = computed(() => items.value.length > 0);
const toast = useToast();

// Create modal state
const showCreateModal = ref(false);
const createSaving = ref(false);
const createError = ref('');
const createForm = reactive({
  nome: '',
  cnpj: '',
  cep: '',
  estado: '',
  cidade: '',
  bairro: '',
  rua: '',
  numero: '',
  complemento: '',
  telefone: '',
  email: '',
  observacoes: '',
});
const createCepLoading = ref(false);

function openCreate() {
  createError.value = '';
  showCreateModal.value = true;
}

function resetCreateForm() {
  createForm.nome = '';
  createForm.cnpj = '';
  createForm.cep = '';
  createForm.estado = '';
  createForm.cidade = '';
  createForm.bairro = '';
  createForm.rua = '';
  createForm.numero = '';
  createForm.complemento = '';
  createForm.telefone = '';
  createForm.email = '';
  createForm.observacoes = '';
}

function closeCreate() {
  showCreateModal.value = false;
  createError.value = '';
  resetCreateForm();
}

async function submitCreate() {
  createSaving.value = true;
  createError.value = '';
  try {
    await axios.post('/api/condominios', { ...createForm });
    closeCreate();
    await fetchItems(currentPage.value || 1);
    toast.success('Condomínio criado com sucesso.');
  } catch (error) {
    console.error(error);
    // @ts-ignore
    const data = error?.response?.data;
    if (data?.errors) {
      const first = (Object.values(data.errors).flat()[0] as string) || '';
      createError.value = first || 'Não foi possível salvar.';
    } else {
      createError.value = data?.message || 'Não foi possível salvar.';
    }
  } finally {
    createSaving.value = false;
  }
}

async function fetchCepCreate() {
  createCepLoading.value = true;
  createError.value = '';
  try {
    const cep = normalizeCep(createForm.cep);
    if (cep.length !== 8) {
      createError.value = 'Informe um CEP válido com 8 dígitos.';
      return;
    }
    const data = await lookupCep(cep);
    if (!data) {
      createError.value = 'CEP não encontrado.';
      return;
    }
    createForm.cep = data.cep;
    createForm.estado = data.uf || createForm.estado;
    createForm.cidade = data.cidade || createForm.cidade;
    createForm.bairro = (data.bairro ?? '') || createForm.bairro;
    createForm.rua = (data.logradouro ?? '') || createForm.rua;
    if (data.complemento) createForm.complemento = data.complemento;
  } finally {
    createCepLoading.value = false;
  }
}

// Edit modal state
const showEditModal = ref(false);
const editSaving = ref(false);
const editError = ref('');
const editId = ref<number | null>(null);
const editForm = reactive({
  nome: '',
  cnpj: '',
  cep: '',
  estado: '',
  cidade: '',
  bairro: '',
  rua: '',
  numero: '',
  complemento: '',
  telefone: '',
  email: '',
  observacoes: '',
});
const editCepLoading = ref(false);

function resetEditForm() {
  editForm.nome = '';
  editForm.cnpj = '';
  editForm.cep = '';
  editForm.estado = '';
  editForm.cidade = '';
  editForm.bairro = '';
  editForm.rua = '';
  editForm.numero = '';
  editForm.complemento = '';
  editForm.telefone = '';
  editForm.email = '';
  editForm.observacoes = '';
}

async function openEditModal(id: number) {
  editError.value = '';
  editSaving.value = false;
  editId.value = id;
  resetEditForm();
  try {
    const { data } = await axios.get(`/api/condominios/${id}`);
    const p = data.data ?? {};
    editForm.nome = p.nome ?? '';
    editForm.cnpj = p.cnpj ?? '';
    editForm.cep = p.cep ?? '';
    editForm.estado = p.estado ?? '';
    editForm.cidade = p.cidade ?? '';
    editForm.bairro = p.bairro ?? '';
    editForm.rua = p.rua ?? '';
    editForm.numero = p.numero ?? '';
    editForm.complemento = p.complemento ?? '';
    editForm.telefone = p.telefone ?? '';
    editForm.email = p.email ?? '';
    editForm.observacoes = p.observacoes ?? '';
  } catch (e) {
    console.error(e);
    editError.value = 'Não foi possível carregar os dados.';
  }
  showEditModal.value = true;
}

function closeEdit() {
  showEditModal.value = false;
  editError.value = '';
}

async function submitEdit() {
  if (!editId.value) return;
  editSaving.value = true;
  editError.value = '';
  try {
    await axios.put(`/api/condominios/${editId.value}`, { ...editForm });
    toast.success('Condomínio atualizado.');
    closeEdit();
    await fetchItems(currentPage.value || 1);
  } catch (error: any) {
    const data = error?.response?.data;
    if (data?.errors) {
      const first = (Object.values(data.errors).flat()[0] as string) || '';
      editError.value = first || 'Não foi possível salvar.';
    } else {
      editError.value = data?.message || 'Não foi possível salvar.';
    }
  } finally {
    editSaving.value = false;
  }
}

async function fetchCepEdit() {
  editCepLoading.value = true;
  editError.value = '';
  try {
    const cep = normalizeCep(editForm.cep);
    if (cep.length !== 8) {
      editError.value = 'Informe um CEP válido com 8 dígitos.';
      return;
    }
    const data = await lookupCep(cep);
    if (!data) {
      editError.value = 'CEP não encontrado.';
      return;
    }
    editForm.cep = data.cep;
    editForm.estado = data.uf || editForm.estado;
    editForm.cidade = data.cidade || editForm.cidade;
    editForm.bairro = (data.bairro ?? '') || editForm.bairro;
    editForm.rua = (data.logradouro ?? '') || editForm.rua;
    if (data.complemento) editForm.complemento = data.complemento;
  } finally {
    editCepLoading.value = false;
  }
}

async function deleteCondominio(row: CondominioRow) {
  const ok = window.confirm(`Excluir "${row.nome}"? Esta ação não pode ser desfeita.`);
  if (!ok) return;
  try {
    await axios.delete(`/api/condominios/${row.id}`);
    toast.success('Registro excluído com sucesso.');
    await fetchItems(currentPage.value || 1);
  } catch (error: any) {
    const message = error?.response?.data?.message ?? 'Não foi possível excluir o registro.';
    toast.error(message);
  }
}

watch(perPage, () => {
  fetchItems(1);
});

async function fetchItems(page = 1) {
  loading.value = true;
  errorMessage.value = '';

  const params: Record<string, unknown> = { page, per_page: perPage.value };
  if (filters.search) params['filter[search]'] = filters.search;
  if (filters.cidade) params['filter[cidade]'] = filters.cidade;
  if (filters.estado) params['filter[estado]'] = filters.estado;

  try {
    const { data } = await axios.get('/api/condominios', { params });
    const rows: CondominioRow[] = data.data ?? [];
    const metaData: MetaPagination | null = data.meta ?? null;

    if (metaData && rows.length === 0 && metaData.current_page > 1) {
      await fetchItems(metaData.current_page - 1);
      return;
    }

    items.value = rows;
    meta.value = metaData;
    currentPage.value = metaData?.current_page ?? page;
  } catch (error) {
    console.error(error);
    errorMessage.value = 'Não foi possível carregar os Condomínios.';
  } finally {
    loading.value = false;
  }
}

function applyFilters() {
  fetchItems(1);
}

function resetFilters() {
  filters.search = '';
  filters.cidade = '';
  filters.estado = '';
  perPage.value = 15;
  fetchItems(1);
}

function changePage(page: number) {
  if (!meta.value) return;
  if (page < 1 || page > meta.value.last_page) return;
  fetchItems(page);
}

onMounted(() => {
  fetchItems();
});
</script>

<template>
  <AuthenticatedLayout title="Condomínios">
    <div class="mb-6 flex items-center justify-between">
      <h2 class="text-2xl font-semibold text-slate-100">Condomínios</h2>
      <button type="button" @click="openCreate" class="rounded-lg border border-indigo-500/40 bg-indigo-500/20 px-3 py-2 text-sm font-semibold text-indigo-200 transition hover:border-indigo-400 hover:bg-indigo-500/30 hover:text-white">Novo</button>
    </div>

    <section class="space-y-4">
      <form @submit.prevent="applyFilters" class="grid gap-4 rounded-2xl border border-slate-800 bg-slate-900/80 p-4 shadow-xl shadow-black/40 lg:grid-cols-6">
        <div class="lg:col-span-2">
          <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Busca</label>
          <input v-model="filters.search" type="text" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40" placeholder="Nome, CNPJ, Rua..." />
        </div>
        <div>
          <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Cidade</label>
          <input v-model="filters.cidade" type="text" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40" />
        </div>
        <div>
          <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">UF</label>
          <input v-model="filters.estado" type="text" maxlength="2" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40" />
        </div>
        <div>
          <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Registros por página</label>
          <select v-model="perPage" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
            <option v-for="option in perPageOptions" :key="option" :value="option">{{ option }}</option>
          </select>
        </div>
        <div class="flex items-end gap-3 lg:col-span-2">
          <button type="submit" class="rounded-xl border border-indigo-500/40 bg-indigo-600/80 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-900/30 transition hover:bg-indigo-500/80" :disabled="loading">Aplicar filtros</button>
          <button type="button" class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800/60" @click="resetFilters" :disabled="loading">Limpar</button>
        </div>
      </form>
    </section>

    <div v-if="errorMessage" class="mt-4 rounded-xl border border-rose-500/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-200">{{ errorMessage }}</div>

    <section class="mt-4 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/80 shadow-xl shadow-black/40">
      <table class="min-w-full divide-y divide-slate-800 text-sm">
        <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
          <tr>
            <th class="px-4 py-3 text-left">Nome</th>
            <th class="px-4 py-3 text-left">CNPJ</th>
            <th class="px-4 py-3 text-left">Cidade/UF</th>
            <th class="px-4 py-3 text-left">Contato</th>
            <th class="px-4 py-3 text-right">Ações</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800 bg-slate-950/50 text-slate-200">
          <tr v-if="loading">
            <td colspan="5" class="px-4 py-6 text-center text-slate-400">Carregando...</td>
          </tr>
          <tr v-else-if="!hasResults">
            <td colspan="5" class="px-4 py-6 text-center text-slate-400">Nenhum registro encontrado.</td>
          </tr>
          <tr v-else v-for="row in items" :key="row.id" class="hover:bg-slate-900/60">
            <td class="px-4 py-3 font-semibold text-white">{{ row.nome }}</td>
            <td class="px-4 py-3 text-slate-300">{{ row.cnpj ?? '-' }}</td>
            <td class="px-4 py-3 text-slate-300">{{ row.cidade ?? '-' }}/{{ row.estado ?? '-' }}</td>
            <td class="px-4 py-3 text-slate-300">
              <div>{{ row.telefone ?? '-' }}</div>
              <div class="text-xs text-slate-500">{{ row.email ?? '' }}</div>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-2">
                <Link :href="`/condominios/${row.id}/visualizar`" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-700 bg-slate-900/60 text-slate-300 transition hover:border-indigo-500 hover:bg-indigo-500/20 hover:text-white" title="Ver Condomínio">
                  <span class="sr-only">Ver Condomínio</span>
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75 9.75 6.75 9.75 6.75-3.75 6.75-9.75 6.75S2.25 12 2.25 12z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                </Link>
                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-700 bg-slate-900/60 text-slate-300 transition hover:border-indigo-500 hover:bg-indigo-500/20 hover:text-white" title="Editar Condomínio" @click="openEditModal(row.id)">
                  <span class="sr-only">Editar Condomínio</span>
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a1.5 1.5 0 0 1 2.121 2.121l-9.193 9.193a3 3 0 0 1-1.157.722l-3.057 1.019 1.019-3.057a3 3 0 0 1 .722-1.157l9.193-9.193z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12.75V19.5A1.5 1.5 0 0 1 18 21H5.25A1.5 1.5 0 0 1 3.75 19.5V6A1.5 1.5 0 0 1 5.25 4.5H12" />
                  </svg>
                </button>
                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-500/40 bg-rose-500/10 text-rose-300 transition hover:border-rose-500 hover:bg-rose-500/20 hover:text-rose-100" title="Excluir Condomínio" @click="deleteCondominio(row)">
                  <span class="sr-only">Excluir Condomínio</span>
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

    <div v-if="meta" class="mt-4 flex flex-col items-center justify-between gap-3 rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-4 text-sm text-slate-300 shadow-xl shadow-black/40 sm:flex-row">
      <div>
        Mostrando página {{ meta.current_page }} de {{ meta.last_page }} - {{ meta.total }} registros
      </div>
      <div class="flex items-center gap-2">
        <button class="rounded-lg border border-slate-700 px-3 py-2 transition hover:bg-slate-800/70" :disabled="loading || meta.current_page <= 1" @click="changePage(meta.current_page - 1)">Anterior</button>
        <button class="rounded-lg border border-slate-700 px-3 py-2 transition hover:bg-slate-800/70" :disabled="loading || meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)">Próxima</button>
      </div>
    </div>
  </AuthenticatedLayout>
  
  <!-- Modal de criação (mesmo layout do modal de contrato) -->
  <transition name="fade">
    <div
      v-if="showCreateModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur"
      @keydown.esc.prevent.stop="closeCreate"
    >
      <div class="relative w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40">
        <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
          <div>
            <h3 class="text-lg font-semibold text-white">Novo Condomínio</h3>
            <p class="text-xs text-slate-400">Informe os dados do Condomínio para concluir o cadastro.</p>
          </div>
          <button type="button" class="rounded-md p-2 text-slate-400 transition hover:text-white" @click="closeCreate">
            <span class="sr-only">Fechar</span>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </header>
        <div class="max-h-[80vh] overflow-y-auto px-6 py-5">
          <div v-if="createError" class="mb-4 rounded-md border border-rose-500/40 bg-rose-500/15 px-4 py-2 text-sm text-rose-200">{{ createError }}</div>
          <form @submit.prevent="submitCreate" class="space-y-8">
            <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-5 shadow-inner shadow-black/20">
              <header class="flex items-center gap-3">
                <span class="h-6 w-1 rounded-full bg-indigo-500"></span>
                <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Informações gerais</h4>
              </header>
              <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2 flex flex-col gap-2">
                  <label class="text-sm font-medium text-slate-200">Nome</label>
                  <input v-model="createForm.nome" type="text" required class="rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-medium text-slate-200">CNPJ</label>
                  <input v-model="createForm.cnpj" type="text" class="rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" placeholder="Somente números" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-medium text-slate-200">Telefone</label>
                  <input v-model="createForm.telefone" type="text" class="rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-medium text-slate-200">Email</label>
                  <input v-model="createForm.email" type="email" class="rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
                </div>
              </div>
            </section>

            <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-5 shadow-inner shadow-black/20">
              <header class="flex items-center gap-3">
                <span class="h-6 w-1 rounded-full bg-emerald-500"></span>
                <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Endereço</h4>
              </header>
              <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2 flex flex-col gap-2">
                  <label class="text-sm font-medium text-slate-200">CEP</label>
                  <div class="flex items-center gap-2">
                    <input v-model="createForm.cep" type="text" class="w-full rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" placeholder="Somente números" />
                    <button type="button" @click="fetchCepCreate" :disabled="createCepLoading" class="whitespace-nowrap rounded-md border border-indigo-500/40 bg-indigo-600/80 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-500/80">
                      {{ createCepLoading ? 'Buscando...' : 'Buscar' }}
                    </button>
                  </div>
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-medium text-slate-200">Estado</label>
                  <input v-model="createForm.estado" type="text" maxlength="2" class="rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100 uppercase" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-medium text-slate-200">Cidade</label>
                  <input v-model="createForm.cidade" type="text" class="rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-medium text-slate-200">Bairro</label>
                  <input v-model="createForm.bairro" type="text" class="rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-medium text-slate-200">Rua</label>
                  <input v-model="createForm.rua" type="text" class="rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-medium text-slate-200">Número</label>
                  <input v-model="createForm.numero" type="text" class="rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
                </div>
                <div class="md:col-span-2 flex flex-col gap-2">
                  <label class="text-sm font-medium text-slate-200">Complemento / Apto</label>
                  <input v-model="createForm.complemento" type="text" class="rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
                </div>
                <div class="md:col-span-2 flex flex-col gap-2">
                  <label class="text-sm font-medium text-slate-200">Observações</label>
                  <textarea v-model="createForm.observacoes" rows="4" class="rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100"></textarea>
                </div>
              </div>
            </section>

            <div class="flex items-center justify-end gap-3">
              <button type="button" @click="closeCreate" class="rounded-md border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800/60">Cancelar</button>
              <button type="submit" :disabled="createSaving" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ createSaving ? 'Salvando...' : 'Salvar' }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </transition>

  <!-- Modal de edição (mesmo layout) -->
  <transition name="fade">
    <div
      v-if="showEditModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur"
      @keydown.esc.prevent.stop="closeEdit"
    >
      <div class="relative w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40">
        <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
          <div>
            <h3 class="text-lg font-semibold text-white">Editar Condomínio</h3>
            <p class="text-xs text-slate-400">Atualize os dados do Condomínio e salve as alterações.</p>
          </div>
          <button type="button" class="rounded-md p-2 text-slate-400 transition hover:text-white" @click="closeEdit">
            <span class="sr-only">Fechar</span>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </header>
        <div class="max-h-[80vh] overflow-y-auto px-6 py-5">
          <div v-if="editError" class="mb-4 rounded-md border border-rose-500/40 bg-rose-500/15 px-4 py-2 text-sm text-rose-200">{{ editError }}</div>
          <form @submit.prevent="submitEdit" class="grid gap-6 md:grid-cols-2">
            <section class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-slate-200">Nome</label>
                <input v-model="editForm.nome" type="text" required class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-200">CNPJ</label>
                <input v-model="editForm.cnpj" type="text" class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-200">Telefone</label>
                <input v-model="editForm.telefone" type="text" class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-200">Email</label>
                <input v-model="editForm.email" type="email" class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
              </div>
            </section>
                        <section class="space-y-4">
              <h4 class="text-sm font-semibold text-slate-300">Endereço</h4>
              <div>
                <label class="block text-sm font-medium text-slate-200">CEP</label>
                <div class="mt-1 flex items-center gap-2">
                  <input v-model="editForm.cep" type="text" class="w-full rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" placeholder="Somente números" />
                  <button type="button" @click="fetchCepEdit" :disabled="editCepLoading" class="whitespace-nowrap rounded-md border border-indigo-500/40 bg-indigo-600/80 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-500/80">
                    {{ editCepLoading ? 'Buscando...' : 'Buscar' }}
                  </button>
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-200">Estado</label>
                <input v-model="editForm.estado" type="text" maxlength="2" class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100 uppercase" />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-200">Cidade</label>
                <input v-model="editForm.cidade" type="text" class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-200">Bairro</label>
                <input v-model="editForm.bairro" type="text" class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-200">Rua</label>
                <input v-model="editForm.rua" type="text" class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-200">Número</label>
                <input v-model="editForm.numero" type="text" class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-200">Complemento / Apto</label>
                <input v-model="editForm.complemento" type="text" class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-200">Observações</label>
                <textarea v-model="editForm.observacoes" rows="4" class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100"></textarea>
              </div>
            </section>
            <div class="md:col-span-2 flex items-center justify-end gap-3">
              <button type="button" @click="closeEdit" class="rounded-md border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800/60">Cancelar</button>
              <button type="submit" :disabled="editSaving" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ editSaving ? 'Salvando...' : 'Salvar' }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </transition>
  </template>

<style scoped>
.fade-enter-active,
.fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>








