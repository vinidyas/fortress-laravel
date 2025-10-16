<script setup lang="ts">
import { computed, ref, watch } from 'vue';

interface AlertRow {
  id: number;
  title: string;
  message: string;
  occurred_at: string | null;
  resolved_at: string | null;
  resolution_notes: string | null;
}

const props = defineProps<{
  show: boolean;
  alert: AlertRow | null;
  processing: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'confirm', notes: string): void;
}>();

const notes = ref('');

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      notes.value = props.alert?.resolution_notes ?? '';
    }
  }
);

const formatDateTime = (value: string | null) =>
  value ? new Date(value).toLocaleString('pt-BR') : '-';

const alertTitle = computed(() => props.alert?.title ?? 'Tratar alerta');

const submit = () => {
  if (props.processing) return;
  emit('confirm', notes.value.trim());
};
</script>

<template>
  <transition name="fade">
    <div
      v-if="props.show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur"
      @keydown.esc.prevent.stop="emit('close')"
    >
      <div class="relative w-full max-w-2xl rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40">
        <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
          <div>
            <h2 class="text-lg font-semibold text-white">{{ alertTitle }}</h2>
            <p class="text-xs text-slate-400">
              Confirme o tratamento deste alerta e registre uma anotação opcional.
            </p>
          </div>
          <button
            type="button"
            class="rounded-md p-2 text-slate-400 transition hover:text-white"
            @click="emit('close')"
          >
            <span class="sr-only">Fechar</span>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </header>

        <section class="space-y-4 px-6 py-5">
          <div v-if="props.alert" class="rounded-lg border border-slate-800 bg-slate-900/60 px-4 py-3 text-sm text-slate-200">
            <p class="font-medium text-white">{{ props.alert.title }}</p>
            <p class="mt-2 text-slate-300">{{ props.alert.message }}</p>
            <dl class="mt-3 grid gap-2 text-xs text-slate-400 sm:grid-cols-2">
              <div>
                <dt class="uppercase tracking-wide">Detectado em</dt>
                <dd class="text-slate-200">{{ formatDateTime(props.alert.occurred_at) }}</dd>
              </div>
              <div v-if="props.alert.resolved_at">
                <dt class="uppercase tracking-wide">Tratado em</dt>
                <dd class="text-slate-200">{{ formatDateTime(props.alert.resolved_at) }}</dd>
              </div>
            </dl>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-200" for="resolution-notes">Observações</label>
            <textarea
              id="resolution-notes"
              v-model="notes"
              rows="4"
              class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              placeholder="Opcional: descreva como o alerta foi tratado."
            />
            <p class="text-xs text-slate-500">Essas observações ficam registradas no histórico.</p>
          </div>
        </section>

        <footer class="flex items-center justify-end gap-2 border-t border-slate-800 bg-slate-900/80 px-6 py-4">
          <button
            type="button"
            class="rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-200 transition hover:bg-slate-800 disabled:opacity-60"
            :disabled="processing"
            @click="emit('close')"
          >
            Cancelar
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-500 disabled:opacity-60"
            :disabled="processing"
            @click="submit"
          >
            <svg
              v-if="processing"
              class="h-4 w-4 animate-spin"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 12a8 8 0 018-8m8 8a8 8 0 01-8 8" />
            </svg>
            <span>Confirmar tratamento</span>
          </button>
        </footer>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
