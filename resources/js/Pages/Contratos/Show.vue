<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { computed, onMounted, ref, watch } from 'vue';

type Nullable<T> = T | null;

type PessoaResumo = {
  id: number;
  nome_razao_social: string;
};

type ImovelResumo = {
  id: number;
  codigo: string;
  cidade: Nullable<string>;
  bairro: Nullable<string>;
};

type FiadorResumo = PessoaResumo;

type ContaCobrancaResumo = {
  id: number;
  nome: string;
};

type AnexoResumo = {
  id: number;
  original_name: string;
  mime_type?: Nullable<string>;
  url: string;
};

type TabKey = 'contrato' | 'faturas';

type FaturaResumo = {
  id: number;
  competencia: Nullable<string>;
  vencimento: Nullable<string>;
  status: string;
  valor_total: Nullable<string | number>;
  valor_pago: Nullable<string | number>;
  nosso_numero: Nullable<string>;
  boleto_url: Nullable<string>;
};

type ContratoDetalhe = {
  id: number;
  codigo_contrato: string;
  imovel_id: number;
  locador_id: number;
  locatario_id: number;
  data_inicio: string;
  data_fim: Nullable<string>;
  dia_vencimento: number;
  prazo_meses: Nullable<number>;
  carencia_meses: Nullable<number>;
  data_entrega_chaves: Nullable<string>;
  valor_aluguel: Nullable<string>;
  desconto_mensal: Nullable<string>;
  reajuste_indice: Nullable<string>;
  reajuste_periodicidade_meses: Nullable<number>;
  data_proximo_reajuste: Nullable<string>;
  garantia_tipo: Nullable<string>;
  caucao_valor: Nullable<string>;
  taxa_adm_percentual: Nullable<string>;
  multa_atraso_percentual: Nullable<string>;
  juros_mora_percentual_mes: Nullable<string>;
  repasse_automatico: Nullable<boolean>;
  conta_cobranca_id: Nullable<number>;
  forma_pagamento_preferida: Nullable<string>;
  tipo_contrato: Nullable<string>;
  status: Nullable<string>;
  observacoes: Nullable<string>;
  created_at: string;
  updated_at: string;
  imovel: Nullable<ImovelResumo>;
  locador: Nullable<PessoaResumo>;
  locatario: Nullable<PessoaResumo>;
  fiadores: FiadorResumo[];
  conta_cobranca: Nullable<ContaCobrancaResumo>;
  anexos: AnexoResumo[];
};

const props = defineProps<{ contratoId: number }>();

const loading = ref(true);
const errorMessage = ref('');
const contrato = ref<ContratoDetalhe | null>(null);
const activeTab = ref<TabKey>('contrato');
const faturas = ref<FaturaResumo[]>([]);
const faturasLoading = ref(false);
const faturasError = ref('');
const hasLoadedFaturas = ref(false);

const statusLabels: Record<string, string> = {
  Ativo: 'Ativo',
  EmAnalise: 'Em análise',
  Suspenso: 'Suspenso',
  Encerrado: 'Encerrado',
  Rescindido: 'Rescindido',
};

const garantiaLabels: Record<string, string> = {
  Fiador: 'Fiador',
  Seguro: 'Seguro',
  Caucao: 'Caução',
  SemGarantia: 'Sem garantia',
};

const formaPagamentoLabels: Record<string, string> = {
  Boleto: 'Boleto',
  Pix: 'Pix',
  Deposito: 'Depósito',
  Transferencia: 'Transferência',
  CartaoCredito: 'Cartão de crédito',
  Dinheiro: 'Dinheiro',
};

const statusBadgeClass = computed(() => {
  if (!contrato.value?.status) return 'bg-slate-500/20 text-slate-300 border border-slate-600/40';
  return badgeClassForStatus(contrato.value.status);
});

const statusLabel = computed(() => {
  const key = contrato.value?.status ?? '';
  return statusLabels[key] ?? key;
});
const isContratoTab = computed(() => activeTab.value === 'contrato');
const hasFaturas = computed(() => faturas.value.length > 0);

watch(
  () => props.contratoId,
  () => {
    activeTab.value = 'contrato';
    contrato.value = null;
    faturas.value = [];
    faturasError.value = '';
    hasLoadedFaturas.value = false;
    loadContrato();
  }
);
watch(
  () => activeTab.value,
  (tab) => {
    if (tab === 'faturas') {
      void loadFaturas();
    }
  }
);

