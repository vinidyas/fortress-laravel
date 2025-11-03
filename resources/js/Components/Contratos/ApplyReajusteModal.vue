<script setup lang="ts">
import axios from '@/bootstrap';
import type { AxiosError } from 'axios';
import { computed, reactive, ref, watch } from 'vue';

type Nullable<T> = T | null | undefined;

type ContratoResumo = {
  id: number;
  codigo_contrato: string;
  valor_aluguel: Nullable<string | number>;
  reajuste_indice: Nullable<string>;
  reajuste_indice_outro: Nullable<string>;
  reajuste_periodicidade_meses: Nullable<number>;
  reajuste_teto_percentual: Nullable<string | number>;
  data_proximo_reajuste: Nullable<string>;
};

type Props = {
  show: boolean;
  contrato: ContratoResumo | null;
};

type ApiResponse = {
  message?: string;
  contrato?: { data?: unknown } | unknown;
  reajuste?: unknown;
};

type ValidationErrors = {
  percentual?: string[];
  valor_novo?: string[];
  observacoes?: string[];
};

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'applied', payload: { message?: string; contrato?: unknown; reajuste?: unknown }): void;
}>();

const form = reactive({
  percentual: '',
  valor_novo: '',
  observacoes: '',
});

const errors = reactive({
  percentual: '',
  valor_novo: '',
  observacoes: '',
  general: '',
});

const isSubmitting = ref(false);

const valorAtual = computed(() => toNumber(props.contrato?.valor_aluguel));
const tetoPercentual = computed(() => {
  const valorBruto = toNumber(props.contrato?.reajuste_teto_percentual);
  return valorBruto > 0 ? valorBruto : null;
});

const percentualNumber = computed(() => toNumber(form.percentual));
const valorCalculado = computed(() => {
  if (valorAtual.value <= 0 || percentualNumber.value <= 0) {
    return null;
  }

  return roundTwo(valorAtual.value * (1 + percentualNumber.value / 100));
});

const valorNovoPreview = computed(() => {
  const manual = toNumber(form.valor_novo);
  if (manual > 0) return manual;
  return valorCalculado.value;
});

const excedeTeto = computed(() => {
  if (!tetoPercentual.value) return false;
  return percentualNumber.value > tetoPercentual.value;
});

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      resetForm();
    } else {
      clearErrors();
    }
  }
);

function resetForm() {
  form.percentual = '';
  form.valor_novo = '';
  form.observacoes = '';
  clearErrors();
}

function clearErrors() {
  errors.percentual = '';
  errors.valor_novo = '';
  errors.observacoes = '';
  errors.general = '';
}

function toNumber(value: Nullable<string | number>): number {
  if (value === null || value === undefined) {
    return 0;
  }

  if (typeof value === 'number') {
    return Number.isFinite(value) ? value : 0;
  }

  const normalized = value.replace(/\s+/g, '').replace(',', '.');
  const parsed = Number(normalized);

  return Number.isFinite(parsed) ? parsed : 0;
}

function roundTwo(value: number): number {
  return Math.round(value * 100) / 100;
}

function formatCurrency(value: Nullable<number>): string {
  if (value === null || value === undefined || Number.isNaN(value)) {
    return '-';
  }

  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
}

function formatPercentDisplay(value: Nullable<number | string>): string {
  if (value === null || value === undefined || value === '') {
    return '-';
  }

  const numeric = typeof value === 'number' ? value : Number(String(value).replace(',', '.'));
  if (Number.isNaN(numeric)) {
    return '-';
  }

  return `${numeric.toFixed(2)}%`;
}

function formatDate(value: Nullable<string>): string {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }
  return new Intl.DateTimeFormat('pt-BR').format(date);
}

async function submitReajuste() {
  if (!props.contrato || isSubmitting.value) {
    return;
  }

  clearErrors();

  const percentual = percentualNumber.value;

  if (percentual <= 0) {
    errors.percentual = 'Informe um percentual válido.';
    return;
  }

  const payload: Record<string, unknown> = {
    percentual,
  };

  if (form.valor_novo.trim() !== '') {
    const valorNovo = toNumber(form.valor_novo);

    if (valorNovo <= 0) {
      errors.valor_novo = 'Informe um valor positivo para o aluguel reajustado.';
      return;
    }

    payload.valor_novo = roundTwo(valorNovo);
  }

  if (form.observacoes.trim() !== '') {
    payload.observacoes = form.observacoes.trim();
  }

  isSubmitting.value = true;

  try {
    const { data } = await axios.post<ApiResponse>(`/api/contratos/${props.contrato.id}/reajustes`, payload);

    emit('applied', {
      message: data?.message,
      contrato: (data?.contrato as { data?: unknown })?.data ?? data?.contrato,
      reajuste: data?.reajuste,
    });
  } catch (error) {
    if (axios.isAxiosError(error)) {
      handleAxiosError(error);
    } else {
      errors.general = 'Não foi possível aplicar o reajuste. Tente novamente em instantes.';
    }
  } finally {
    isSubmitting.value = false;
  }
}

