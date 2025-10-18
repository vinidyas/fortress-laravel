<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ImovelFormModal from '@/Components/Imoveis/ImovelFormModal.vue';
import ImovelDetailsModal from '@/Components/Imoveis/ImovelDetailsModal.vue';
import CondominioFormModal from '@/Components/Condominios/CondominioFormModal.vue';
import ContratoFormModal from '@/Components/Contratos/ContratoFormModal.vue';
import type { ImovelFormDraft } from '@/Components/Imoveis/ImovelForm.vue';
import type { CondominioFormDraft, CondominioSavedPayload } from '@/Components/Condominios/CondominioForm.vue';
import axios from '@/bootstrap';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { useToast } from '@/composables/useToast';

type Nullable<T> = T | null;

type ImovelRow = {
  id: number;
  codigo: string;
  tipo_imovel: string;
  disponibilidade: string;
  enderecos: {
    cidade: Nullable<string>;
    bairro: Nullable<string>;
    rua: Nullable<string>;
    logradouro: Nullable<string>;
    complemento: Nullable<string>;
  };
  valores: {
    valor_locacao: Nullable<string | number>;
  };
  condominio?: { id: number; nome: string } | null;
  caracteristicas: {
    dormitorios: Nullable<number>;
    vagas_garagem: Nullable<number>;
  };
  anexos_count?: number;
  contratos?: Array<{
    id: number;
    codigo_contrato?: string;
    status?: string;
  }>;
};

type MetaPagination = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

type PessoaResumo = {
  id: number;
  nome_razao_social: string;
};

type AnexoResumo = {
  id: number;
  display_name: string;
  original_name: string;
  mime_type?: string | null;
  uploaded_at?: string | null;
  uploaded_by?: { id: number; name: string } | null;
  url: string;
};

type ImovelDetalhe = {
  id: number;
  codigo: string;
  tipo_imovel: string;
  finalidade: string[];
  disponibilidade: string;
  enderecos: {
    cep: Nullable<string>;
    estado: Nullable<string>;
    cidade: Nullable<string>;
    bairro: Nullable<string>;
    rua: Nullable<string>;
    logradouro: Nullable<string>;
    numero: Nullable<string>;
    complemento: Nullable<string>;
  };
  valores: {
    valor_locacao: Nullable<string | number>;
    valor_condominio: Nullable<string | number>;
    valor_iptu: Nullable<string | number>;
    condominio_isento: boolean;
    iptu_isento: boolean;
    outros_valores: Nullable<string | number>;
    outros_isento: boolean;
    periodo_iptu: Nullable<string>;
  };
  caracteristicas: {
    dormitorios: Nullable<number>;
    suites: Nullable<number>;
    banheiros: Nullable<number>;
    vagas_garagem: Nullable<number>;
    area_total: Nullable<string | number>;
    area_construida: Nullable<string | number>;
    comodidades: string[];
  };
  proprietario: PessoaResumo | null;
  agenciador: PessoaResumo | null;
  responsavel: PessoaResumo | null;
  condominio: { id: number; nome: string } | null;
  anexos?: AnexoResumo[];
  created_at?: string | null;
  updated_at?: string | null;
};

const finalidadeOptions = [
  { value: 'Locacao', label: 'Locação' },
  { value: 'Venda', label: 'Venda' },
];

const disponibilidadeOptions = [
  { value: 'Disponivel', label: 'Disponível' },
  { value: 'Indisponivel', label: 'Indisponível' },
];

const filters = reactive({
  search: '',
  tipo_imovel: '',
  cidade: '',
  disponibilidade: '',
  finalidade: [] as string[],
});

