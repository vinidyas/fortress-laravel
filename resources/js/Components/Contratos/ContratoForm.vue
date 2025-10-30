<script setup lang="ts">
import axios from '@/bootstrap';
import type { AxiosError } from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useToast } from '@/composables/useToast';
import DatePicker from '@/Components/Form/DatePicker.vue';
import MoneyInput from '@/Components/Form/MoneyInput.vue';
import ImovelSelect from '@/Components/Contratos/ImovelSelect.vue';
import PessoaSelect from '@/Components/Pessoas/PessoaSelect.vue';

interface ExistingAttachment {
  id: number;
  original_name: string;
  url: string;
  marked?: boolean;
}

interface Props {
  mode: 'create' | 'edit';
  contratoId?: number | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{ (e: 'saved'): void; (e: 'cancel'): void }>();

const toast = useToast();
const loading = ref(true);
const saving = ref(false);
const formError = ref('');
const errors = reactive<Record<string, string>>({});

const garantiaOptions = [
  { value: 'Fiador', label: 'Fiador' },
  { value: 'Seguro', label: 'Seguro fiança' },
  { value: 'Caucao', label: 'Caução' },
  { value: 'SemGarantia', label: 'Sem garantia' },
];

const statusOptions = [
  { value: 'Ativo', label: 'Ativo' },
  { value: 'EmAnalise', label: 'Em análise' },
  { value: 'Suspenso', label: 'Suspenso' },
  { value: 'Encerrado', label: 'Encerrado' },
  { value: 'Rescindido', label: 'Rescindido' },
];

const reajusteOptions = [
  { value: 'IGPM', label: 'IGP-M' },
  { value: 'IGPDI', label: 'IGP-DI' },
  { value: 'IPCA', label: 'IPCA' },
  { value: 'IPCA15', label: 'IPCA-15' },
  { value: 'INPC', label: 'INPC' },
  { value: 'TR', label: 'TR' },
  { value: 'SELIC', label: 'SELIC' },
  { value: 'OUTRO', label: 'Outro índice' },
  { value: 'SEM_REAJUSTE', label: 'Sem reajuste' },
];

const formaPagamentoOptions = [
  { value: 'Boleto', label: 'Boleto' },
  { value: 'Pix', label: 'Pix' },
  { value: 'Deposito', label: 'Depósito' },
  { value: 'Transferencia', label: 'Transferência' },
  { value: 'CartaoCredito', label: 'Cartão de crédito' },
  { value: 'Dinheiro', label: 'Dinheiro' },
];

const tipoContratoOptions = [
  { value: 'Residencial', label: 'Residencial' },
  { value: 'Comercial', label: 'Comercial' },
  { value: 'Temporada', label: 'Temporada' },
  { value: 'Outros', label: 'Outros' },
];

const form = reactive({
  codigo_contrato: '',
  imovel_id: null as number | null,
  locador_id: null as number | null,
  locatario_id: null as number | null,
  data_inicio: '',
  data_fim: '',
  dia_vencimento: '' as string | number,
  carencia_meses: '',
  data_entrega_chaves: '',
  valor_aluguel: '',
  reajuste_indice: 'IGPM',
  reajuste_indice_outro: '',
  reajuste_periodicidade_meses: '12',
  reajuste_teto_percentual: '',
  data_proximo_reajuste: '',
  garantia_tipo: 'SemGarantia',
  caucao_valor: '',
  multa_atraso_percentual: '',
  juros_mora_percentual_mes: '',
  multa_rescisao_alugueis: '3',
  repasse_automatico: true,
  conta_cobranca_id: '' as string | number,
  forma_pagamento_preferida: '',
  tipo_contrato: '',
  status: 'Ativo',
  observacoes: '',
});

const fiadores = ref<(number | null)[]>([]);
const newAttachments = ref<File[]>([]);
const existingAttachments = ref<ExistingAttachment[]>([]);

const toDateInputValue = (value?: string | null): string => {
  if (!value) return '';
  const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!match) return '';
  const [, year, month, day] = match;
  return `${year}-${month}-${day}`;
};

const toMonthInputValue = (value?: string | null): string => {
  if (!value) return '';
  const match = String(value).match(/^(\d{4})-(\d{2})/);
  if (!match) return '';
  const [, year, month] = match;
  return `${year}-${month}`;
};

