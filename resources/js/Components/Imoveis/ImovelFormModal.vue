<script setup lang="ts">
import ImovelForm, { ImovelFormDraft } from '@/Components/Imoveis/ImovelForm.vue';
import { computed, ref, watch } from 'vue';

type ModalMode = 'create' | 'edit';

type Props = {
  show: boolean;
  mode?: ModalMode;
  imovelId?: number | null;
  draft?: ImovelFormDraft | null;
};

const props = withDefaults(defineProps<Props>(), {
  mode: 'create' as ModalMode,
  imovelId: null,
  draft: null,
});

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'saved'): void;
  (e: 'request-create-condominio', draft: ImovelFormDraft): void;
}>();

const headerTitle = computed(() => (props.mode === 'edit' ? 'Editar imóvel' : 'Novo imóvel'));
const headerSubtitle = computed(() =>
  props.mode === 'edit'
    ? 'Atualize os dados do imóvel e salve as alterações.'
    : 'Informe os dados do imóvel para concluir o cadastro.'
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
  () => props.imovelId,
  () => {
    if (props.mode === 'edit' && props.show) {
      formKeySeed.value += 1;
    }
  }
);

watch(
  () => props.draft,
  () => {
    if (props.mode === 'create' && props.show) {
      formKeySeed.value += 1;
    }
  }
);

function handleSaved(): void {
  emit('saved');
  emit('close');
}

function handleCancel(): void {
  emit('close');
}

function handleRequestCreateCondominio(draft: ImovelFormDraft): void {
  emit('request-create-condominio', draft);
}
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
          <button
            type="button"
            class="rounded-md p-2 text-slate-400 transition hover:text-white"
            @click="handleCancel"
          >
            <span class="sr-only">Fechar</span>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </header>
        <div class="max-h-[80vh] overflow-y-auto px-6 py-5">
          <ImovelForm
            :key="props.mode === 'edit' ? `edit-${props.imovelId ?? 'novo'}` : `create-${formKeySeed}`"
            :mode="props.mode"
            :imovel-id="props.mode === 'edit' ? props.imovelId ?? null : null"
            :draft="props.mode === 'create' ? props.draft ?? null : null"
            @saved="handleSaved"
            @cancel="handleCancel"
            @request-create-condominio="handleRequestCreateCondominio"
          />
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