const imoveis = ref<ImovelRow[]>([]);
const meta = ref<MetaPagination | null>(null);
const loading = ref(false);
const errorMessage = ref('');
const perPageOptions = [10, 15, 25, 50];
const perPage = ref(15);
const currentPage = ref(1);
const toast = useToast();
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showCondominioModal = ref(false);
const showDetailsModal = ref(false);
const imovelDraft = ref<ImovelFormDraft | null>(null);
const condominioDraft = ref<CondominioFormDraft | null>(null);
const editingImovelId = ref<number | null>(null);
const detailsLoading = ref(false);
const selectedImovel = ref<ImovelDetalhe | null>(null);
const pendingReopenContext = ref<'create' | 'edit' | null>(null);
const contratoModalState = reactive({ visible: false, contratoId: null as number | null });

function createEmptyImovelDraft(): ImovelFormDraft {
  return {
    form: {
      codigo: '',
      proprietario_id: null,
      agenciador_id: null,
      responsavel_id: null,
      tipo_imovel: '',
      finalidade: [],
      disponibilidade: 'Disponivel',
      cep: '',
      estado: '',
      cidade: '',
      bairro: '',
      rua: '',
      condominio_id: null,
      numero: '',
      complemento: '',
      valor_locacao: '',
      valor_condominio: '',
      condominio_isento: false,
      valor_iptu: '',
      iptu_isento: false,
      outros_valores: '',
      outros_isento: false,
      periodo_iptu: 'Mensal',
      dormitorios: null,
      suites: null,
      banheiros: null,
      vagas_garagem: null,
      area_total: '',
      area_construida: '',
      comodidades: [],
    },
    condominioSearchTerm: '',
    proprietarioSearchTerm: '',
  };
}

function createCondominioDraft(nome = ''): CondominioFormDraft {
  return {
    form: {
      nome,
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
    },
  };
}

function cloneImovelFormDraft(source: ImovelFormDraft): ImovelFormDraft {
  return JSON.parse(JSON.stringify(source)) as ImovelFormDraft;
}

const currencyFormatter = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
});

function formatCurrency(value: Nullable<string | number>): string {
  if (value === null || value === undefined || value === '') {
    return '-';
  }

  const numeric = typeof value === 'number' ? value : Number.parseFloat(String(value));

  if (Number.isNaN(numeric)) {
    return '-';
  }

  return currencyFormatter.format(numeric);
}

function availabilityLabel(value: string): string {
  const normalized = value ? value.toLowerCase() : '';

  if (normalized === 'disponível') {
    return 'Disponível';
  }

  if (normalized === 'indisponível') {
    return 'Indisponível';
  }

  return value;
}

function availabilityClasses(value: string): string {
  const normalized = availabilityLabel(value);

  return normalized === 'Disponível'
    ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30'
    : 'bg-rose-500/15 text-rose-300 border border-rose-500/30';
}

// Helpers using canonical values
function dispLabel(value: string): string {
  if (value === 'Disponivel') return 'Disponível';
  if (value === 'Indisponivel') return 'Indisponível';
  return availabilityLabel(value);
}

function dispClasses(value: string): string {
  return value === 'Disponivel'
    ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30'
    : 'bg-rose-500/15 text-rose-300 border border-rose-500/30';
}

const actionButtonClass =
  'inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-700 bg-slate-900/60 text-slate-300 transition hover:border-indigo-500 hover:bg-indigo-500/20 hover:text-white';
const dangerActionButtonClass =
  'inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-500/40 bg-rose-500/10 text-rose-300 transition hover:border-rose-500 hover:bg-rose-500/20 hover:text-rose-100';

async function deleteImovel(imovel: ImovelRow) {
  const ok = window.confirm(`Excluir "${imovel.codigo}"? Esta ação não pode ser desfeita.`);
  if (!ok) return;
  try {
    await axios.delete(`/api/imoveis/${imovel.id}`);
    toast.success('Registro excluído com sucesso.');
    await fetchImoveis(meta.value?.current_page ?? 1);
  } catch (error: any) {
    const status = error?.response?.status;
    const message = error?.response?.data?.message ?? '';

    if (status === 409 || (status === 400 && message.includes('contrato'))) {
      toast.error('Não é possível excluir: existe contrato vinculado a este imóvel.');
      return;
    }

    const friendly =
      status === 403
        ? 'Você não tem permissão para excluir este imóvel.'
        : status === 404
          ? 'Imóvel não encontrado. Atualize a página.'
          : 'Não foi possível excluir o registro.';

    toast.error(friendly);
  }
}