const monthInputToIso = (value: string): string => {
  if (!value) return '';
  const match = value.match(/^(\d{4})-(\d{2})$/);
  if (!match) return '';
  const [, year, month] = match;
  return `${year}-${month}-01`;
};

const showCaucaoValor = computed(() => form.garantia_tipo === 'Caucao');
const showFiadorCampos = computed(() => form.garantia_tipo === 'Fiador');
const normalizedFiadores = computed(() => fiadores.value.filter((id): id is number => typeof id === 'number' && Number.isFinite(id)));
const showReajusteCampos = computed(() => form.reajuste_indice !== 'SEM_REAJUSTE');
const arquivosParaRemover = computed(() => existingAttachments.value.filter((a) => a.marked).map((a) => a.id));

const clearErrors = () => {
  Object.keys(errors).forEach((k) => delete errors[k]);
};

const resetForm = () => {
  form.codigo_contrato = '';
  form.imovel_id = null;
  form.locador_id = null;
  form.locatario_id = null;
  form.data_inicio = '';
  form.data_fim = '';
  form.dia_vencimento = '';
  form.carencia_meses = '';
  form.data_entrega_chaves = '';
  form.valor_aluguel = '';
  form.reajuste_indice = 'IGPM';
  form.reajuste_indice_outro = '';
  form.reajuste_periodicidade_meses = '12';
  form.reajuste_teto_percentual = '';
  form.data_proximo_reajuste = '';
  form.garantia_tipo = 'SemGarantia';
  form.caucao_valor = '';
  form.multa_atraso_percentual = '';
  form.juros_mora_percentual_mes = '';
  form.multa_rescisao_alugueis = '3';
  form.repasse_automatico = true;
  form.conta_cobranca_id = '';
  form.forma_pagamento_preferida = '';
  form.tipo_contrato = '';
  form.status = 'Ativo';
  form.observacoes = '';
  fiadores.value = [];
  newAttachments.value = [];
  existingAttachments.value = [];
  clearErrors();
  formError.value = '';
};

const formatDecimal = (value: unknown): string => {
  if (value === null || value === undefined || value === '') return '';
  return String(value);
};

const fetchGeneratedCodigo = async (withLoader = false): Promise<void> => {
  if (withLoader) {
    loading.value = true;
  }

  try {
    const { data } = await axios.get('/api/contratos/generate-codigo');
    const codigo = typeof data?.codigo === 'string' ? data.codigo : '';

    if (!codigo) {
      throw new Error('Código vazio retornado pela API.');
    }

    form.codigo_contrato = codigo;
    delete errors.codigo_contrato;
    if (formError.value && formError.value.includes('código')) {
      formError.value = '';
    }
  } catch (error) {
    console.error(error);
    form.codigo_contrato = '';
    const message = 'Não foi possível gerar um código de contrato automaticamente. Tente novamente.';
    formError.value = message;
    toast.error(message);
  } finally {
    if (withLoader) {
      loading.value = false;
    }
  }
};