function badgeClassForStatus(status: string): string {
  switch (status) {
    case 'Ativo':
      return 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/40';
    case 'Suspenso':
      return 'bg-amber-500/15 text-amber-300 border border-amber-500/40';
    case 'Encerrado':
      return 'bg-slate-500/20 text-slate-300 border border-slate-600/40';
    case 'Rescindido':
      return 'bg-rose-500/15 text-rose-300 border border-rose-500/40';
    case 'EmAnalise':
      return 'bg-indigo-500/15 text-indigo-200 border border-indigo-500/40';
    default:
      return 'bg-slate-500/20 text-slate-300 border border-slate-600/40';
  }
}

function formatDate(value: Nullable<string>): string {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }
  return new Intl.DateTimeFormat('pt-BR').format(date);
}

function formatCurrency(value: Nullable<string | number>): string {
  if (value === null || value === undefined || value === '') return '-';
  const numeric = typeof value === 'number' ? value : Number(value);
  if (Number.isNaN(numeric)) return '-';
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(numeric);
}

function formatPercent(value: Nullable<string | number>): string {
  if (value === null || value === undefined || value === '') return '-';
  const numeric = typeof value === 'number' ? value : Number(value);
  if (Number.isNaN(numeric)) return '-';
  return `${numeric.toFixed(2)}%`;
}

function formatBoolean(value: Nullable<boolean>): string {
  return value ? 'Sim' : 'Não';
}

function formatCompetencia(value: Nullable<string>): string {
  if (!value) return '-';
  if (/^\d{4}-\d{2}$/.test(value)) {
    const [year, month] = value.split('-');
    return `${month}/${year}`;
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }

  const month = String(date.getMonth() + 1).padStart(2, '0');
  const year = date.getFullYear();
  return `${month}/${year}`;
}

function garantiaLabel(value: Nullable<string>): string {
  if (!value) return '-';
  return garantiaLabels[value] ?? value;
}

function formaPagamentoLabel(value: Nullable<string>): string {
  if (!value) return '-';
  return formaPagamentoLabels[value] ?? value;
}

async function loadContrato() {
  loading.value = true;
  errorMessage.value = '';
  try {
    const response = await axios.get(`/api/contratos/${props.contratoId}`);
    contrato.value = response.data?.data ?? null;
  } catch (error) {
    console.error(error);
    errorMessage.value = 'Não foi possível carregar os dados do contrato.';
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  loadContrato();
});

function tabButtonClasses(tab: TabKey): string {
  const base =
    'inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400/60 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900';
  if (activeTab.value === tab) {
    return `${base} border-indigo-500/60 bg-indigo-500/20 text-white shadow shadow-indigo-900/40`;
  }
  return `${base} border-transparent text-slate-300 hover:border-indigo-500/50 hover:bg-indigo-500/10 hover:text-white`;
}

function selectTab(tab: TabKey) {
  if (activeTab.value === tab) return;
  activeTab.value = tab;
}

function faturaStatusBadgeClass(status: string): string {
  switch (status) {
    case 'Aberta':
      return 'bg-amber-500/15 text-amber-300 border border-amber-500/40';
    case 'Paga':
      return 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/40';
    case 'Cancelada':
      return 'bg-rose-500/15 text-rose-300 border border-rose-500/40';
    default:
      return 'bg-slate-500/20 text-slate-300 border border-slate-600/40';
  }
}

async function loadFaturas(force = false) {
  if (faturasLoading.value) return;
  if (!force && hasLoadedFaturas.value) return;

  faturasLoading.value = true;
  faturasError.value = '';

  try {
    const { data } = await axios.get('/api/faturas', {
      params: {
        'filter[contrato_id]': props.contratoId,
        per_page: 50,
      },
    });

    const rows: FaturaResumo[] = data.data ?? [];
    faturas.value = rows;
    hasLoadedFaturas.value = true;
  } catch (error) {
    console.error(error);
    faturasError.value = 'Não foi possível carregar as faturas do contrato.';
  } finally {
    faturasLoading.value = false;
  }
}

function reloadFaturas() {
  void loadFaturas(true);
}
</script>

