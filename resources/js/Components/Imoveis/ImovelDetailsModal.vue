<script setup lang="ts">
import { useAuditTimeline, type AuditLogEntry } from '@/composables/useAuditTimeline';
import { extractChanges, formatDateTime, getActionLabel } from '@/utils/audit';
import { computed, ref, watch } from 'vue';
import DatePicker from '@/Components/Form/DatePicker.vue';

type Nullable<T> = T | null;

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
    condominio_isento: boolean;
    valor_iptu: Nullable<string | number>;
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

const props = defineProps<{
  show: boolean;
  loading: boolean;
  imovel: ImovelDetalhe | null;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
}>();

const disponibilidadeLabel = (value: string): string => {
  const lookup: Record<string, string> = {
    Disponivel: 'Disponível',
    Indisponivel: 'Indisponível',
  };
  return lookup[value] ?? value ?? '-';
};

const finalidadeLabel = computed(() => {
  const list = props.imovel?.finalidade ?? [];
  if (list.length === 0) {
    return '-';
  }
  return list.join(', ');
});

const comodidadesLabel = computed(() => {
  const list = props.imovel?.caracteristicas.comodidades ?? [];
  if (!list.length) {
    return '-';
  }
  return list.join(', ');
});

function closeModal(): void {
  emit('close');
}

function formatCurrency(value: Nullable<string | number>): string {
  if (value === null || value === undefined || value === '') {
    return '-';
  }

  const numeric = typeof value === 'number' ? value : Number.parseFloat(String(value));
  if (Number.isNaN(numeric)) {
    return '-';
  }

  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 2,
  }).format(numeric);
}

const hasAnexos = computed(() => (props.imovel?.anexos?.length ?? 0) > 0);

const enderecoResumo = computed(() => {
  const endereco = props.imovel?.enderecos;
  if (!endereco) return '-';
  const partes = [
    endereco.rua ?? endereco.logradouro,
    endereco.numero,
    endereco.bairro,
    endereco.cidade,
    endereco.estado,
  ].filter(Boolean);
  return partes.length ? partes.join(', ') : '-';
});

const createdAtLabel = computed(() => (props.imovel?.created_at ? new Date(props.imovel.created_at).toLocaleString('pt-BR') : '-'));
const updatedAtLabel = computed(() => (props.imovel?.updated_at ? new Date(props.imovel.updated_at).toLocaleString('pt-BR') : '-'));

const timeline = useAuditTimeline(() => (props.imovel ? `/api/imoveis/${props.imovel.id}/audit` : null));
const timelineInitialized = ref(false);
const {
  entries: auditEntries,
  meta: auditMeta,
  loading: auditLoading,
  errorMessage: auditError,
  filters: auditFilters,
  hasMore: auditHasMore,
  fetchTimeline: fetchAuditTimeline,
  resetTimeline: resetAuditTimeline,
  exportTimeline: exportAuditTimeline,
  hasFilters: auditHasFilters,
} = timeline;

const applyAuditFilters = () => fetchAuditTimeline(1, false);
const clearAuditFilters = () => {
  auditFilters.dateFrom = '';
  auditFilters.dateTo = '';
  fetchAuditTimeline(1, false);
};

watch(
  () => props.show,
  (visible) => {
    if (visible && props.imovel?.id && !timelineInitialized.value) {
      timelineInitialized.value = true;
      void fetchAuditTimeline(1, false);
    }

    if (!visible) {
      resetAuditTimeline();
      timelineInitialized.value = false;
    }
  }
);

watch(
  () => props.imovel?.id,
  (id) => {
    if (props.show && id) {
      timelineInitialized.value = true;
      void fetchAuditTimeline(1, false);
    }
  }
);

const timelineFieldLabels = {
  valor_locacao: 'Valor locação',
  valor_condominio: 'Valor condomínio',
  valor_iptu: 'Valor IPTU',
  outros_valores: 'Outros valores',
  disponibilidade: 'Disponibilidade',
};

