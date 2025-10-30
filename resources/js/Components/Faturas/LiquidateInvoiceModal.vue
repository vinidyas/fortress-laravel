<script setup lang="ts">
import DatePicker from '@/Components/Form/DatePicker.vue';

type Nullable<T> = T | null;

type LiquidateForm = {
  valor_pago: string;
  pago_em: string;
  metodo_pagamento: string;
  observacoes: string;
};

const props = defineProps<{
  show: boolean;
  submitting: boolean;
  error: string;
  form: LiquidateForm;
  metodoOptions: string[];
  formatCurrency: (value: Nullable<string>) => string;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'submit'): void;
  (e: 'use-total'): void;
  (e: 'valor-input', event: Event): void;
}>();
</script>

<template>
  <transition name="fade">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur"
      @keydown.esc.prevent.stop="emit('close')"
    >
      <div class="relative w-full max-w-3xl overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40">
        <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
          <div>
            <h2 class="text-lg font-semibold text-white">Liquidar fatura</h2>
            <p class="text-xs text-slate-400">Informe os detalhes do recebimento para registrar a baixa.</p>
          </div>
          <button type="button" class="rounded-md p-2 text-slate-400 transition hover:text-white" @click="emit('close')">
            <span class="sr-only">Fechar</span>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </header>

        <form class="max-h-[75vh] overflow-y-auto px-6 py-5 text-sm text-slate-200" @submit.prevent="emit('submit')">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-slate-300">Valor pago</label>
              <div class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                  <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs font-semibold uppercase tracking-wide text-slate-500">R$</span>
                  <input
                    :value="formatCurrency(form.valor_pago)"
                    type="text"
                    inputmode="decimal"
                    required
                    class="w-full rounded-lg border border-slate-700 bg-slate-900/60 px-8 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                    placeholder="0,00"
                    @input="emit('valor-input', $event)"
                  />
                </div>
                <button
                  type="button"
                  class="inline-flex items-center justify-center rounded-lg border border-indigo-500/40 bg-indigo-600/70 px-4 py-2 text-xs font-semibold text-white transition hover:bg-indigo-500/80"
                  @click="emit('use-total')"
                >
                  Usar total da fatura
                </button>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-300">Pago em</label>
              <DatePicker v-model="form.pago_em" placeholder="dd/mm/aaaa" required />
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-300">Método</label>
              <select
                v-model="form.metodo_pagamento"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
              >
                <option v-for="option in metodoOptions" :key="option" :value="option">
                  {{ option }}
                </option>
              </select>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-slate-300">Observações</label>
              <textarea
                v-model="form.observacoes"
                rows="3"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
              ></textarea>
            </div>
          </div>

          <div v-if="error" class="mt-4 rounded-lg border border-rose-500/40 bg-rose-500/15 px-3 py-2 text-xs text-rose-200">
            {{ error }}
          </div>
        </form>

        <footer class="flex flex-col gap-3 border-t border-slate-800 px-6 py-4 text-xs text-slate-300 md:flex-row md:items-center md:justify-end">
          <button
            type="button"
            class="rounded-lg border border-slate-700 px-4 py-2 text-xs font-semibold text-slate-300 transition hover:border-slate-600 hover:bg-slate-800/80 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="submitting"
            @click="emit('close')"
          >
            Cancelar
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg border border-emerald-500/60 bg-emerald-600/80 px-4 py-2 text-xs font-semibold text-white transition hover:bg-emerald-500/80 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="submitting"
            @click="emit('submit')"
          >
            <svg
              v-if="submitting"
              class="h-4 w-4 animate-spin"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 4.5a6 6 0 11-9 0" />
            </svg>
            <span>{{ submitting ? 'Processando...' : 'Registrar baixa' }}</span>
          </button>
        </footer>
      </div>
    </div>
  </transition>
</template>
