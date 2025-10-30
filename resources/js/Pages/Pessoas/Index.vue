<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { lookupCep, normalizeCep } from '@/utils/cep';
import { useToast } from '@/composables/useToast';

type Nullable<T> = T | null;

type PessoaRow = {
  id: number;
  nome_razao_social: string;
  cpf_cnpj: Nullable<string>;
  email: Nullable<string>;
  telefone: Nullable<string>;
  tipo_pessoa: string;
  papeis: string[];
  enderecos?: { cidade: Nullable<string>; estado: Nullable<string> };
};

type MetaPagination = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

const tipoOptions = ['Fisica', 'Juridica'];
const papelOptions = [
  { value: 'Locatario', label: 'Locatário' },
  { value: 'Proprietario', label: 'Proprietário' },
  { value: 'Fiador', label: 'Fiador' },
  { value: 'Fornecedor', label: 'Fornecedor' },
  { value: 'Cliente', label: 'Cliente' },
];

const filters = reactive({
  search: '',
  tipo_pessoa: '',
  papel: '',
  cidade: '',
  estado: '',
});

const pessoas = ref<PessoaRow[]>([]);
const meta = ref<MetaPagination | null>(null);
const loading = ref(false);
const errorMessage = ref('');
const perPageOptions = [10, 15, 25, 50];
const perPage = ref(15);
const currentPage = ref(1);
const toast = useToast();

const hasResults = computed(() => pessoas.value.length > 0);

const actionButtonClass =
  'inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-700 bg-slate-900/60 text-slate-300 transition hover:border-indigo-500 hover:bg-indigo-500/20 hover:text-white';
const dangerActionButtonClass =
  'inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-500/40 bg-rose-500/10 text-rose-300 transition hover:border-rose-500 hover:bg-rose-500/20 hover:text-rose-100';

// Create modal state
const showCreateModal = ref(false);
const createSaving = ref(false);
const createError = ref('');
const createCepLoading = ref(false);
const createForm = reactive({
  nome_razao_social: '',
  tipo_pessoa: 'Fisica' as 'Fisica' | 'Juridica',
  cpf_cnpj: '',
  email: '',
  telefone: '',
  papeis: [] as string[],
  cep: '',
  estado: '',
  cidade: '',
  bairro: '',
  rua: '',
  numero: '',
  complemento: '',
});

const normalizePapeis = (value: unknown): string[] => {
  if (!Array.isArray(value)) return [];

  return value
    .map((papel) => {
      if (typeof papel !== 'string') return null;
      return papel === 'Inquilino' ? 'Locatario' : papel;
    })
    .filter((papel): papel is string => Boolean(papel));
};

function openCreate() {
  createError.value = '';
  showCreateModal.value = true;
}

function resetCreateForm() {
  createForm.nome_razao_social = '';
  createForm.tipo_pessoa = 'Fisica';
  createForm.cpf_cnpj = '';
  createForm.email = '';
  createForm.telefone = '';
  createForm.papeis = [];
  createForm.cep = '';
  createForm.estado = '';
  createForm.cidade = '';
  createForm.bairro = '';
  createForm.rua = '';
  createForm.numero = '';
  createForm.complemento = '';
}

function closeCreate() {
  showCreateModal.value = false;
  createError.value = '';
  resetCreateForm();
}

