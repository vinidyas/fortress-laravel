<script setup lang="ts">
import axios from '@/bootstrap';
import type { AxiosError } from 'axios';
import { reactive, ref, watch } from 'vue';
import { useToast } from '@/composables/useToast';

type Nullable<T> = T | null;

type ContratoForm = {
  codigo_contrato: string;
  imovel_id: Nullable<number>;
  locador_id: Nullable<number>;
  locatario_id: Nullable<number>;
  fiador_id: Nullable<number>;
  data_inicio: string;
  data_fim: string;
  dia_vencimento: Nullable<number>;
  valor_aluguel: string;
  reajuste_indice: string;
  data_proximo_reajuste: string;
  garantia_tipo: string;
  caucao_valor: string;
  taxa_adm_percentual: string;
  status: string;
  observacoes: string;
};

type FormErrors = Partial<
  Record<
    | 'codigo_contrato'
    | 'imovel_id'
    | 'locador_id'
    | 'locatario_id'
    | 'fiador_id'
    | 'data_inicio'
    | 'data_fim'
    | 'dia_vencimento'
    | 'valor_aluguel'
    | 'reajuste_indice'
    | 'data_proximo_reajuste'
    | 'garantia_tipo'
    | 'caucao_valor'
    | 'taxa_adm_percentual'
    | 'status'
    | 'observacoes'
    | 'form'
  , string>
>;

const garantiaOptions = ['Fiador', 'Seguro', 'Caucao', 'SemGarantia'];
const statusOptions = ['Ativo', 'Suspenso', 'Encerrado'];
const reajusteOptions = ['IGPM', 'IPCA', 'INPC'];

const props = defineProps<{
  show: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'created'): void;
}>();

const toast = useToast();

const defaultForm = (): ContratoForm => ({
  codigo_contrato: '',
  imovel_id: null,
  locador_id: null,
  locatario_id: null,
  fiador_id: null,
  data_inicio: '',
  data_fim: '',
  dia_vencimento: null,
  valor_aluguel: '',
  reajuste_indice: 'IGPM',
  data_proximo_reajuste: '',
  garantia_tipo: 'SemGarantia',
  caucao_valor: '',
  taxa_adm_percentual: '',
  status: 'Ativo',
  observacoes: '',
});

const form = reactive<ContratoForm>(defaultForm());
const errors = reactive<FormErrors>({});
const submitting = ref(false);
const formError = ref('');

const resetState = () => {
  Object.assign(form, defaultForm());
  Object.keys(errors).forEach((key) => {
    delete errors[key as keyof FormErrors];
  });
  formError.value = '';
};

watch(
  () => props.show,
  (value) => {
    if (value) {
      resetState();
    }
  }
);

const close = () => {
  if (submitting.value) {
    return;
  }

  emit('close');
};

const normalizeDecimal = (value: string): Nullable<string> => {
  const trimmed = value.trim();
  if (trimmed === '') {
    return null;
  }

  return trimmed;
};

const submit = async () => {
  if (submitting.value) {
    return;
  }

  submitting.value = true;
  Object.keys(errors).forEach((key) => {
    delete errors[key as keyof FormErrors];
  });
  formError.value = '';

  const payload = {
    codigo_contrato: form.codigo_contrato.trim(),
    imovel_id: form.imovel_id,
    locador_id: form.locador_id,
    locatario_id: form.locatario_id,
    fiador_id: form.fiador_id,
    data_inicio: form.data_inicio || null,
    data_fim: form.data_fim || null,
    dia_vencimento: form.dia_vencimento,
    valor_aluguel: normalizeDecimal(form.valor_aluguel),
    reajuste_indice: form.reajuste_indice,
    data_proximo_reajuste: form.data_proximo_reajuste || null,
    garantia_tipo: form.garantia_tipo,
    caucao_valor: normalizeDecimal(form.caucao_valor),
    taxa_adm_percentual: normalizeDecimal(form.taxa_adm_percentual),
    status: form.status,
    observacoes: form.observacoes.trim() || null,
  };

  try {
    const response = await axios.post('/api/contratos', payload);
    toast.success(response.data?.message ?? 'Contrato criado com sucesso.');
    emit('created');
    resetState();
  } catch (error) {
    const axiosError = error as AxiosError<{ errors?: Record<string, string[]>; message?: string }>;

    if (axiosError.response?.status === 422) {
      const validation = axiosError.response.data?.errors ?? {};
      Object.entries(validation).forEach(([key, messages]) => {
        errors[key as keyof FormErrors] = Array.isArray(messages)
          ? messages[0]
          : String(messages);
      });
      formError.value = axiosError.response.data?.message ?? 'Corrija os campos destacados e tente novamente.';
      return;
    }

    const message = axiosError.response?.data?.message ?? 'Nao foi possivel salvar o contrato.';
    formError.value = message;
    toast.error(message);
  } finally {
    submitting.value = false;
  }
};
</script>

