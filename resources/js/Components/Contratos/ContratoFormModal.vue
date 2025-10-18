<script setup lang="ts">
import ContratoForm from '@/Components/Contratos/ContratoForm.vue';
import { useAuditTimeline, type AuditLogEntry } from '@/composables/useAuditTimeline';
import { extractChanges, formatDateTime, getActionLabel } from '@/utils/audit';
import { computed, ref, watch } from 'vue';
import DatePicker from '@/Components/Form/DatePicker.vue';

type ModalMode = 'create' | 'edit';

type Props = {
  show: boolean;
  mode: ModalMode;
  contratoId?: number | null;
};

const props = defineProps<Props>();
const emit = defineEmits<{ (e: 'close'): void; (e: 'saved'): void }>();

const headerTitle = computed(() => (props.mode === 'edit' ? 'Editar contrato' : 'Novo contrato'));
const headerSubtitle = computed(() =>
  props.mode === 'edit'
    ? 'Atualize os dados do contrato e salve as alterações.'
    : 'Informe os dados do contrato para concluir o cadastro.'
);

const formKeySeed = ref(0);
watch(
  () => props.show,
  (visible) => {
    if (visible && props.mode === 'create') {
      formKeySeed.value += 1;
    }
  }
);

watch(
  () => props.contratoId,
  () => {
    if (props.mode === 'edit' && props.show) {
      formKeySeed.value += 1;
    }
  }
);

const timeline = useAuditTimeline(() => (props.mode === 'edit' && props.contratoId ? `/api/contratos/${props.contratoId}/audit` : null));
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

watch(
  () => props.show,
  (visible) => {
    if (visible && props.mode === 'edit' && props.contratoId && !timelineInitialized.value) {
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
  () => props.contratoId,
  (id) => {
    if (props.show && props.mode === 'edit' && id) {
      timelineInitialized.value = true;
      void fetchAuditTimeline(1, false);
    }
  }
);

const contratoFieldLabels = {
  valor_aluguel: 'Valor aluguel',
  status: 'Status',
  data_inicio: 'Data início',
  data_fim: 'Data fim',
};

const formattedEntries = computed(() =>
  auditEntries.value.map((entry) => ({
    ...entry,
    changes: extractChanges(entry as AuditLogEntry, contratoFieldLabels),
    timestamp: formatDateTime(entry.created_at),
    actionLabel: getActionLabel(entry.action),
    userLabel: entry.user?.nome ?? entry.user?.username ?? 'Sistema',
  }))
);

const applyAuditFilters = () => fetchAuditTimeline(1, false);
const clearAuditFilters = () => {
  auditFilters.dateFrom = '';
  auditFilters.dateTo = '';
  fetchAuditTimeline(1, false);
};

const handleSaved = () => {
  emit('saved');
  emit('close');
};

const handleCancel = () => {
  emit('close');
};
</script>

<template>
  <transition name="fade">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur"
      @keydown.esc.prevent.stop="handleCancel"
    >
      <div class="relative w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40">
        <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
          <div>
            <h2 class="text-lg font-semibold text-white">{{ headerTitle }}</h2>
            <p class="text-xs text-slate-400">{{ headerSubtitle }}</p>
          </div>
          <button type="button" class="rounded-md p-2 text-slate-400 transition hover:text-white" @click="handleCancel">
            <span class="sr-only">Fechar</span>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </header>
        <div class="max-h-[80vh] overflow-y-auto px-6 py-5">
          <ContratoForm
            :key="props.mode === 'edit' ? `edit-${props.contratoId ?? 'nova'}` : `create-${formKeySeed}`"
            :mode="props.mode"
            :contrato-id="props.mode === 'edit' ? props.contratoId ?? null : null"
            @saved="handleSaved"
            @cancel="handleCancel"
          />

          <section
            v-if="props.mode === 'edit'"
            class="mt-6 space-y-4 rounded-xl border border-slate-800 bg-slate-950/60 p-4 text-xs text-slate-300"
          >
            <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h3 class="text-sm font-semibold text-white">Histórico de auditoria</h3>
                <p class="text-xs text-slate-500">Acompanhe as alterações realizadas neste contrato.</p>
              </div>
              <div class="flex items-center gap-2">
                <button type="button" class="rounded-lg border border-indigo-500/40 px-3 py-1 text-xs font-semibold text-indigo-200 transition hover:bg-indigo-500/20" :disabled="!auditEntries.length" @click="exportAuditTimeline('csv')">
                  Exportar CSV
                </button>
                <button type="button" class="rounded-lg border border-indigo-500/40 px-3 py-1 text-xs font-semibold text-indigo-200 transition hover:bg-indigo-500/20" :disabled="!auditEntries.length" @click="exportAuditTimeline('json')">
                  Exportar JSON
                </button>
              </div>
            </header>

            <div class="grid gap-3 md:grid-cols-4">
              <div class="flex flex-col gap-1">
                <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">De</label>
                <DatePicker v-model="auditFilters.dateFrom" placeholder="dd/mm/aaaa" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Até</label>
                <DatePicker v-model="auditFilters.dateTo" placeholder="dd/mm/aaaa" />
              </div>
              <div class="flex items-end gap-2 md:col-span-2">
                <button type="button" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-500" :disabled="auditLoading" @click="applyAuditFilters">
                  Aplicar
                </button>
                <button v-if="auditHasFilters" type="button" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-slate-800/70" :disabled="auditLoading" @click="clearAuditFilters">
                  Limpar
                </button>
              </div>
            </div>

            <div class="space-y-4">
              <div v-if="auditError" class="rounded-lg border border-rose-500/40 bg-rose-500/15 px-3 py-2 text-xs text-rose-200">{{ auditError }}</div>
              <div v-else-if="auditLoading && !auditEntries.length" class="text-xs text-slate-400">Carregando histórico...</div>
              <div v-else-if="!auditEntries.length" class="text-xs text-slate-400">Nenhum registro encontrado.</div>
              <ul v-else class="relative space-y-3 border-l border-slate-800 pl-6 text-xs">
                <li v-for="entry in formattedEntries" :key="entry.id" class="relative">
                  <span class="absolute -left-[9px] top-1.5 h-2.5 w-2.5 rounded-full bg-indigo-400"></span>
                  <div class="flex flex-wrap items-center justify-between gap-2 text-slate-400">
                    <span>{{ entry.timestamp }}</span>
                    <span>{{ entry.userLabel }}</span>
                  </div>
                  <p class="mt-1 text-sm font-medium text-white">{{ entry.actionLabel }}</p>
                  <ul v-if="entry.changes.length" class="mt-2 list-disc space-y-1 pl-4 text-slate-300">
                    <li v-for="change in entry.changes" :key="change">{{ change }}</li>
                  </ul>
                  <p v-else class="mt-2 text-slate-500">Sem alterações registradas.</p>
                </li>
              </ul>
              <div v-if="auditLoading && auditEntries.length" class="text-xs text-slate-400">Carregando...</div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-slate-400">
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