const loadContrato = async () => {
  if (props.mode !== 'edit' || !props.contratoId) {
    loading.value = false;
    return;
  }

  loading.value = true;
  try {
    const { data } = await axios.get(`/api/contratos/${props.contratoId}`);
    const payload = data.data;

    form.codigo_contrato = payload.codigo_contrato ?? '';
    form.imovel_id = payload.imovel_id ?? null;
    form.locador_id = payload.locador_id ?? null;
    form.locatario_id = payload.locatario_id ?? null;
    form.data_inicio = toDateInputValue(payload.data_inicio);
    form.data_fim = toDateInputValue(payload.data_fim);
    form.dia_vencimento = payload.dia_vencimento ?? '';
    form.carencia_meses = payload.carencia_meses ?? '';
    form.data_entrega_chaves = toDateInputValue(payload.data_entrega_chaves);
    form.valor_aluguel = formatDecimal(payload.valor_aluguel);
    form.reajuste_indice = payload.reajuste_indice ?? 'IGPM';
    form.reajuste_indice_outro = payload.reajuste_indice_outro ?? '';
    form.reajuste_periodicidade_meses = payload.reajuste_periodicidade_meses ?? '';
    form.reajuste_teto_percentual = formatDecimal(payload.reajuste_teto_percentual);
    form.data_proximo_reajuste = toMonthInputValue(payload.data_proximo_reajuste);
    form.garantia_tipo = payload.garantia_tipo ?? 'SemGarantia';
    form.caucao_valor = formatDecimal(payload.caucao_valor);
    form.multa_atraso_percentual = formatDecimal(payload.multa_atraso_percentual);
    form.juros_mora_percentual_mes = formatDecimal(payload.juros_mora_percentual_mes);
    form.multa_rescisao_alugueis = formatDecimal(payload.multa_rescisao_alugueis ?? '3');
    form.repasse_automatico = Boolean(payload.repasse_automatico);
    form.conta_cobranca_id = payload.conta_cobranca_id ?? '';
    form.forma_pagamento_preferida = payload.forma_pagamento_preferida ?? '';
    form.tipo_contrato = payload.tipo_contrato ?? '';
    form.status = payload.status ?? 'Ativo';
    form.observacoes = payload.observacoes ?? '';
    fiadores.value = (payload.fiadores ?? []).map((f: { id: number }) => f.id);
    if (form.garantia_tipo === 'Fiador' && fiadores.value.length === 0) {
      fiadores.value.push(null);
    }
    existingAttachments.value = (payload.anexos ?? []).map((a: ExistingAttachment) => ({ ...a, marked: false }));
  } catch (error) {
    console.error(error);
    toast.error('Não foi possível carregar o contrato.');
  } finally {
    loading.value = false;
  }
};

const handleFilesSelected = (event: Event) => {
  const target = event.target as HTMLInputElement;
  if (!target.files) return;
  newAttachments.value.push(...Array.from(target.files));
  target.value = '';
};

const removeNewAttachment = (index: number) => newAttachments.value.splice(index, 1);
const toggleExistingAttachment = (a: ExistingAttachment) => { a.marked = !a.marked; };

const submit = async () => {
  if (saving.value) return;
  saving.value = true;
  clearErrors();
  formError.value = '';

  if (!form.codigo_contrato) {
    await fetchGeneratedCodigo();
    if (!form.codigo_contrato) {
      saving.value = false;
      return;
    }
  }

  const formData = new FormData();
  formData.append('codigo_contrato', String(form.codigo_contrato));
  formData.append('imovel_id', String(form.imovel_id ?? ''));
  formData.append('locador_id', form.locador_id ? String(form.locador_id) : '');
  formData.append('locatario_id', form.locatario_id ? String(form.locatario_id) : '');
  formData.append('data_inicio', form.data_inicio || '');
  formData.append('data_fim', form.data_fim || '');
  formData.append('dia_vencimento', String(form.dia_vencimento ?? ''));
  formData.append('carencia_meses', form.carencia_meses ?? '');
  formData.append('data_entrega_chaves', form.data_entrega_chaves || '');
  formData.append('valor_aluguel', form.valor_aluguel ?? '');
  formData.append('reajuste_indice', form.reajuste_indice ?? '');
  formData.append('reajuste_indice_outro', form.reajuste_indice === 'OUTRO' ? form.reajuste_indice_outro.trim() : '');
  formData.append('reajuste_periodicidade_meses', form.reajuste_periodicidade_meses ?? '');
  formData.append('reajuste_teto_percentual', form.reajuste_teto_percentual ?? '');
  formData.append('data_proximo_reajuste', monthInputToIso(form.data_proximo_reajuste) || '');
  formData.append('garantia_tipo', form.garantia_tipo ?? '');
  formData.append('caucao_valor', form.caucao_valor ?? '');
  formData.append('multa_atraso_percentual', form.multa_atraso_percentual ?? '');
  formData.append('juros_mora_percentual_mes', form.juros_mora_percentual_mes ?? '');
  formData.append('multa_rescisao_alugueis', form.multa_rescisao_alugueis ?? '');
  formData.append('repasse_automatico', form.repasse_automatico ? '1' : '0');
  formData.append('conta_cobranca_id', String(form.conta_cobranca_id ?? ''));
  formData.append('forma_pagamento_preferida', form.forma_pagamento_preferida ?? '');
  formData.append('tipo_contrato', form.tipo_contrato ?? '');
  formData.append('status', form.status ?? '');
  formData.append('observacoes', form.observacoes ?? '');
  normalizedFiadores.value.forEach((id, index) => formData.append(`fiadores[${index}]`, String(id)));

  arquivosParaRemover.value.forEach((id, index) => formData.append(`anexos_remover[${index}]`, String(id)));
  newAttachments.value.forEach((file) => formData.append('anexos[]', file, file.name));

  const url = props.mode === 'edit' && props.contratoId ? `/api/contratos/${props.contratoId}` : '/api/contratos';
  if (props.mode === 'edit') formData.append('_method', 'PUT');

  try {
    await axios.post(url, formData, { headers: { 'Content-Type': 'multipart/form-data' } });
    toast.success(props.mode === 'create' ? 'Contrato criado com sucesso.' : 'Contrato atualizado com sucesso.');
    if (props.mode === 'create') {
      resetForm();
      await fetchGeneratedCodigo();
    } else {
      await loadContrato();
    }
    emit('saved');
  } catch (error) {
    const axiosError = error as AxiosError<{ errors?: Record<string, string[]>; message?: string }>;
    if (axiosError.response?.status === 422) {
      const validation = axiosError.response.data?.errors ?? {};
      Object.entries(validation).forEach(([key, messages]) => { errors[key] = Array.isArray(messages) ? messages[0] : String(messages); });
      formError.value = axiosError.response.data?.message ?? 'Corrija os campos destacados e tente novamente.';
      return;
    }
    const message = axiosError.response?.data?.message ?? 'Não foi possível salvar o contrato.';
    formError.value = message;
    toast.error(message);
  } finally {
    saving.value = false;
  }
};