async function fetchImoveis(page = 1): Promise<void> {
  loading.value = true;
  errorMessage.value = '';

  const params: Record<string, unknown> = {
    page,
    per_page: perPage.value,
  };

  if (filters.search) params['filter[search]'] = filters.search;
  if (filters.tipo_imovel) params['filter[tipo_imovel]'] = filters.tipo_imovel;
  if (filters.disponibilidade) params['filter[disponibilidade]'] = filters.disponibilidade;
  if (filters.cidade) params['filter[cidade]'] = filters.cidade;
  if (filters.finalidade.length > 0) params['filter[finalidade]'] = filters.finalidade;

  try {
    const { data } = await axios.get('/api/imoveis', { params });
    imoveis.value = data.data ?? [];
    meta.value = data.meta ?? null;
    currentPage.value = data.meta?.current_page ?? page;
  } catch (error) {
    console.error(error);
    errorMessage.value = 'Não foi possível carregar os imóveis.';
    imoveis.value = [];
    meta.value = null;
  } finally {
    loading.value = false;
  }
}

function applyFilters(): void {
  fetchImoveis(1);
}

function resetFilters(): void {
  filters.search = '';
  filters.tipo_imovel = '';
  filters.cidade = '';
  filters.disponibilidade = '';
  filters.finalidade = [];
  perPage.value = 15;
  fetchImoveis(1);
}

function toggleFinalidade(value: string): void {
  if (filters.finalidade.includes(value)) {
    filters.finalidade = filters.finalidade.filter((item) => item !== value);
  } else {
    filters.finalidade.push(value);
  }
}

function changePage(page: number): void {
  if (!meta.value) return;
  if (page < 1 || page > meta.value.last_page) return;

  fetchImoveis(page);
}

const hasResults = computed(() => imoveis.value.length > 0);

function openCreateModal(): void {
  condominioDraft.value = null;
  showCreateModal.value = true;
}

function closeCreateModal(): void {
  showCreateModal.value = false;
  imovelDraft.value = null;
  condominioDraft.value = null;
}

async function handleCreateSaved(): Promise<void> {
  showCreateModal.value = false;
  imovelDraft.value = null;
  condominioDraft.value = null;
  await fetchImoveis(1);
}

function openEditModal(id: number): void {
  editingImovelId.value = id;
  showEditModal.value = true;
}

function closeEditModal(): void {
  showEditModal.value = false;
  editingImovelId.value = null;
}

async function handleEditSaved(): Promise<void> {
  showEditModal.value = false;
  await fetchImoveis(currentPage.value);
  editingImovelId.value = null;
}

function openContratoModal(id: number): void {
  contratoModalState.visible = true;
  contratoModalState.contratoId = id;
}

function closeContratoModal(): void {
  contratoModalState.visible = false;
  contratoModalState.contratoId = null;
}

async function handleContratoSaved(): Promise<void> {
  contratoModalState.visible = false;
  await fetchImoveis(currentPage.value);
  contratoModalState.contratoId = null;
}

function openDetailsModal(id: number): void {
  showDetailsModal.value = true;
  detailsLoading.value = true;
  selectedImovel.value = null;

  axios
    .get(`/api/imoveis/${id}`)
    .then((response) => {
      selectedImovel.value = response?.data?.data ?? null;
    })
    .catch((error) => {
      console.error(error);
      toast.error('Não foi possível carregar os detalhes do imóvel.');
      showDetailsModal.value = false;
    })
    .finally(() => {
      detailsLoading.value = false;
    });
}