function handleAxiosError(error: AxiosError<{ message?: string; errors?: ValidationErrors }>) {
  const response = error.response;

  if (!response) {
    errors.general = 'Falha de comunicação com o servidor.';
    return;
  }

  const payload = response.data;

  if (response.status === 422 && payload?.errors) {
    errors.percentual = payload.errors.percentual?.[0] ?? '';
    errors.valor_novo = payload.errors.valor_novo?.[0] ?? '';
    errors.observacoes = payload.errors.observacoes?.[0] ?? '';
    errors.general = payload.message ?? '';
    return;
  }

  errors.general = payload?.message ?? 'Não foi possível aplicar o reajuste.';
}

function closeModal() {
  if (isSubmitting.value) {
    return;
  }

  emit('close');
}
</script>

<template>
  <transition name="fade">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur"
      tabindex="-1"
      @keydown.esc.prevent.stop="closeModal"
    >
      <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40">
        <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
          <div>
            <h2 class="text-lg font-semibold text-white">Aplicar reajuste</h2>
            <p class="text-xs text-slate-400">
              Contrato {{ props.contrato?.codigo_contrato ?? '-' }} • Próximo reajuste em
              {{ formatDate(props.contrato?.data_proximo_reajuste) }}
            </p>
          </div>
          <button
            type="button"
            class="rounded-md p-2 text-slate-400 transition hover:text-white"
            @click="closeModal"
          >
            <span class="sr-only">Fechar modal</span>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </header>

        <div class="max-h-[75vh] overflow-y-auto px-6 py-5 text-sm text-slate-200">
          <template v-if="!props.contrato">
            <p class="text-sm text-rose-200">
              Não foi possível carregar as informações do contrato para aplicar o reajuste.
            </p>
          </template>
          <template v-else>
            <div class="space-y-6">
              <div class="grid gap-3 rounded-xl border border-slate-800 bg-slate-950/60 p-4 sm:grid-cols-3">
                <div>
                  <p class="text-xs uppercase tracking-wide text-slate-500">Índice</p>
                  <p class="text-sm font-semibold text-white">
                    {{ props.contrato.reajuste_indice ?? '—' }}
                  </p>
                  <p v-if="props.contrato.reajuste_indice === 'OUTRO'" class="text-xs text-slate-400">
                    {{ props.contrato.reajuste_indice_outro ?? '-' }}
                  </p>
                </div>
                <div>
                  <p class="text-xs uppercase tracking-wide text-slate-500">Valor atual</p>
                  <p class="text-sm font-semibold text-white">
                    {{ formatCurrency(valorAtual) }}
                  </p>
                </div>
                <div>
                  <p class="text-xs uppercase tracking-wide text-slate-500">Teto (opcional)</p>
                  <p class="text-sm font-semibold text-white">
                    {{ formatPercentDisplay(props.contrato.reajuste_teto_percentual) }}
                  </p>
                </div>
              </div>

              <div
                v-if="errors.general"
                class="rounded-lg border border-rose-500/40 bg-rose-500/15 px-3 py-2 text-xs text-rose-200"
              >
                {{ errors.general }}
              </div>

              <form class="space-y-4" @submit.prevent="submitReajuste">
                <div>
                  <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-400">
                    Percentual de reajuste *
                  </label>
                  <input
                    v-model="form.percentual"
                    type="number"
                    inputmode="decimal"
                    step="0.01"
                    min="0"
                    placeholder="Ex.: 4.50"
                    class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  />
                  <p v-if="errors.percentual" class="mt-1 text-xs text-rose-400">
                    {{ errors.percentual }}
                  </p>
                  <p class="mt-2 text-xs text-slate-500">
                    Valor calculado após reajuste:
                    <span class="font-semibold text-slate-200">
                      {{ valorCalculado ? formatCurrency(valorCalculado) : '—' }}
                    </span>
                  </p>
                  <p v-if="excedeTeto" class="mt-1 text-xs text-amber-300">
                    O percentual informado excede o teto definido para este contrato.
                  </p>
                </div>

                <div>
                  <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-400">
                    Valor ajustado (opcional)
                  </label>
                  <input
                    v-model="form.valor_novo"
                    type="number"
                    inputmode="decimal"
                    step="0.01"
                    min="0"
                    placeholder="Informe apenas se quiser substituir o cálculo automático"
                    class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  />
                  <p v-if="errors.valor_novo" class="mt-1 text-xs text-rose-400">
                    {{ errors.valor_novo }}
                  </p>
                  <p class="mt-2 text-xs text-slate-500">
                    Valor final considerado:
                    <span class="font-semibold text-slate-200">
                      {{ valorNovoPreview ? formatCurrency(valorNovoPreview) : '—' }}
                    </span>
                  </p>
                </div>

                <div>
                  <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-400">
                    Observações (opcional)
                  </label>
                  <textarea
                    v-model="form.observacoes"
                    rows="3"
                    maxlength="1000"
                    class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    placeholder="Informe detalhes relevantes (índice oficial, período de cálculo, referência etc.)"
                  />
                  <p v-if="errors.observacoes" class="mt-1 text-xs text-rose-400">
                    {{ errors.observacoes }}
                  </p>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3 pt-2">
                  <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:border-slate-500 hover:text-white"
                    @click="closeModal"
                  >
                    Cancelar
                  </button>
                  <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-lg border border-indigo-500/40 bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:border-indigo-400 hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isSubmitting"
                  >
                    <span v-if="isSubmitting">Aplicando…</span>
                    <span v-else>Aplicar reajuste</span>
                  </button>
                </div>
              </form>
            </div>
          </template>
        </div>
      </div>
    </div>
  </transition>
</template>