watch(() => form.garantia_tipo, (v) => {
  if (v !== 'Caucao') form.caucao_valor = '';
  if (v === 'Fiador') {
    if (fiadores.value.length === 0) fiadores.value.push(null);
  } else {
    fiadores.value = [];
  }
});
watch(() => form.reajuste_indice, (v) => {
  if (v === 'SEM_REAJUSTE') {
    form.reajuste_periodicidade_meses = '';
    form.data_proximo_reajuste = '';
    form.reajuste_teto_percentual = '';
  } else {
    if (!form.reajuste_periodicidade_meses) {
      form.reajuste_periodicidade_meses = '12';
    }
    if (v !== 'OUTRO') {
      form.reajuste_indice_outro = '';
    }
  }
});

watch(
  () => props.mode,
  (mode) => {
    if (mode === 'create') {
      form.garantia_tipo = 'SemGarantia';
      form.caucao_valor = '';
    }
  },
  { immediate: true }
);

onMounted(() => {
  if (props.mode === 'edit') {
    void loadContrato();
  } else {
    void fetchGeneratedCodigo(true);
  }
});
watch(
  () => props.contratoId,
  () => {
    if (props.mode === 'edit') {
      void loadContrato();
    }
  }
);
</script>

<template>
  <div class="space-y-6" v-if="!loading">
    <div v-if="formError" class="rounded-lg border border-rose-500/40 bg-rose-500/15 px-4 py-2 text-sm text-rose-100">
      {{ formError }}
    </div>

    <form class="space-y-8" @submit.prevent="submit">
      <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-5 shadow-inner shadow-black/20">
        <header class="flex items-center gap-3">
          <span class="h-6 w-1 rounded-full bg-indigo-500"></span>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Identificação</h3>
        </header>
        <div class="grid gap-6 lg:grid-cols-[repeat(12,minmax(0,1fr))]">
          <div class="lg:col-span-3 flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-200">Código *</label>
          <input
            v-model="form.codigo_contrato"
            type="text"
            required
            readonly
            maxlength="30"
            class="rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-white read-only:cursor-not-allowed read-only:bg-slate-900/60 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            title="Código gerado automaticamente"
          />
          <p v-if="errors.codigo_contrato" class="text-xs text-rose-400">{{ errors.codigo_contrato }}</p>
        </div>
          <div class="lg:col-span-9">
            <ImovelSelect
              v-model="form.imovel_id"
              label="Imóvel"
              required
              :disabled="saving"
              :error="errors.imovel_id ?? null"
            />
          </div>
          <div class="lg:col-span-6">
            <PessoaSelect
              v-model="form.locador_id"
              label="Proprietário"
              role="Proprietario"
              required
              :disabled="saving"
              :error="errors.locador_id ?? null"
            />
          </div>
          <div class="lg:col-span-6">
            <PessoaSelect
              v-model="form.locatario_id"
              label="Locatário"
              role="Locatario"
              required
              :disabled="saving"
              :error="errors.locatario_id ?? null"
            />
          </div>
        </div>
      </section>

      <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-5 shadow-inner shadow-black/20">
        <header class="flex items-center gap-3">
          <span class="h-6 w-1 rounded-full bg-cyan-500"></span>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Vigência</h3>
        </header>
        <div class="grid gap-6 lg:grid-cols-3">
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-200">Data início *</label>
          <DatePicker v-model="form.data_inicio" placeholder="dd/mm/aaaa" required :invalid="Boolean(errors.data_inicio)" />
          <p v-if="errors.data_inicio" class="text-xs text-rose-400">{{ errors.data_inicio }}</p>
        </div>
          <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Data fim</label>
          <DatePicker v-model="form.data_fim" placeholder="dd/mm/aaaa" :invalid="Boolean(errors.data_fim)" />
          <p v-if="errors.data_fim" class="text-xs text-rose-400">{{ errors.data_fim }}</p>
        </div>
          <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Dia de vencimento *</label>
          <input v-model="form.dia_vencimento" type="number" min="1" max="28" required class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
          <p v-if="errors.dia_vencimento" class="text-xs text-rose-400">{{ errors.dia_vencimento }}</p>
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Carência (meses)</label>
          <input v-model="form.carencia_meses" type="number" min="0" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
          <p v-if="errors.carencia_meses" class="text-xs text-rose-400">{{ errors.carencia_meses }}</p>
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Entrega das chaves</label>
          <DatePicker v-model="form.data_entrega_chaves" placeholder="dd/mm/aaaa" :invalid="Boolean(errors.data_entrega_chaves)" />
          <p v-if="errors.data_entrega_chaves" class="text-xs text-rose-400">{{ errors.data_entrega_chaves }}</p>
        </div>
        </div>
      </section>

      <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-5 shadow-inner shadow-black/20">
        <header class="flex items-center gap-3">
          <span class="h-6 w-1 rounded-full bg-amber-500"></span>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Financeiro</h3>
        </header>
        <div class="grid gap-6 lg:grid-cols-3">
          <div class="flex flex-col gap-1 lg:col-span-3">
            <label class="text-sm font-medium text-slate-200">Valor do aluguel *</label>
            <MoneyInput
              v-model="form.valor_aluguel"
              required
              :input-class="'border-slate-700 bg-slate-900 text-white'"
            />
            <p v-if="errors.valor_aluguel" class="text-xs text-rose-400">{{ errors.valor_aluguel }}</p>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-200">Multa por atraso</label>
            <div class="relative">
              <input
                v-model="form.multa_atraso_percentual"
                type="text"
                inputmode="decimal"
                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 pr-12 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              />
              <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs uppercase tracking-wide text-slate-400">%</span>
            </div>
            <p v-if="errors.multa_atraso_percentual" class="text-xs text-rose-400">{{ errors.multa_atraso_percentual }}</p>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-200">Juros por atraso (ao mês)</label>
            <div class="relative">
              <input
                v-model="form.juros_mora_percentual_mes"
                type="text"
                inputmode="decimal"
                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 pr-12 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              />
              <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs uppercase tracking-wide text-slate-400">%</span>
            </div>
            <p v-if="errors.juros_mora_percentual_mes" class="text-xs text-rose-400">{{ errors.juros_mora_percentual_mes }}</p>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-200">Multa rescisão de contrato *</label>
            <div class="relative">
              <input
                v-model="form.multa_rescisao_alugueis"
                type="number"
                min="0"
                step="0.1"
                required
                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 pr-20 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              />
              <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs uppercase tracking-wide text-slate-400">
                aluguéis
              </span>
            </div>
            <p v-if="errors.multa_rescisao_alugueis" class="text-xs text-rose-400">{{ errors.multa_rescisao_alugueis }}</p>
          </div>
        </div>
      </section>

      <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-5 shadow-inner shadow-black/20">
        <header class="flex items-center gap-3">
          <span class="h-6 w-1 rounded-full bg-purple-500"></span>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Reajuste</h3>
        </header>
          <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Índice de reajuste</label>
          <select v-model="form.reajuste_indice" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
            <option v-for="option in reajusteOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
          <p v-if="errors.reajuste_indice" class="text-xs text-rose-400">{{ errors.reajuste_indice }}</p>
        </div>
        <div v-if="form.reajuste_indice === 'OUTRO'" class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Informe o índice personalizado *</label>
          <input
            v-model="form.reajuste_indice_outro"
            type="text"
            maxlength="60"
            placeholder="Ex.: Índice setorial, contrato coletivo"
            class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
          />
          <p v-if="errors.reajuste_indice_outro" class="text-xs text-rose-400">{{ errors.reajuste_indice_outro }}</p>
        </div>
        <div v-if="showReajusteCampos" class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Periodicidade (meses)</label>
          <input v-model="form.reajuste_periodicidade_meses" type="number" min="1" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
          <p v-if="errors.reajuste_periodicidade_meses" class="text-xs text-rose-400">{{ errors.reajuste_periodicidade_meses }}</p>
        </div>
        <div v-if="showReajusteCampos" class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Teto de reajuste (%)</label>
          <input
            v-model="form.reajuste_teto_percentual"
            type="text"
            inputmode="decimal"
            placeholder="Opcional"
            class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
          />
          <p v-if="errors.reajuste_teto_percentual" class="text-xs text-rose-400">{{ errors.reajuste_teto_percentual }}</p>
        </div>
        <div v-if="showReajusteCampos" class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Próximo reajuste</label>
          <DatePicker v-model="form.data_proximo_reajuste" mode="month" placeholder="mm/aaaa" :invalid="Boolean(errors.data_proximo_reajuste)" />
          <p v-if="errors.data_proximo_reajuste" class="text-xs text-rose-400">{{ errors.data_proximo_reajuste }}</p>
        </div>
      </section>

      <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-5 shadow-inner shadow-black/20">
        <header class="flex items-center gap-3">
          <span class="h-6 w-1 rounded-full bg-fuchsia-500"></span>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Garantias</h3>
        </header>
        <div class="grid gap-6 lg:grid-cols-3">
          <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Tipo *</label>
          <select v-model="form.garantia_tipo" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
            <option v-for="option in garantiaOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
          <p v-if="errors.garantia_tipo" class="text-xs text-rose-400">{{ errors.garantia_tipo }}</p>
        </div>
          <div v-if="showCaucaoValor" class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Valor caução *</label>
          <input v-model="form.caucao_valor" type="text" inputmode="decimal" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
          <p v-if="errors.caucao_valor" class="text-xs text-rose-400">{{ errors.caucao_valor }}</p>
        </div>
        </div>
        <div v-if="showFiadorCampos" class="space-y-3">
          <div
            v-for="(fiadorId, index) in fiadores"
            :key="`fiador-${index}`"
            class="flex items-start gap-3"
          >
            <div class="flex-1">
              <PessoaSelect
                v-model="fiadores[index]"
                :label="fiadores.length > 1 ? `Fiador ${index + 1}` : 'Fiador'"
                role="Fiador"
                :disabled="saving"
                :error="errors[`fiadores.${index}`] ?? null"
              />
            </div>
            <button
              type="button"
              class="mt-6 inline-flex items-center rounded-md border border-rose-500/60 px-3 py-2 text-xs font-medium text-rose-200 transition hover:bg-rose-500/20"
              @click="fiadores.splice(index, 1)"
              v-if="fiadores.length > 1"
            >
              Remover
            </button>
          </div>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-md border border-slate-700 px-3 py-2 text-sm text-slate-200 transition hover:bg-slate-800"
            @click="fiadores.push(null)"
          >
            + Adicionar fiador
          </button>
          <p v-if="errors.fiadores" class="text-xs text-rose-400">{{ errors.fiadores }}</p>
        </div>
      </section>

      <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-5 shadow-inner shadow-black/20">
        <header class="flex items-center gap-3">
          <span class="h-6 w-1 rounded-full bg-teal-500"></span>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Classificação e pagamento</h3>
        </header>
        <div class="grid gap-6 lg:grid-cols-3">
          <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Tipo de contrato</label>
          <select v-model="form.tipo_contrato" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
            <option value="">Selecione</option>
            <option v-for="option in tipoContratoOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
          <p v-if="errors.tipo_contrato" class="text-xs text-rose-400">{{ errors.tipo_contrato }}</p>
        </div>
          <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Forma de pagamento preferida</label>
          <select v-model="form.forma_pagamento_preferida" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
            <option value="">Selecione</option>
            <option v-for="option in formaPagamentoOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
          <p v-if="errors.forma_pagamento_preferida" class="text-xs text-rose-400">{{ errors.forma_pagamento_preferida }}</p>
        </div>
          <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Status</label>
          <select v-model="form.status" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
            <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
          <p v-if="errors.status" class="text-xs text-rose-400">{{ errors.status }}</p>
        </div>
        </div>
      </section>

      <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-5 shadow-inner shadow-black/20">
        <header class="flex flex-col gap-1">
          <div class="flex items-center gap-3">
            <span class="h-6 w-1 rounded-full bg-slate-500"></span>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Anexos</h3>
          </div>
          <p class="text-xs text-slate-400">Envie documentos pertinentes ao contrato (PDF ou imagem).</p>
        </header>
        <div class="flex flex-col gap-2">
          <input type="file" multiple accept=".pdf,.jpg,.jpeg,.png" class="text-sm text-slate-200 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-indigo-500" @change="handleFilesSelected" />
          <ul class="list-inside list-disc text-xs text-slate-400">
            <li v-for="(file, index) in newAttachments" :key="index" class="flex items-center justify-between gap-2">
              <span>{{ file.name }}</span>
              <button type="button" class="text-rose-300 transition hover:text-rose-200" @click="removeNewAttachment(index)">remover</button>
            </li>
          </ul>
        </div>
        <div v-if="existingAttachments.length" class="space-y-2">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Anexos atuais</p>
          <ul class="space-y-1 text-xs text-slate-300">
            <li v-for="attachment in existingAttachments" :key="attachment.id" class="flex items-center justify-between gap-3">
              <a :href="attachment.url" class="text-indigo-300 underline hover:text-indigo-200" target="_blank" rel="noopener">{{ attachment.original_name }}</a>
              <label class="inline-flex items-center gap-1 text-rose-300">
                <input type="checkbox" :checked="attachment.marked" @change="toggleExistingAttachment(attachment)" />
                remover
              </label>
            </li>
          </ul>
        </div>
      </section>

      <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-5 shadow-inner shadow-black/20">
        <header class="flex items-center gap-3">
          <span class="h-6 w-1 rounded-full bg-rose-500"></span>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Observações</h3>
        </header>
        <textarea v-model="form.observacoes" rows="4" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"></textarea>
        <p v-if="errors.observacoes" class="text-xs text-rose-400">{{ errors.observacoes }}</p>
      </section>

      <div class="flex items-center justify-end gap-2">
        <button type="button" class="rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-200 transition hover:bg-slate-800" :disabled="saving" @click="emit('cancel')">Cancelar</button>
        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 disabled:opacity-60" :disabled="saving">
          <svg v-if="saving" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 4v2" />
            <path d="M18.364 5.636l-1.414 1.414" />
            <path d="M20 12h-2" />
            <path d="M18.364 18.364l-1.414-1.414" />
            <path d="M12 20v-2" />
            <path d="M5.636 18.364l1.414-1.414" />
            <path d="M4 12h2" />
            <path d="M5.636 5.636l1.414 1.414" />
          </svg>
          {{ saving ? 'Salvando...' : props.mode === 'create' ? 'Salvar' : 'Atualizar' }}
        </button>
      </div>
    </form>
  </div>
  <div v-else class="flex items-center justify-center py-12 text-sm text-slate-400">Carregando dados do contrato...</div>
</template>

<style scoped>
.list-disc li::marker { color: rgb(148 163 184); }
</style>