function closeDetailsModal(): void {
  showDetailsModal.value = false;
  detailsLoading.value = false;
  selectedImovel.value = null;
}

function reopenPendingForm(): void {
  const context = pendingReopenContext.value;
  pendingReopenContext.value = null;

  if (context === 'create') {
    nextTick(() => {
      showCreateModal.value = true;
    });
  } else if (context === 'edit') {
    nextTick(() => {
      showEditModal.value = true;
    });
  }
}

function handleRequestCreateCondominioFromCreate(draft: ImovelFormDraft): void {
  imovelDraft.value = cloneImovelFormDraft(draft);
  condominioDraft.value = createCondominioDraft(draft.condominioSearchTerm ?? '');
  pendingReopenContext.value = 'create';
  showCreateModal.value = false;
  showEditModal.value = false;
  nextTick(() => {
    showCondominioModal.value = true;
  });
}

function handleRequestCreateCondominioFromEdit(draft: ImovelFormDraft): void {
  condominioDraft.value = createCondominioDraft(draft.condominioSearchTerm ?? '');
  pendingReopenContext.value = 'edit';
  showEditModal.value = false;
  nextTick(() => {
    showCondominioModal.value = true;
  });
}

function handleCondominioSaved(payload: CondominioSavedPayload): void {
  if (pendingReopenContext.value === 'create') {
    if (!imovelDraft.value) {
      imovelDraft.value = createEmptyImovelDraft();
    }

    imovelDraft.value.form.condominio_id = payload.id;
    imovelDraft.value.condominioSearchTerm = payload.nome ?? '';
    imovelDraft.value.form.cep = payload.cep ?? '';
    imovelDraft.value.form.estado = payload.estado ?? '';
    imovelDraft.value.form.cidade = payload.cidade ?? '';
    imovelDraft.value.form.bairro = payload.bairro ?? '';
    imovelDraft.value.form.rua = payload.rua ?? imovelDraft.value.form.rua;
    imovelDraft.value.form.numero = payload.numero ?? imovelDraft.value.form.numero;
    imovelDraft.value.form.complemento = payload.complemento ?? imovelDraft.value.form.complemento;
  } else {
    toast.success('Condomínio criado. Selecione-o na busca do imóvel.');
  }

  condominioDraft.value = null;
  showCondominioModal.value = false;
  reopenPendingForm();
}

function handleCondominioClose(): void {
  showCondominioModal.value = false;
  condominioDraft.value = null;
  reopenPendingForm();
}

watch(perPage, () => {
  fetchImoveis(1);
});

onMounted(() => {
  fetchImoveis();
});
</script>