<template>
  <AuthenticatedLayout :title="contrato ? `Contrato ${contrato.codigo_contrato}` : 'Detalhes do contrato'">
    <Head :title="contrato ? `Contrato ${contrato.codigo_contrato}` : 'Detalhes do contrato'" />

    <div class="space-y-8 text-slate-100">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-2xl font-semibold text-white">
            {{ contrato ? `Contrato ${contrato.codigo_contrato}` : 'Detalhes do contrato' }}
          </h2>
          <p class="text-sm text-slate-400">Visualize todas as informações cadastradas para este contrato.</p>
        </div>
        <Link
          href="/contratos"
          class="inline-flex items-center justify-center rounded-xl border border-slate-700 bg-slate-800/60 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-indigo-500 hover:bg-indigo-500/20 hover:text-white"
        >
          Voltar para listagem
        </Link>
      </div>

      <div
        v-if="errorMessage"
        class="rounded-xl border border-rose-500/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-200"
      >
        {{ errorMessage }}
      </div>

      <div
        v-if="loading"
        class="rounded-2xl border border-slate-800 bg-slate-900/80 px-6 py-12 text-center text-sm text-slate-300 shadow-xl shadow-black/40"
      >
        Carregando dados do contrato...
      </div>

      <template v-else-if="contrato">
        <section class="space-y-6 rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
          <header class="flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold" :class="statusBadgeClass">
              {{ statusLabel }}
            </span>
            <span class="text-sm text-slate-400">Código interno: <span class="font-semibold text-slate-200">{{ contrato.codigo_contrato }}</span></span>
          </header>
          <nav class="flex flex-wrap gap-2 border-b border-slate-800/60 pb-2 pt-2">
            <button type="button" :class="tabButtonClasses('contrato')" @click="selectTab('contrato')">Contrato</button>
            <button type="button" :class="tabButtonClasses('faturas')" @click="selectTab('faturas')">Faturas</button>
          </nav>

          <div v-if="isContratoTab" class="space-y-6 pt-4">
            <div class="grid gap-8 lg:grid-cols-2">
              <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Identificação</h3>
                <dl class="mt-4 space-y-3 text-sm">
                  <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Imóvel</dt>
                    <dd class="font-medium text-white">
                      <template v-if="contrato.imovel">
                        {{ contrato.imovel.codigo }}
                        <span class="text-xs text-slate-400">• {{ contrato.imovel.cidade ?? 'Cidade não informada' }}</span>
                      </template>
                      <template v-else>-</template>
                    </dd>
                  </div>
                  <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Locador</dt>
                    <dd class="font-medium text-white">{{ contrato.locador?.nome_razao_social ?? '-' }}</dd>
                  </div>
                  <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Locatário</dt>
                    <dd class="font-medium text-white">{{ contrato.locatario?.nome_razao_social ?? '-' }}</dd>
                  </div>
                  <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Fiadores</dt>
                    <dd>
                      <template v-if="contrato.fiadores?.length">
                        <ul class="list-inside list-disc space-y-1 text-slate-200">
                          <li v-for="fiador in contrato.fiadores" :key="fiador.id">{{ fiador.nome_razao_social }}</li>
                        </ul>
                      </template>
                      <template v-else>
                        <span class="text-slate-400">Nenhum fiador cadastrado.</span>
                      </template>
                    </dd>
                  </div>
                </dl>
              </div>

              <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Vigência</h3>
                <dl class="mt-4 space-y-3 text-sm">
                  <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Início</dt>
                    <dd class="font-medium text-white">{{ formatDate(contrato.data_inicio) }}</dd>
                  </div>
                  <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Fim</dt>
                    <dd class="font-medium text-white">{{ contrato.data_fim ? formatDate(contrato.data_fim) : 'Sem data fim' }}</dd>
                  </div>
                  <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Dia de vencimento</dt>
                    <dd class="font-medium text-white">Dia {{ contrato.dia_vencimento }}</dd>
                  </div>
                  <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                      <dt class="text-xs uppercase tracking-wide text-slate-500">Prazo (meses)</dt>
                      <dd class="font-medium text-white">{{ contrato.prazo_meses ?? '-' }}</dd>
                    </div>
                    <div>
                      <dt class="text-xs uppercase tracking-wide text-slate-500">Carência (meses)</dt>
                      <dd class="font-medium text-white">{{ contrato.carencia_meses ?? '-' }}</dd>
                    </div>
                  </div>
                  <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                      <dt class="text-xs uppercase tracking-wide text-slate-500">Entrega das chaves</dt>
                      <dd class="font-medium text-white">{{ formatDate(contrato.data_entrega_chaves) }}</dd>
                    </div>
                    <div>
                      <dt class="text-xs uppercase tracking-wide text-slate-500">Próximo reajuste</dt>
                      <dd class="font-medium text-white">{{ formatDate(contrato.data_proximo_reajuste) }}</dd>
                    </div>
                  </div>
                </dl>
              </div>
            </div>
          </div>
          <div v-else class="space-y-4 pt-4">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/50 p-6">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Faturas do contrato</h3>
                <button
                  type="button"
                  class="inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-900/80 px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:border-indigo-500 hover:bg-indigo-500/20 hover:text-white disabled:cursor-not-allowed disabled:border-slate-700/40 disabled:text-slate-500"
                  :disabled="faturasLoading"
                  @click="reloadFaturas"
                >
                  Atualizar lista
                </button>
              </div>
              <div class="mt-4 space-y-4">
                <div
                  v-if="faturasLoading"
                  class="rounded-xl border border-slate-800 bg-slate-900/80 px-4 py-6 text-center text-sm text-slate-300"
                >
                  Carregando faturas do contrato...
                </div>
                <div
                  v-else-if="faturasError"
                  class="rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-4 text-sm text-rose-200"
                >
                  <p>{{ faturasError }}</p>
                  <button
                    type="button"
                    class="mt-3 inline-flex items-center gap-2 rounded-lg border border-rose-400/70 px-3 py-1.5 text-xs font-semibold text-rose-100 transition hover:border-rose-300 hover:bg-rose-500/20 hover:text-white"
                    @click="reloadFaturas"
                  >
                    Tentar novamente
                  </button>
                </div>
                <div v-else-if="hasFaturas">
                  <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-800 text-sm">
                      <thead class="text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                          <th class="px-3 py-2 text-left font-semibold">Competência</th>
                          <th class="px-3 py-2 text-left font-semibold">Vencimento</th>
                          <th class="px-3 py-2 text-left font-semibold">Status</th>
                          <th class="px-3 py-2 text-left font-semibold">Valor total</th>
                          <th class="px-3 py-2 text-left font-semibold">Valor pago</th>
                          <th class="px-3 py-2 text-left font-semibold">Referência</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-800 text-slate-200">
                        <tr
                          v-for="fatura in faturas"
                          :key="fatura.id"
                          class="transition hover:bg-slate-800/30"
                        >
                          <td class="px-3 py-2 font-medium text-white">
                            {{ formatCompetencia(fatura.competencia) }}
                          </td>
                          <td class="px-3 py-2">
                            {{ formatDate(fatura.vencimento) }}
                          </td>
                          <td class="px-3 py-2">
                            <span
                              class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                              :class="faturaStatusBadgeClass(fatura.status)"
                            >
                              {{ fatura.status }}
                            </span>
                          </td>
                          <td class="px-3 py-2">
                            {{ formatCurrency(fatura.valor_total) }}
                          </td>
                          <td class="px-3 py-2">
                            {{ formatCurrency(fatura.valor_pago) }}
                          </td>
                          <td class="px-3 py-2">
                            <div v-if="fatura.nosso_numero || fatura.boleto_url" class="space-y-1">
                              <span v-if="fatura.nosso_numero" class="block text-xs text-slate-400">
                                Nosso nº {{ fatura.nosso_numero }}
                              </span>
                              <a
                                v-if="fatura.boleto_url"
                                :href="fatura.boleto_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center rounded-md border border-indigo-500/40 px-3 py-1 text-xs font-semibold text-indigo-200 transition hover:border-indigo-400 hover:bg-indigo-500/20 hover:text-white"
                              >
                                Ver boleto
                              </a>
                            </div>
                            <span v-else class="text-xs text-slate-500">-</span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <p v-else class="text-sm text-slate-400">Nenhuma fatura vinculada a este contrato.</p>
              </div>
            </div>
          </div>
        </section>

        <section
          v-if="isContratoTab"
          class="grid gap-8 rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40 lg:grid-cols-2"
        >
          <div class="space-y-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Financeiro</h3>
            <dl class="space-y-3 text-sm">
              <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Valor do aluguel</dt>
                <dd class="font-medium text-white">{{ formatCurrency(contrato.valor_aluguel) }}</dd>
              </div>
              <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Desconto mensal</dt>
                <dd class="font-medium text-white">{{ formatCurrency(contrato.desconto_mensal) }}</dd>
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <div>
                  <dt class="text-xs uppercase tracking-wide text-slate-500">Multa por atraso</dt>
                  <dd class="font-medium text-white">{{ formatPercent(contrato.multa_atraso_percentual) }}</dd>
                </div>
                <div>
                  <dt class="text-xs uppercase tracking-wide text-slate-500">Juros mensal</dt>
                  <dd class="font-medium text-white">{{ formatPercent(contrato.juros_mora_percentual_mes) }}</dd>
                </div>
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <div>
                  <dt class="text-xs uppercase tracking-wide text-slate-500">Taxa administrativa</dt>
                  <dd class="font-medium text-white">{{ formatPercent(contrato.taxa_adm_percentual) }}</dd>
                </div>
                <div>
                  <dt class="text-xs uppercase tracking-wide text-slate-500">Repasse automático</dt>
                  <dd class="font-medium text-white">{{ formatBoolean(contrato.repasse_automatico) }}</dd>
                </div>
              </div>
              <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Conta de cobrança</dt>
                <dd class="font-medium text-white">{{ contrato.conta_cobranca?.nome ?? 'Não vinculado' }}</dd>
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <div>
                  <dt class="text-xs uppercase tracking-wide text-slate-500">Forma de pagamento</dt>
                  <dd class="font-medium text-white">{{ formaPagamentoLabel(contrato.forma_pagamento_preferida) }}</dd>
                </div>
                <div>
                  <dt class="text-xs uppercase tracking-wide text-slate-500">Tipo de contrato</dt>
                  <dd class="font-medium text-white">{{ contrato.tipo_contrato ?? '-' }}</dd>
                </div>
              </div>
            </dl>
          </div>

          <div class="space-y-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Garantia & reajuste</h3>
            <dl class="space-y-3 text-sm">
              <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Tipo de garantia</dt>
                <dd class="font-medium text-white">{{ garantiaLabel(contrato.garantia_tipo) }}</dd>
              </div>
              <div v-if="contrato.garantia_tipo === 'Caucao'">
                <dt class="text-xs uppercase tracking-wide text-slate-500">Valor da caução</dt>
                <dd class="font-medium text-white">{{ formatCurrency(contrato.caucao_valor) }}</dd>
              </div>
              <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Índice de reajuste</dt>
                <dd class="font-medium text-white">{{ contrato.reajuste_indice ?? 'Sem reajuste' }}</dd>
              </div>
              <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Periodicidade do reajuste</dt>
                <dd class="font-medium text-white">
                  {{ contrato.reajuste_indice === 'SEM_REAJUSTE' ? 'Não se aplica' : (contrato.reajuste_periodicidade_meses ? contrato.reajuste_periodicidade_meses + ' meses' : '-') }}
                </dd>
              </div>
            </dl>
          </div>
        </section>

        <section
          v-if="isContratoTab"
          class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
        >
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Observações</h3>
          <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4 text-sm text-slate-200">
            <template v-if="contrato.observacoes">
              <p class="whitespace-pre-line">{{ contrato.observacoes }}</p>
            </template>
            <template v-else>
              <p class="text-slate-400">Nenhuma observação registrada.</p>
            </template>
          </div>
        </section>

        <section
          v-if="isContratoTab"
          class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
        >
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Anexos</h3>
          <div v-if="contrato.anexos?.length" class="space-y-2">
            <ul class="space-y-2 text-sm text-slate-200">
              <li v-for="anexo in contrato.anexos" :key="anexo.id" class="flex items-center justify-between gap-3 rounded-lg border border-slate-800 bg-slate-950/60 px-4 py-2">
                <div>
                  <p class="font-medium text-white">{{ anexo.original_name }}</p>
                  <p class="text-xs text-slate-400">{{ anexo.mime_type ?? 'Arquivo' }}</p>
                </div>
                <a
                  :href="anexo.url"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex items-center rounded-lg border border-indigo-500/40 bg-indigo-500/20 px-3 py-2 text-xs font-semibold text-indigo-200 transition hover:border-indigo-400 hover:bg-indigo-500/30 hover:text-white"
                >
                  Abrir
                </a>
              </li>
            </ul>
          </div>
          <p v-else class="text-sm text-slate-400">Nenhum arquivo anexado ao contrato.</p>
        </section>
      </template>
    </div>
  </AuthenticatedLayout>
</template>
