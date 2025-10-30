<script setup lang="ts">
import TransactionForm, {
  type TransactionOption,
  type TransactionPayload,
  type TransactionPersonOption,
} from '@/Components/Financeiro/TransactionForm.vue';
import { computed, ref, watch } from 'vue';

type ModalMode = 'create' | 'edit';
type ModalTab = 'basic' | 'installments' | 'attachments' | 'receipts';

const props = defineProps<{
  show: boolean;
  mode?: ModalMode;
  transaction?: TransactionPayload | null;
  accounts: TransactionOption[];
  costCenters: TransactionOption[];
  people: TransactionPersonOption[];
  properties: Array<{ id: number; titulo?: string | null; codigo_interno?: string | null }>;
  permissions: {
    update: boolean;
    delete: boolean;
    reconcile: boolean;
  };
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'created'): void;
  (e: 'updated'): void;
  (e: 'deleted'): void;
}>();

const effectiveMode = computed<ModalMode>(() => props.mode ?? 'create');
const formKeySeed = ref(0);
const modalTabs: Array<{ id: ModalTab; label: string }> = [
  { id: 'basic', label: 'Básico' },
  { id: 'installments', label: 'Parcelas' },
  { id: 'attachments', label: 'Anexos' },
  { id: 'receipts', label: 'Recibos' },
];
const activeTab = ref<ModalTab>('basic');
const hasPersistedTransaction = computed(() => Boolean(props.transaction?.id));
const computedTabs = computed(() =>
  modalTabs.map((tab) => ({
    ...tab,
    disabled: ['attachments', 'receipts'].includes(tab.id) && !hasPersistedTransaction.value,
  }))
);

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      formKeySeed.value += 1;
      activeTab.value = 'basic';
    }
  }
);

watch(
  () => props.transaction,
  () => {
    if (props.show) {
      formKeySeed.value += 1;
      activeTab.value = 'basic';
    }
  }
);

const handleClose = () => {
  emit('close');
};

const handleSaved = () => {
  if (effectiveMode.value === 'create') {
    emit('created');
  } else {
    emit('updated');
  }
  emit('close');
};

const handleDeleted = () => {
  emit('deleted');
  emit('close');
};

const changeTab = (tabId: ModalTab) => {
  const targetTab = computedTabs.value.find((tab) => tab.id === tabId);
  if (targetTab?.disabled) {
    return;
  }

  activeTab.value = tabId;
};
</script>

<template>
  <transition name="fade">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-3 py-5 backdrop-blur"
      @keydown.esc.prevent.stop="handleClose"
    >
      <div class="relative w-full max-w-4xl px-[1.5px] pb-[1.5px]">
        <div
          class="pointer-events-none absolute inset-0 -z-10 rounded-[28px] bg-gradient-to-br from-indigo-500/30 via-purple-500/20 to-emerald-400/25 blur-xl"
        ></div>
        <div
          class="relative overflow-hidden rounded-[26px] border border-white/10 bg-slate-950/85 shadow-[0_25px_60px_-25px_rgba(15,23,42,0.9)] backdrop-blur-2xl"
        >
          <header
            class="flex items-center justify-between gap-4 border-b border-white/5 px-6 py-5"
          >
            <div class="space-y-1.5">
              <span class="inline-flex items-center gap-2 rounded-full bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-indigo-200">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-400"></span>
                Financeiro
              </span>
              <h2 class="text-2xl font-semibold leading-tight text-white">
                {{ effectiveMode === 'edit' ? 'Editar lançamento' : 'Novo lançamento' }}
              </h2>
              <p class="max-w-xl text-sm text-slate-300/80">
                {{
                  effectiveMode === 'edit'
                    ? 'Revise e atualize os dados financeiros com confiança.'
                    : 'Cadastre um novo movimento financeiro de forma rápida e organizada.'
                }}
              </p>
            </div>
            <button
              type="button"
              class="group relative inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-300 transition hover:border-white/30 hover:bg-white/10 hover:text-white"
              @click="handleClose"
            >
              <span class="sr-only">Fechar</span>
              <svg
                class="h-4.5 w-4.5 transition-transform group-hover:rotate-180"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </header>
          <div class="max-h-[82vh] overflow-y-auto">
            <div class="px-6 pt-4">
              <nav
                class="flex items-center gap-2 overflow-x-auto rounded-full border border-white/5 bg-white/5 p-1 text-sm text-slate-400"
              >
                <button
                  v-for="tab in computedTabs"
                  :key="tab.id"
                  type="button"
                  class="inline-flex min-w-[110px] items-center justify-center rounded-full px-4 py-1.5 transition"
                  :class="
                    [
                      activeTab === tab.id
                        ? 'bg-gradient-to-r from-indigo-500/90 via-purple-500/90 to-sky-500/90 text-white shadow-[0_8px_20px_-10px_rgba(99,102,241,0.8)]'
                        : 'hover:bg-white/10 hover:text-slate-100',
                      tab.disabled ? 'cursor-not-allowed opacity-40 hover:bg-transparent hover:text-slate-400' : '',
                    ]
                  "
                  :disabled="tab.disabled"
                  :title="tab.disabled ? 'Disponível após salvar o lançamento.' : undefined"
                  @click="changeTab(tab.id)"
                >
                  {{ tab.label }}
                </button>
              </nav>
            </div>
            <div class="px-6 py-5">
              <TransactionForm
                :key="`${effectiveMode}-${formKeySeed}`"
                :mode="effectiveMode"
                :transaction="transaction ?? null"
                :accounts="accounts"
                :cost-centers="costCenters"
                :people="people"
                :properties="properties"
                :permissions="permissions"
                :active-tab="activeTab"
                context="modal"
                appearance="dark"
                :redirect-on-save="false"
                @deleted="handleDeleted"
                @saved="handleSaved"
                @cancel="handleClose"
              />
            </div>
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