<template>
  <AuthenticatedLayout title="Imóveis">
    <div class="space-y-8 text-slate-100">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-2xl font-semibold text-white">Imóveis</h2>
          <p class="text-sm text-slate-400">Gerencie o portifolio com filtros completos.</p>
        </div>

        <button
          type="button"
          class="inline-flex items-center justify-center rounded-xl border border-indigo-500/40 bg-indigo-600/70 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500/80"
          @click="openCreateModal"
        >
          + Novo imóvel
        </button>
      </div>

      <section
        class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
      >
        <form @submit.prevent="applyFilters" class="grid gap-5 lg:grid-cols-6">
          <div class="lg:col-span-2">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400"
              >Busca</label
            >
            <input
              v-model="filters.search"
              type="search"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
              placeholder="Código, nome do condomínio, complemento ou valor de locação"
            />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tipo</label>
            <input
              v-model="filters.tipo_imovel"
              type="text"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
              placeholder="Apartamento, Casa..."
            />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400"
              >Cidade</label
            >
            <input
              v-model="filters.cidade"
              type="text"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400"
              >Disponibilidade</label
            >
            <select
              v-model="filters.disponibilidade"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            >
              <option value="">Todas</option>
              <option
                v-for="option in disponibilidadeOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400"
              >Registros por página</label
            >
            <select
              v-model="perPage"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            >
              <option v-for="option in perPageOptions" :key="option" :value="option">
                {{ option }}
              </option>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400"
              >Finalidade</label
            >
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
              <label
                v-for="option in finalidadeOptions"
                :key="option.value"
                class="flex items-center gap-2 text-sm text-slate-200"
              >
                <input
                  type="checkbox"
                  :value="option.value"
                  :checked="filters.finalidade.includes(option.value)"
                  @change="toggleFinalidade(option.value)"
                  class="rounded border-slate-600 bg-slate-800 text-indigo-500 focus:ring-indigo-500"
                />
                {{ option.label }}
              </label>
            </div>
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

      <section
        class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/80 shadow-xl shadow-black/40"
      >
        <table class="min-w-full divide-y divide-slate-800 text-sm">
          <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
            <tr>
              <th class="px-4 py-3 text-left">Código</th>
              <th class="px-4 py-3 text-left">Imóvel</th>
              <th class="px-4 py-3 text-left">Tipo</th>
              <th class="px-4 py-3 text-left">Anexos</th>
              <th class="px-4 py-3 text-left">Contratos</th>
              <th class="px-4 py-3 text-left">Cidade / Bairro</th>
              <th class="px-4 py-3 text-left">Locação</th>
              <th class="px-4 py-3 text-left">Dorms / Vagas</th>
              <th class="px-4 py-3 text-left">Disponibilidade</th>
              <th class="px-4 py-3 text-right">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800 bg-slate-950/50 text-slate-200">
            <tr v-if="loading">
              <td colspan="7" class="px-4 py-6 text-center text-slate-400">
                Carregando imóveis...
              </td>
            </tr>
            <tr v-else-if="!hasResults">
              <td colspan="7" class="px-4 py-6 text-center text-slate-400">
                Nenhum imóvel encontrado.
              </td>
            </tr>
            <tr v-else v-for="imovel in imoveis" :key="imovel.id" class="hover:bg-slate-900/60">
              <td class="px-4 py-3 font-semibold text-white">{{ imovel.codigo }}</td>
              <td class="px-4 py-3 text-slate-300">
                {{ imovel.condominio?.nome ?? 'Sem condomínio' }}
                <template v-if="imovel.enderecos.complemento">
                  — {{ imovel.enderecos.complemento }}
                </template>
              </td>
              <td class="px-4 py-3 text-slate-300">{{ imovel.tipo_imovel }}</td>
              <td class="px-4 py-3">
                <svg
                  v-if="(imovel.anexos_count ?? 0) > 0"
                  class="h-4 w-4 text-indigo-300"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M12 12V4m0 0l-4 4m4-4l4 4" />
                </svg>
                <span v-else class="text-xs text-slate-500">—</span>
              </td>
              <td class="px-4 py-3 text-slate-300">
                <template v-if="imovel.contratos?.length">
                  <div class="flex flex-wrap gap-2">
                    <button
                      v-for="contrato in imovel.contratos"
                      :key="contrato.id"
                      type="button"
                      class="inline-flex items-center gap-1 rounded-full border border-indigo-500/30 bg-indigo-500/10 px-2.5 py-0.5 text-xs font-semibold text-indigo-200 transition hover:border-indigo-400/60 hover:bg-indigo-500/20 hover:text-white"
                      @click="openContratoModal(contrato.id)"
                    >
                      <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h9l7 7v9a2 2 0 01-2 2z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v6h6" />
                      </svg>
                      {{ contrato.codigo_contrato ?? `Contrato #${contrato.id}` }}
                    </button>
                  </div>
                </template>
                <span v-else class="text-xs text-slate-500">—</span>
              </td>
              <td class="px-4 py-3">
                <div class="text-slate-200">{{ imovel.enderecos.cidade ?? '-' }}</div>
                <div class="text-xs text-slate-500">{{ imovel.enderecos.bairro ?? '-' }}</div>
              </td>
              <td class="px-4 py-3 text-slate-200">
                {{ formatCurrency(imovel.valores.valor_locacao) }}
              </td>
              <td class="px-4 py-3 text-slate-200">
                {{ imovel.caracteristicas.dormitorios ?? 0 }} /
                {{ imovel.caracteristicas.vagas_garagem ?? 0 }}
              </td>
              <td class="px-4 py-3">
                <span
                  :class="[
                    'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold',
                    dispClasses(imovel.disponibilidade),
                  ]"
                >
                  {{ dispLabel(imovel.disponibilidade) }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-2">
                  <button
                    type="button"
                    :class="actionButtonClass"
                    title="Ver imóvel"
                    @click="openDetailsModal(imovel.id)"
                  >
                    <span class="sr-only">Ver imóvel</span>
                    <svg
                      class="h-4 w-4"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M2.25 12s3.75-6.75 9.75-6.75 9.75 6.75 9.75 6.75-3.75 6.75-9.75 6.75S2.25 12 2.25 12z"
                      />
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                      />
                    </svg>
                  </button>
                  <button
                    type="button"
                    :class="actionButtonClass"
                    title="Editar imóvel"
                    @click="openEditModal(imovel.id)"
                  >
                    <span class="sr-only">Editar imóvel</span>
                    <svg
                      class="h-4 w-4"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M16.862 3.487a1.5 1.5 0 0 1 2.121 2.121l-9.193 9.193a3 3 0 0 1-1.157.722l-3.057 1.019 1.019-3.057a3 3 0 0 1 .722-1.157l9.193-9.193z"
                      />
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M19.5 12.75V19.5A1.5 1.5 0 0 1 18 21H5.25A1.5 1.5 0 0 1 3.75 19.5V6A1.5 1.5 0 0 1 5.25 4.5H12"
                      />
                    </svg>
                  </button>
                  <button
                    type="button"
                    :class="dangerActionButtonClass"
                    title="Excluir imóvel"
                    @click="deleteImovel(imovel)"
                  >
                    <span class="sr-only">Excluir imóvel</span>
                    <svg
                      class="h-4 w-4"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12" />
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 7V4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5V7"
                      />
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M8.25 7H15.75L15 19.5a1.5 1.5 0 0 1-1.494 1.401h-2.012A1.5 1.5 0 0 1 10 19.5L9.25 7"
                      />
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
        Mostrando página {{ meta.current_page }} de {{ meta.last_page }} -
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
            Próxima
          </button>
        </div>
      </div>
    </div>
    <ImovelFormModal
      :show="showCreateModal"
      mode="create"
      :draft="imovelDraft"
      @close="closeCreateModal"
      @saved="handleCreateSaved"
      @request-create-condominio="handleRequestCreateCondominioFromCreate"
    />
    <ImovelFormModal
      :show="showEditModal"
      mode="edit"
      :imovel-id="editingImovelId ?? null"
      @close="closeEditModal"
      @saved="handleEditSaved"
      @request-create-condominio="handleRequestCreateCondominioFromEdit"
    />
    <CondominioFormModal
      :show="showCondominioModal"
      mode="create"
      :draft="condominioDraft"
      @close="handleCondominioClose"
      @saved="handleCondominioSaved"
    />
    <ImovelDetailsModal
      :show="showDetailsModal"
      :loading="detailsLoading"
      :imovel="selectedImovel"
      @close="closeDetailsModal"
    />
    <ContratoFormModal
      :show="contratoModalState.visible"
      mode="edit"
      :contrato-id="contratoModalState.contratoId"
      @close="closeContratoModal"
      @saved="handleContratoSaved"
    />
  </AuthenticatedLayout>
</template>