const formattedEntries = computed(() =>
  auditEntries.value.map((entry) => ({
    ...entry,
    changes: extractChanges(entry as AuditLogEntry, timelineFieldLabels),
    timestamp: formatDateTime(entry.created_at),
    actionLabel: getActionLabel(entry.action),
    userLabel: entry.user?.nome ?? entry.user?.username ?? 'Sistema',
  }))
);
</script>

<template>
  <transition name="fade">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur"
      @keydown.esc.prevent.stop="closeModal"
    >
      <div class="relative w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40">
        <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
          <div>
            <h2 class="text-lg font-semibold text-white">Detalhes do imóvel</h2>
            <p class="text-xs text-slate-400">Visualize as informações cadastradas.</p>
          </div>
          <button type="button" class="rounded-md p-2 text-slate-400 transition hover:text-white" @click="closeModal">
            <span class="sr-only">Fechar</span>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </header>

        <div class="max-h-[80vh] overflow-y-auto px-6 py-5">
          <div v-if="loading" class="flex items-center justify-center py-10 text-sm text-slate-400">
            Carregando informações do imóvel...
          </div>

          <div v-else-if="!imovel" class="flex items-center justify-center py-10 text-sm text-slate-400">
            Não foi possível carregar os detalhes deste imóvel.
          </div>

          <div v-else class="space-y-6 text-sm text-slate-200">
            <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Identificação</h3>
              <div class="mt-3 grid gap-4 md:grid-cols-3">
                <div>
                  <span class="text-xs text-slate-400">Código</span>
                  <p class="text-base font-semibold text-white">{{ imovel.codigo }}</p>
                </div>
                <div>
                  <span class="text-xs text-slate-400">Tipo</span>
                  <p class="text-base text-slate-200">{{ imovel.tipo_imovel }}</p>
                </div>
                <div>
                  <span class="text-xs text-slate-400">Disponibilidade</span>
                  <p class="text-base text-slate-200">{{ disponibilidadeLabel(imovel.disponibilidade) }}</p>
                </div>
                <div class="md:col-span-3">
                  <span class="text-xs text-slate-400">Finalidade</span>
                  <p class="text-base text-slate-200">{{ finalidadeLabel }}</p>
                </div>
              </div>
            </section>

            <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Localização</h3>
              <div class="mt-3 grid gap-4 md:grid-cols-2">
                <div>
                  <span class="text-xs text-slate-400">Endereço</span>
                  <p class="text-base text-slate-200">{{ enderecoResumo }}</p>
                  <p class="text-xs text-slate-500">CEP: {{ imovel.enderecos.cep ?? '-' }}</p>
                </div>
                <div>
                  <span class="text-xs text-slate-400">Complemento</span>
                  <p class="text-base text-slate-200">{{ imovel.enderecos.complemento ?? '-' }}</p>
                </div>
              </div>
            </section>

            <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Valores</h3>
              <div class="mt-3 grid gap-4 md:grid-cols-3">
                <div>
                  <span class="text-xs text-slate-400">Locação</span>
                  <p class="text-base text-slate-200">{{ formatCurrency(imovel.valores.valor_locacao) }}</p>
                </div>
                <div>
                  <span class="text-xs text-slate-400">Condomínio</span>
                  <p class="text-base text-slate-200">{{ imovel.valores.condominio_isento ? 'Isento' : formatCurrency(imovel.valores.valor_condominio) }}</p>
                  <p class="text-[11px] text-slate-400">Status: {{ imovel.valores.condominio_isento ? 'Isento' : 'Cobrado' }}</p>
                </div>
                <div>
                  <span class="text-xs text-slate-400">IPTU</span>
                  <p class="text-base text-slate-200">{{ imovel.valores.iptu_isento ? 'Isento' : formatCurrency(imovel.valores.valor_iptu) }}</p>
                  <p class="text-[11px] text-slate-400">Status: {{ imovel.valores.iptu_isento ? 'Isento' : 'Cobrado' }}</p>
                </div>
                <div>
                  <span class="text-xs text-slate-400">Outros valores</span>
                  <p class="text-base text-slate-200">{{ imovel.valores.outros_isento ? 'Isento' : formatCurrency(imovel.valores.outros_valores) }}</p>
                  <p class="text-[11px] text-slate-400">Status: {{ imovel.valores.outros_isento ? 'Isento' : 'Cobrado' }}</p>
                </div>
                <div>
                  <span class="text-xs text-slate-400">Período IPTU</span>
                  <p class="text-base text-slate-200">{{ imovel.valores.periodo_iptu ?? '-' }}</p>
                </div>
              </div>
            </section>

            <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Características</h3>
              <div class="mt-3 grid gap-4 md:grid-cols-3">
                <div>
                  <span class="text-xs text-slate-400">Dormitórios</span>
                  <p class="text-base text-slate-200">{{ imovel.caracteristicas.dormitorios ?? '-' }}</p>
                </div>
                <div>
                  <span class="text-xs text-slate-400">Suítes</span>
                  <p class="text-base text-slate-200">{{ imovel.caracteristicas.suites ?? '-' }}</p>
                </div>
                <div>
                  <span class="text-xs text-slate-400">Banheiros</span>
                  <p class="text-base text-slate-200">{{ imovel.caracteristicas.banheiros ?? '-' }}</p>
                </div>
                <div>
                  <span class="text-xs text-slate-400">Vagas</span>
                  <p class="text-base text-slate-200">{{ imovel.caracteristicas.vagas_garagem ?? '-' }}</p>
                </div>
                <div>
                  <span class="text-xs text-slate-400">Área total (m²)</span>
                  <p class="text-base text-slate-200">{{ imovel.caracteristicas.area_total ?? '-' }}</p>
                </div>
                <div>
                  <span class="text-xs text-slate-400">Área construída (m²)</span>
                  <p class="text-base text-slate-200">{{ imovel.caracteristicas.area_construida ?? '-' }}</p>
                </div>
                <div class="md:col-span-3">
                  <span class="text-xs text-slate-400">Comodidades</span>
                  <p class="text-base text-slate-200">{{ comodidadesLabel }}</p>
                </div>
              </div>
            </section>

            <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Relacionamentos</h3>
              <div class="mt-3 grid gap-4 md:grid-cols-2">
                <div>
                  <span class="text-xs text-slate-400">Proprietário</span>
                  <p class="text-base text-slate-200">{{ imovel.proprietario?.nome_razao_social ?? '-' }}</p>
                </div>
                <div>
                  <span class="text-xs text-slate-400">Agenciador</span>
                  <p class="text-base text-slate-200">{{ imovel.agenciador?.nome_razao_social ?? '-' }}</p>
                </div>
                <div>
                  <span class="text-xs text-slate-400">Responsável</span>
                  <p class="text-base text-slate-200">{{ imovel.responsavel?.nome_razao_social ?? '-' }}</p>
                </div>
                <div>
                  <span class="text-xs text-slate-400">Condomínio</span>
                  <p class="text-base text-slate-200">{{ imovel.condominio?.nome ?? '-' }}</p>
                </div>
              </div>
            </section>

            <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-4" v-if="hasAnexos">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Documentos anexados</h3>
              <div class="mt-3 space-y-2">
                <a
                  v-for="anexo in imovel.anexos"
                  :key="anexo.id"
                  :href="anexo.url"
                  class="flex items-center justify-between gap-3 rounded-lg border border-slate-800 bg-slate-900/60 px-3 py-2 text-sm text-slate-200 transition hover:border-indigo-500/40 hover:bg-indigo-500/10 hover:text-white"
                  target="_blank"
                  rel="noopener"
                >
                  <div>
                    <p class="font-medium text-white">{{ anexo.display_name }}</p>
                    <p class="text-xs text-slate-400">{{ anexo.mime_type ?? 'Arquivo' }}</p>
                  </div>
                  <svg class="h-4 w-4 text-indigo-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M12 12V4m0 0l-4 4m4-4l4 4" />
                  </svg>
                </a>
              </div>
            </section>

            <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
              <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Histórico de auditoria</h3>
                  <p class="text-xs text-slate-500">Acompanhe alterações de valores, status e outras ações neste imóvel.</p>
                </div>
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    class="rounded-lg border border-indigo-500/40 px-3 py-1 text-xs font-semibold text-indigo-200 transition hover:bg-indigo-500/20"
                    @click="exportAuditTimeline('csv')"
                    :disabled="!auditEntries.length"
                  >
                    Exportar CSV
                  </button>
                  <button
                    type="button"
                    class="rounded-lg border border-indigo-500/40 px-3 py-1 text-xs font-semibold text-indigo-200 transition hover:bg-indigo-500/20"
                    @click="exportAuditTimeline('json')"
                    :disabled="!auditEntries.length"
                  >
                    Exportar JSON
                  </button>
                </div>
              </header>

              <div class="mt-4 grid gap-3 md:grid-cols-4">
                <div class="flex flex-col gap-1">
                  <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">De</label>
                  <DatePicker v-model="auditFilters.dateFrom" placeholder="dd/mm/aaaa" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Até</label>
                  <DatePicker v-model="auditFilters.dateTo" placeholder="dd/mm/aaaa" />
                </div>
                <div class="flex items-end gap-2 md:col-span-2">
                  <button type="button" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-500" :disabled="auditLoading" @click="applyAuditFilters">
                    Aplicar
                  </button>
                  <button v-if="auditHasFilters" type="button" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:bg-slate-800/70" :disabled="auditLoading" @click="clearAuditFilters">
                    Limpar
                  </button>
                </div>
              </div>

              <div class="mt-4 space-y-4">
                <div v-if="auditError" class="rounded-lg border border-rose-500/40 bg-rose-500/15 px-3 py-2 text-xs text-rose-200">{{ auditError }}</div>
                <div v-else-if="auditLoading && !auditEntries.length" class="text-xs text-slate-400">Carregando histórico...</div>
                <div v-else-if="!auditEntries.length" class="text-xs text-slate-400">Nenhum registro de auditoria encontrado para este período.</div>
                <ul v-else class="relative space-y-4 border-l border-slate-800 pl-6">
                  <li v-for="entry in formattedEntries" :key="entry.id" class="relative">
                    <span class="absolute -left-[9px] top-1.5 h-2.5 w-2.5 rounded-full bg-indigo-400"></span>
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-400">
                      <span>{{ entry.timestamp }}</span>
                      <span>{{ entry.userLabel }}</span>
                    </div>
                    <p class="mt-1 text-sm font-medium text-white">{{ entry.actionLabel }}</p>
                    <ul v-if="entry.changes.length" class="mt-2 list-disc space-y-1 pl-4 text-xs text-slate-300">
                      <li v-for="change in entry.changes" :key="change">{{ change }}</li>
                    </ul>
                    <p v-else class="mt-2 text-xs text-slate-500">Sem alterações registradas.</p>
                  </li>
                </ul>
                <div v-if="auditLoading && auditEntries.length" class="text-xs text-slate-400">Carregando...</div>
              </div>

              <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-400">
                <span v-if="auditMeta">Página {{ auditMeta.current_page }} de {{ auditMeta.last_page }}</span>
                <button
                  v-if="auditHasMore"
                  type="button"
                  class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-slate-800/70"
                  :disabled="auditLoading"
                  @click="auditMeta ? fetchAuditTimeline(auditMeta.current_page + 1, true) : undefined"
                >
                  Carregar mais
                </button>
              </div>
            </section>

            <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-4 text-xs text-slate-400">
              <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p>Criado em: <span class="text-slate-200">{{ createdAtLabel }}</span></p>
                <p>Atualizado em: <span class="text-slate-200">{{ updatedAtLabel }}</span></p>
              </div>
            </section>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
