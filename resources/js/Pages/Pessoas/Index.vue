<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { onMounted, reactive, ref } from 'vue';

type Nullable<T> = T | null;

type PessoaRow = {
  id: number;
  nome_razao_social: string;
  cpf_cnpj: Nullable<string>;
  email: Nullable<string>;
  telefone: Nullable<string>;
  tipo_pessoa: string;
  papeis: string[];
};

type MetaPagination = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

const tipoOptions = ['Fisica', 'Juridica'];
const papelOptions = [
  'Proprietario',
  'Inquilino',
  'Fiador',
  'Corretor',
  'Fornecedor',
  'Funcionario',
];

const filters = reactive({
  search: '',
  tipo_pessoa: '',
  papel: '',
});

const pessoas = ref<PessoaRow[]>([]);
const meta = ref<MetaPagination | null>(null);
const loading = ref(false);
const errorMessage = ref('');
const perPageOptions = [10, 15, 25, 50];
const perPage = ref(15);

async function fetchPessoas(page = 1) {
  loading.value = true;
  errorMessage.value = '';

  const params: Record<string, unknown> = {
    page,
    per_page: perPage.value,
  };

  if (filters.search) params['filter[search]'] = filters.search;
  if (filters.tipo_pessoa) params['filter[tipo_pessoa]'] = filters.tipo_pessoa;
  if (filters.papel) params['filter[papel]'] = filters.papel;

  try {
    const { data } = await axios.get('/api/pessoas', { params });
    pessoas.value = data.data;
    meta.value = data.meta;
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

onMounted(() => {
  fetchPessoas();
});
</script>

<template>
  <AuthenticatedLayout title="Pessoas">
    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <h2 class="text-2xl font-semibold text-slate-900">Pessoas</h2>
      <Link
        class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
        href="/pessoas/novo"
      >
        + Nova Pessoa
      </Link>
    </div>

    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
      <form @submit.prevent="applyFilters" class="grid gap-4 md:grid-cols-5">
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-slate-700">Busca</label>
          <input
            v-model="filters.search"
            type="search"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
            placeholder="Nome, CPF/CNPJ, email"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Tipo</label>
          <select
            v-model="filters.tipo_pessoa"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
          >
            <option value="">Todos</option>
            <option v-for="option in tipoOptions" :key="option" :value="option">
              {{ option }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Papel</label>
          <select
            v-model="filters.papel"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
          >
            <option value="">Todos</option>
            <option v-for="option in papelOptions" :key="option" :value="option">
              {{ option }}
            </option>
          </select>
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
        <div class="flex items-center gap-3 md:col-span-5">
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
              Nome
            </th>
            <th
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
            >
              Documento
            </th>
            <th
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
            >
              Contato
            </th>
            <th
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
            >
              Tipo
            </th>
            <th
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
            >
              Papéis
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
            <td colspan="6" class="px-4 py-6 text-center text-slate-500">Carregando pessoas...</td>
          </tr>
          <tr v-else-if="!pessoas.length">
            <td colspan="6" class="px-4 py-6 text-center text-slate-500">
              Nenhuma pessoa encontrada.
            </td>
          </tr>
          <tr v-for="pessoa in pessoas" :key="pessoa.id">
            <td class="px-4 py-3 font-medium text-slate-900">{{ pessoa.nome_razao_social }}</td>
            <td class="px-4 py-3">{{ pessoa.cpf_cnpj ?? '-' }}</td>
            <td class="px-4 py-3">
              <div>{{ pessoa.email ?? '-' }}</div>
              <div class="text-xs text-slate-500">{{ pessoa.telefone ?? '' }}</div>
            </td>
            <td class="px-4 py-3">{{ pessoa.tipo_pessoa }}</td>
            <td class="px-4 py-3">
              <span v-if="!pessoa.papeis?.length" class="text-slate-400">Sem papéis</span>
              <div v-else class="flex flex-wrap gap-1">
                <span
                  v-for="papel in pessoa.papeis"
                  :key="papel"
                  class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700"
                >
                  {{ papel }}
                </span>
              </div>
            </td>
            <td class="px-4 py-3 text-right">
              <Link
                :href="`/pessoas/${pessoa.id}`"
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