function toggleCreatePapel(papel: string) {
  if (createForm.papeis.includes(papel)) {
    createForm.papeis = createForm.papeis.filter((p) => p !== papel);
  } else {
    createForm.papeis.push(papel);
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

async function submitCreate() {
  createSaving.value = true;
  createError.value = '';
  try {
    const payload = { ...createForm, papeis: createForm.papeis };
    await axios.post('/api/pessoas', payload);
    closeCreate();
    await fetchPessoas(1);
    toast.success('Registro criado com sucesso.');
  } catch (error: any) {
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

watch(perPage, () => {
  fetchPessoas(1);
});

async function fetchPessoas(page = 1) {
  loading.value = true;
  errorMessage.value = '';

  const params: Record<string, unknown> = { page, per_page: perPage.value };
  if (filters.search) params['filter[search]'] = filters.search;
  if (filters.tipo_pessoa) params['filter[tipo_pessoa]'] = filters.tipo_pessoa;
  if (filters.papel) params['filter[papel]'] = filters.papel;
  if (filters.cidade) params['filter[cidade]'] = filters.cidade;
  if (filters.estado) params['filter[estado]'] = filters.estado;

  try {
    const { data } = await axios.get('/api/pessoas', { params });
    const rows: PessoaRow[] = data.data ?? [];
    const metaData: MetaPagination | null = data.meta ?? null;

    if (metaData && rows.length === 0 && metaData.current_page > 1) {
      await fetchPessoas(metaData.current_page - 1);
      return;
    }

    pessoas.value = rows.map((row) => ({
      ...row,
      papeis: normalizePapeis(row.papeis),
    }));
    meta.value = metaData;
    currentPage.value = metaData?.current_page ?? page;
  } catch (error) {
    console.error(error);
    errorMessage.value = 'Não foi possível carregar as pessoas.';
  } finally {
    loading.value = false;
  }
}

function applyFilters() {
  fetchPessoas(1);
}

function resetFilters() {
  filters.search = '';
  filters.tipo_pessoa = '';
  filters.papel = '';
  perPage.value = 15;
  fetchPessoas(1);
}

function changePage(page: number) {
  if (!meta.value) return;
  if (page < 1 || page > meta.value.last_page) return;
  fetchPessoas(page);
}

async function deletePessoa(pessoa: PessoaRow) {
  const confirmed = window.confirm(
    `Deseja realmente excluir ${pessoa.nome_razao_social}? Essa ação não pode ser desfeita.`
  );
  if (!confirmed) return;

  try {
    await axios.delete(`/api/pessoas/${pessoa.id}`);
    toast.success('Registro excluído com sucesso.');
    await fetchPessoas(currentPage.value);
  } catch (error: any) {
    const message = error?.response?.data?.message ?? 'Não foi possível excluir o registro.';
    toast.error(message);
  }
}

onMounted(() => {
  fetchPessoas();
});
</script>

<template>
  <AuthenticatedLayout title="Pessoas/Empresas">
    <div class="space-y-8 text-slate-100">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-2xl font-semibold text-white">Pessoas/Empresas</h2>
          <p class="text-sm text-slate-400">Gerencie pessoas físicas e jurídicas em um só lugar.</p>
        </div>

        <button
          type="button"
          @click="openCreate"
          class="inline-flex items-center justify-center rounded-xl border border-indigo-500/40 bg-indigo-600/70 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500/80"
        >
          + Nova pessoa/empresa
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
              placeholder="Nome, CPF/CNPJ, email"
            />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tipo</label>
            <select
              v-model="filters.tipo_pessoa"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            >
              <option value="">Todos</option>
              <option v-for="option in tipoOptions" :key="option" :value="option">{{ option }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Papel</label>
            <select
              v-model="filters.papel"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            >
              <option value="">Todos</option>
              <option v-for="option in papelOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
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
            <select
              v-model.number="perPage"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            >
              <option v-for="option in perPageOptions" :key="option" :value="option">{{ option }}</option>
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

      <div v-if="errorMessage" class="rounded-xl border border-rose-500/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-200">
        {{ errorMessage }}
      </div>

      <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/80 shadow-xl shadow-black/40">
        <table class="min-w-full divide-y divide-slate-800 text-sm">
          <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
            <tr>
              <th class="px-4 py-3 text-left">Nome/Razão Social</th>
              <th class="px-4 py-3 text-left">Documento</th>
              <th class="px-4 py-3 text-left">Contato</th>
              <th class="px-4 py-3 text-left">Cidade/UF</th>
              <th class="px-4 py-3 text-left">Tipo</th>
              <th class="px-4 py-3 text-left">Papéis</th>
              <th class="px-4 py-3 text-right">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800 bg-slate-950/50 text-slate-200">
            <tr v-if="loading">
              <td colspan="7" class="px-4 py-6 text-center text-slate-400">Carregando pessoas...</td>
            </tr>
            <tr v-else-if="!hasResults">
              <td colspan="7" class="px-4 py-6 text-center text-slate-400">Nenhum registro encontrado.</td>
            </tr>
            <tr v-else v-for="pessoa in pessoas" :key="pessoa.id" class="hover:bg-slate-900/60">
              <td class="px-4 py-3 font-semibold text-white">{{ pessoa.nome_razao_social }}</td>
              <td class="px-4 py-3">{{ pessoa.cpf_cnpj ?? '-' }}</td>
              <td class="px-4 py-3">
                <div class="text-slate-200">{{ pessoa.email ?? '-' }}</div>
                <div class="text-xs text-slate-500">{{ pessoa.telefone ?? '' }}</div>
              </td>
              <td class="px-4 py-3">
                <div class="text-slate-200">{{ pessoa.enderecos?.cidade ?? '-' }}</div>
                <div class="text-xs text-slate-500">{{ pessoa.enderecos?.estado ?? '-' }}</div>
              </td>
              <td class="px-4 py-3">{{ pessoa.tipo_pessoa }}</td>
              <td class="px-4 py-3">
                <span v-if="!pessoa.papeis?.length" class="text-slate-400">Sem papéis</span>
                <div v-else class="flex flex-wrap gap-1">
                  <span v-for="papel in pessoa.papeis" :key="papel" class="inline-flex items-center rounded-full bg-slate-800 px-2 py-0.5 text-xs font-semibold text-slate-200">
                    {{ papel }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-2">
                  <Link :href="`/pessoas/${pessoa.id}`" :class="actionButtonClass" title="Editar">
                    <span class="sr-only">Editar</span>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a1.5 1.5 0 0 1 2.121 2.121l-9.193 9.193a3 3 0 0 1-1.157.722l-3.057 1.019 1.019-3.057a3 3 0 0 1 .722-1.157l9.193-9.193z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12.75V19.5A1.5 1.5 0 0 1 18 21H5.25A1.5 1.5 0 0 1 3.75 19.5V6A1.5 1.5 0 0 1 5.25 4.5H12" />
                    </svg>
                  </Link>
                  <button type="button" :class="dangerActionButtonClass" title="Excluir" @click="deletePessoa(pessoa)">
                    <span class="sr-only">Excluir</span>
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
  <!-- Modal de criação -->
  <transition name="fade">
    <div
      v-if="showCreateModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur"
      @keydown.esc.prevent.stop="closeCreate"
    >
      <div class="relative w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40">
        <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
          <div>
            <h3 class="text-lg font-semibold text-white">Nova pessoa/empresa</h3>
            <p class="text-xs text-slate-400">Informe os dados para concluir o cadastro.</p>
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
                <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Dados gerais</h4>
              </header>
              <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2 flex flex-col gap-2">
                  <label class="text-sm font-medium text-slate-200">Nome / Razão social</label>
                  <input v-model="createForm.nome_razao_social" type="text" required class="rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-medium text-slate-200">Tipo</label>
                  <select v-model="createForm.tipo_pessoa" class="rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100">
                    <option value="Fisica">Fisica</option>
                    <option value="Juridica">Juridica</option>
                  </select>
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-medium text-slate-200">CPF / CNPJ</label>
                  <input v-model="createForm.cpf_cnpj" type="text" class="rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" placeholder="Somente números" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-medium text-slate-200">Email</label>
                  <input v-model="createForm.email" type="email" class="rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-medium text-slate-200">Telefone</label>
                  <input v-model="createForm.telefone" type="text" class="rounded-md border border-slate-700 bg-slate-900/60 px-3 py-2 text-slate-100" />
                </div>
                <div class="md:col-span-2">
                  <label class="text-sm font-medium text-slate-200">Papéis</label>
                  <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <label v-for="option in papelOptions" :key="option.value" class="flex items-center gap-2 text-sm text-slate-300">
                      <input type="checkbox" :value="option.value" :checked="createForm.papeis.includes(option.value)" @change="toggleCreatePapel(option.value)" class="rounded border-slate-700 text-indigo-600 focus:ring-indigo-500" />
                      {{ option.label }}
                    </label>
                  </div>
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
  </AuthenticatedLayout>
</template>