<template>
  <transition name="fade">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur"
      @keydown.esc.prevent.stop='close'
    >
      <div
        class="relative w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40"
      >
        <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
          <div>
            <h2 class="text-lg font-semibold text-white">Novo contrato</h2>
            <p class="text-xs text-slate-400">Informe os dados do contrato para concluir o cadastro.</p>
          </div>
          <button
            type="button"
            class="rounded-md p-2 text-slate-400 transition hover:text-white"
            @click="close"
          >
            <span class="sr-only">Fechar</span>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </header>

        <form class="max-h-[80vh] overflow-y-auto px-6 py-5" @submit.prevent="submit">
          <div
            v-if="formError"
            class="mb-4 rounded-lg border border-rose-500/40 bg-rose-500/15 px-4 py-2 text-sm text-rose-100"
          >
            {{ formError }}
          </div>

          <div class="grid gap-5 md:grid-cols-2">
            <div class="space-y-4">
              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-slate-200">Codigo *</label>
                <input
                  v-model="form.codigo_contrato"
                  type="text"
                  required
                  maxlength="30"
                  class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  placeholder="CTR-0001"
                />
                <p v-if="errors.codigo_contrato" class="text-xs text-rose-400">{{ errors.codigo_contrato }}</p>
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-slate-200">Imovel ID *</label>
                <input
                  v-model.number="form.imovel_id"
                  type="number"
                  min="1"
                  required
                  class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  placeholder="ID do imovel"
                />
                <p v-if="errors.imovel_id" class="text-xs text-rose-400">{{ errors.imovel_id }}</p>
              </div>

              <div class="grid gap-4 md:grid-cols-2">
                <div class="flex flex-col gap-1">
                  <label class="text-sm font-medium text-slate-200">Locador ID *</label>
                  <input
                    v-model.number="form.locador_id"
                    type="number"
                    min="1"
                    required
                    class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  />
                  <p v-if="errors.locador_id" class="text-xs text-rose-400">{{ errors.locador_id }}</p>
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-sm font-medium text-slate-200">Locatario ID *</label>
                  <input
                    v-model.number="form.locatario_id"
                    type="number"
                    min="1"
                    required
                    class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  />
                  <p v-if="errors.locatario_id" class="text-xs text-rose-400">{{ errors.locatario_id }}</p>
                </div>
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-slate-200">Fiador ID</label>
                <input
                  v-model.number="form.fiador_id"
                  type="number"
                  min="1"
                  class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  placeholder="Opcional"
                />
                <p v-if="errors.fiador_id" class="text-xs text-rose-400">{{ errors.fiador_id }}</p>
              </div>

              <div class="grid gap-4 md:grid-cols-2">
                <div class="flex flex-col gap-1">
                  <label class="text-sm font-medium text-slate-200">Data inicio *</label>
                  <input
                    v-model="form.data_inicio"
                    type="date"
                    required
                    class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  />
                  <p v-if="errors.data_inicio" class="text-xs text-rose-400">{{ errors.data_inicio }}</p>
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-sm font-medium text-slate-200">Data fim</label>
                  <input
                    v-model="form.data_fim"
                    type="date"
                    class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  />
                  <p v-if="errors.data_fim" class="text-xs text-rose-400">{{ errors.data_fim }}</p>
                </div>
              </div>

              <div class="grid gap-4 md:grid-cols-2">
                <div class="flex flex-col gap-1">
                  <label class="text-sm font-medium text-slate-200">Dia vencimento *</label>
                  <input
                    v-model.number="form.dia_vencimento"
                    type="number"
                    min="1"
                    max="28"
                    required
                    class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  />
                  <p v-if="errors.dia_vencimento" class="text-xs text-rose-400">{{ errors.dia_vencimento }}</p>
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-sm font-medium text-slate-200">Valor aluguel *</label>
                  <input
                    v-model="form.valor_aluguel"
                    type="text"
                    inputmode="decimal"
                    required
                    placeholder="0.00"
                    class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  />
                  <p v-if="errors.valor_aluguel" class="text-xs text-rose-400">{{ errors.valor_aluguel }}</p>
                </div>
              </div>
            </div>

            <div class="space-y-4">
              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-slate-200">Indice de reajuste</label>
                <select
                  v-model="form.reajuste_indice"
                  class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                >
                  <option v-for="option in reajusteOptions" :key="option" :value="option">
                    {{ option }}
                  </option>
                </select>
                <p v-if="errors.reajuste_indice" class="text-xs text-rose-400">{{ errors.reajuste_indice }}</p>
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-slate-200">Data proximo reajuste</label>
                <input
                  v-model="form.data_proximo_reajuste"
                  type="date"
                  class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
                <p v-if="errors.data_proximo_reajuste" class="text-xs text-rose-400">{{ errors.data_proximo_reajuste }}</p>
              </div>

              <div class="grid gap-4 md:grid-cols-2">
                <div class="flex flex-col gap-1">
                  <label class="text-sm font-medium text-slate-200">Garantia</label>
                  <select
                    v-model="form.garantia_tipo"
                    class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  >
                    <option v-for="option in garantiaOptions" :key="option" :value="option">
                      {{ option }}
                    </option>
                  </select>
                  <p v-if="errors.garantia_tipo" class="text-xs text-rose-400">{{ errors.garantia_tipo }}</p>
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-sm font-medium text-slate-200">Valor caucao</label>
                  <input
                    v-model="form.caucao_valor"
                    type="text"
                    inputmode="decimal"
                    placeholder="0.00"
                    class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  />
                  <p v-if="errors.caucao_valor" class="text-xs text-rose-400">{{ errors.caucao_valor }}</p>
                </div>
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-slate-200">Taxa adm (%)</label>
                <input
                  v-model="form.taxa_adm_percentual"
                  type="text"
                  inputmode="decimal"
                  placeholder="0.00"
                  class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
                <p v-if="errors.taxa_adm_percentual" class="text-xs text-rose-400">{{ errors.taxa_adm_percentual }}</p>
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-slate-200">Status</label>
                <select
                  v-model="form.status"
                  class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                >
                  <option v-for="option in statusOptions" :key="option" :value="option">
                    {{ option }}
                  </option>
                </select>
                <p v-if="errors.status" class="text-xs text-rose-400">{{ errors.status }}</p>
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-slate-200">Observacoes</label>
                <textarea
                  v-model="form.observacoes"
                  rows="6"
                  class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  placeholder="Notas adicionais sobre o contrato"
                ></textarea>
                <p v-if="errors.observacoes" class="text-xs text-rose-400">{{ errors.observacoes }}</p>
              </div>
            </div>
          </div>

          <div class="mt-6 flex items-center justify-end gap-2 border-t border-slate-800 pt-4">
            <button
              type="button"
              class="rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-200 transition hover:bg-slate-800"
              @click="close"
              :disabled="submitting"
            >
              Cancelar
            </button>
            <button
              type="submit"
              class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 disabled:opacity-60"
              :disabled="submitting"
            >
              <svg
                v-if="submitting"
                class="h-4 w-4 animate-spin"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M12 4v2" />
                <path d="M18.364 5.636l-1.414 1.414" />
                <path d="M20 12h-2" />
                <path d="M18.364 18.364l-1.414-1.414" />
                <path d="M12 20v-2" />
                <path d="M5.636 18.364l1.414-1.414" />
                <path d="M4 12h2" />
                <path d="M5.636 5.636l1.414 1.414" />
              </svg>
              {{ submitting ? 'Salvando...' : 'Salvar' }}
            </button>
          </div>
        </form>
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



