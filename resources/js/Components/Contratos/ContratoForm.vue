<script setup lang="ts">
import axios from '@/bootstrap';
import type { AxiosError } from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useToast } from '@/composables/useToast';

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
const loading = ref(props.mode === 'edit');
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
  { value: 'IPCA', label: 'IPCA' },
  { value: 'INPC', label: 'INPC' },
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
  imovel_id: '' as string | number,
  locador_id: '' as string | number,
  locatario_id: '' as string | number,
  data_inicio: '',
  data_fim: '',
  dia_vencimento: '' as string | number,
  prazo_meses: '',
  carencia_meses: '',
  data_entrega_chaves: '',
  valor_aluguel: '',
  desconto_mensal: '',
  reajuste_indice: 'IGPM',
  reajuste_periodicidade_meses: '12',
  data_proximo_reajuste: '',
  garantia_tipo: 'SemGarantia',
  caucao_valor: '',
  taxa_adm_percentual: '',
  multa_atraso_percentual: '',
  juros_mora_percentual_mes: '',
  repasse_automatico: true,
  conta_cobranca_id: '' as string | number,
  forma_pagamento_preferida: '',
  tipo_contrato: '',
  status: 'Ativo',
  observacoes: '',
});

const fiadores = ref<number[]>([]);
const newAttachments = ref<File[]>([]);
const existingAttachments = ref<ExistingAttachment[]>([]);

const maskDate = (value: string): string => {
  const digits = value.replace(/\D/g, '').slice(0, 8);
  const parts = [];
  if (digits.length > 0) parts.push(digits.slice(0, 2));
  if (digits.length > 2) parts.push(digits.slice(2, 4));
  if (digits.length > 4) parts.push(digits.slice(4, 8));
  return parts.join('/');
};

const maskMonthYear = (value: string): string => {
  const digits = value.replace(/\D/g, '').slice(0, 6);
  const parts = [];
  if (digits.length > 0) parts.push(digits.slice(0, 2));
  if (digits.length > 2) parts.push(digits.slice(2, 6));
  return parts.join('/');
};

const isoToBrDate = (value?: string | null): string => {
  if (!value) return '';
  if (value.includes('/')) return value;
  const [year, month, day] = value.split('-');
  if (!year || !month || !day) return value;
  return `${day.padStart(2, '0')}/${month.padStart(2, '0')}/${year}`;
};

const isoToMonthYear = (value?: string | null): string => {
  if (!value) return '';
  if (value.includes('/')) return value;
  const [year, month] = value.split('-');
  if (!year || !month) return value;
  return `${month.padStart(2, '0')}/${year}`;
};

const brToIsoDate = (value: string): string => {
  const digits = value.replace(/\D/g, '');
  if (digits.length !== 8) return '';
  const day = digits.slice(0, 2);
  const month = digits.slice(2, 4);
  const year = digits.slice(4, 8);
  return `${year}-${month}-${day}`;
};

const monthYearToIso = (value: string): string => {
  const digits = value.replace(/\D/g, '');
  if (digits.length !== 6) return '';
  const month = digits.slice(0, 2);
  const year = digits.slice(2, 6);
  return `${year}-${month}-01`;
};

const showCaucaoValor = computed(() => form.garantia_tipo === 'Caucao');
const showReajusteCampos = computed(() => form.reajuste_indice !== 'SEM_REAJUSTE');
const arquivosParaRemover = computed(() => existingAttachments.value.filter((a) => a.marked).map((a) => a.id));

const clearErrors = () => {
  Object.keys(errors).forEach((k) => delete errors[k]);
};

const resetForm = () => {
  form.codigo_contrato = '';
  form.imovel_id = '';
  form.locador_id = '';
  form.locatario_id = '';
  form.data_inicio = '';
  form.data_fim = '';
  form.dia_vencimento = '';
  form.prazo_meses = '';
  form.carencia_meses = '';
  form.data_entrega_chaves = '';
  form.valor_aluguel = '';
  form.desconto_mensal = '';
  form.reajuste_indice = 'IGPM';
  form.reajuste_periodicidade_meses = '12';
  form.data_proximo_reajuste = '';
  form.garantia_tipo = 'SemGarantia';
  form.caucao_valor = '';
  form.taxa_adm_percentual = '';
  form.multa_atraso_percentual = '';
  form.juros_mora_percentual_mes = '';
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

const loadContrato = async () => {
  if (props.mode !== 'edit' || !props.contratoId) {
    loading.value = false;
    return;
  }

  try {
    const { data } = await axios.get(`/api/contratos/${props.contratoId}`);
    const payload = data.data;

    form.codigo_contrato = payload.codigo_contrato ?? '';
    form.imovel_id = payload.imovel_id ?? '';
    form.locador_id = payload.locador_id ?? '';
    form.locatario_id = payload.locatario_id ?? '';
    form.data_inicio = isoToBrDate(payload.data_inicio);
    form.data_fim = isoToBrDate(payload.data_fim);
    form.dia_vencimento = payload.dia_vencimento ?? '';
    form.prazo_meses = payload.prazo_meses ?? '';
    form.carencia_meses = payload.carencia_meses ?? '';
    form.data_entrega_chaves = isoToBrDate(payload.data_entrega_chaves);
    form.valor_aluguel = formatDecimal(payload.valor_aluguel);
    form.desconto_mensal = formatDecimal(payload.desconto_mensal);
    form.reajuste_indice = payload.reajuste_indice ?? 'IGPM';
    form.reajuste_periodicidade_meses = payload.reajuste_periodicidade_meses ?? '';
    form.data_proximo_reajuste = isoToMonthYear(payload.data_proximo_reajuste);
    form.garantia_tipo = payload.garantia_tipo ?? 'SemGarantia';
    form.caucao_valor = formatDecimal(payload.caucao_valor);
    form.taxa_adm_percentual = formatDecimal(payload.taxa_adm_percentual);
    form.multa_atraso_percentual = formatDecimal(payload.multa_atraso_percentual);
    form.juros_mora_percentual_mes = formatDecimal(payload.juros_mora_percentual_mes);
    form.repasse_automatico = Boolean(payload.repasse_automatico);
    form.conta_cobranca_id = payload.conta_cobranca_id ?? '';
    form.forma_pagamento_preferida = payload.forma_pagamento_preferida ?? '';
    form.tipo_contrato = payload.tipo_contrato ?? '';
    form.status = payload.status ?? 'Ativo';
    form.observacoes = payload.observacoes ?? '';

    fiadores.value = (payload.fiadores ?? []).map((f: { id: number }) => f.id);
    existingAttachments.value = (payload.anexos ?? []).map((a: ExistingAttachment) => ({ ...a, marked: false }));
  } catch (error) {
    console.error(error);
    toast.error('Não foi possível carregar o contrato.');
  } finally {
    loading.value = false;
  }
};

const addFiador = () => fiadores.value.push(NaN);
const removeFiador = (index: number) => fiadores.value.splice(index, 1);
const handleFiadorChange = (index: number, value: string) => {
  const parsed = value === '' ? NaN : Number(value);
  fiadores.value[index] = Number.isNaN(parsed) ? NaN : parsed;
};

const handleFilesSelected = (event: Event) => {
  const target = event.target as HTMLInputElement;
  if (!target.files) return;
  newAttachments.value.push(...Array.from(target.files));
  target.value = '';
};

const removeNewAttachment = (index: number) => newAttachments.value.splice(index, 1);
const toggleExistingAttachment = (a: ExistingAttachment) => { a.marked = !a.marked; };

const normalizedFiadores = computed(() => fiadores.value.filter((id) => Number.isFinite(id)) as number[]);

const submit = async () => {
  if (saving.value) return;
  saving.value = true;
  clearErrors();
  formError.value = '';

  const formData = new FormData();
  formData.append('codigo_contrato', String(form.codigo_contrato));
  formData.append('imovel_id', String(form.imovel_id ?? ''));
  formData.append('locador_id', String(form.locador_id ?? ''));
  formData.append('locatario_id', String(form.locatario_id ?? ''));
  formData.append('data_inicio', brToIsoDate(form.data_inicio) || '');
  formData.append('data_fim', brToIsoDate(form.data_fim) || '');
  formData.append('dia_vencimento', String(form.dia_vencimento ?? ''));
  formData.append('prazo_meses', form.prazo_meses ?? '');
  formData.append('carencia_meses', form.carencia_meses ?? '');
  formData.append('data_entrega_chaves', brToIsoDate(form.data_entrega_chaves) || '');
  formData.append('valor_aluguel', form.valor_aluguel ?? '');
  formData.append('desconto_mensal', form.desconto_mensal ?? '');
  formData.append('reajuste_indice', form.reajuste_indice ?? '');
  formData.append('reajuste_periodicidade_meses', form.reajuste_periodicidade_meses ?? '');
  formData.append('data_proximo_reajuste', monthYearToIso(form.data_proximo_reajuste) || '');
  formData.append('garantia_tipo', form.garantia_tipo ?? '');
  formData.append('caucao_valor', form.caucao_valor ?? '');
  formData.append('taxa_adm_percentual', form.taxa_adm_percentual ?? '');
  formData.append('multa_atraso_percentual', form.multa_atraso_percentual ?? '');
  formData.append('juros_mora_percentual_mes', form.juros_mora_percentual_mes ?? '');
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
    if (props.mode === 'create') resetForm(); else await loadContrato();
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

watch(() => form.garantia_tipo, (v) => { if (v !== 'Caucao') form.caucao_valor = ''; });
watch(() => form.reajuste_indice, (v) => {
  if (v === 'SEM_REAJUSTE') {
    form.reajuste_periodicidade_meses = '';
    form.data_proximo_reajuste = '';
  } else if (!form.reajuste_periodicidade_meses) {
    form.reajuste_periodicidade_meses = '12';
  }
});

onMounted(() => { if (props.mode === 'edit') loadContrato(); else loading.value = false; });
watch(() => props.contratoId, () => { if (props.mode === 'edit') loadContrato(); });
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
        <div class="grid gap-6 lg:grid-cols-3">
          <div class="lg:col-span-1 flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-200">Código *</label>
          <input
            v-model="form.codigo_contrato"
            type="text"
            required
            maxlength="30"
            class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            placeholder="CTR-0001"
          />
          <p v-if="errors.codigo_contrato" class="text-xs text-rose-400">{{ errors.codigo_contrato }}</p>
        </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-200">Imóvel ID *</label>
          <input
            v-model="form.imovel_id"
            type="number"
            min="1"
            required
            class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
          />
          <p v-if="errors.imovel_id" class="text-xs text-rose-400">{{ errors.imovel_id }}</p>
        </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-200">Locador ID *</label>
          <input
            v-model="form.locador_id"
            type="number"
            min="1"
            required
            class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
          />
          <p v-if="errors.locador_id" class="text-xs text-rose-400">{{ errors.locador_id }}</p>
        </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-200">Locatário ID *</label>
          <input
            v-model="form.locatario_id"
            type="number"
            min="1"
            required
            class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
          />
          <p v-if="errors.locatario_id" class="text-xs text-rose-400">{{ errors.locatario_id }}</p>
        </div>
        </div>
      </section>

      <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-5 shadow-inner shadow-black/20">
        <header class="flex flex-col gap-1">
          <div class="flex items-center gap-3">
            <span class="h-6 w-1 rounded-full bg-emerald-500"></span>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Fiadores</h3>
          </div>
          <p class="text-xs text-slate-400">Informe os IDs das pessoas que atuarão como fiadores.</p>
        </header>
        <div class="space-y-3">
          <div v-for="(fiadorId, index) in fiadores" :key="index" class="flex items-center gap-3">
            <input
              :value="Number.isNaN(fiadorId) ? '' : fiadorId"
              type="number"
              min="1"
              class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              placeholder="ID do fiador"
              @input="(e) => handleFiadorChange(index, (e.target as HTMLInputElement).value)"
            />
            <button type="button" class="rounded-md border border-rose-600 px-2 py-1 text-xs text-rose-300 transition hover:bg-rose-500/20" @click="removeFiador(index)">
              Remover
            </button>
          </div>
          <button type="button" class="inline-flex items-center gap-2 rounded-md border border-slate-700 px-3 py-2 text-sm text-slate-200 transition hover:bg-slate-800" @click="addFiador">
            + Adicionar fiador
          </button>
          <p v-if="errors['fiadores.0']" class="text-xs text-rose-400">{{ errors['fiadores.0'] }}</p>
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
          <input
            :value="form.data_inicio"
            type="text"
            inputmode="numeric"
            placeholder="dd/mm/aaaa"
            required
            class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            @input="form.data_inicio = maskDate(($event.target as HTMLInputElement).value)"
          />
          <p v-if="errors.data_inicio" class="text-xs text-rose-400">{{ errors.data_inicio }}</p>
        </div>
          <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Data fim</label>
          <input
            :value="form.data_fim"
            type="text"
            inputmode="numeric"
            placeholder="dd/mm/aaaa"
            class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            @input="form.data_fim = maskDate(($event.target as HTMLInputElement).value)"
          />
          <p v-if="errors.data_fim" class="text-xs text-rose-400">{{ errors.data_fim }}</p>
        </div>
          <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Dia de vencimento *</label>
          <input v-model="form.dia_vencimento" type="number" min="1" max="28" required class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
          <p v-if="errors.dia_vencimento" class="text-xs text-rose-400">{{ errors.dia_vencimento }}</p>
        </div>
          <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Prazo (meses)</label>
          <input v-model="form.prazo_meses" type="number" min="0" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
          <p v-if="errors.prazo_meses" class="text-xs text-rose-400">{{ errors.prazo_meses }}</p>
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Carência (meses)</label>
          <input v-model="form.carencia_meses" type="number" min="0" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
          <p v-if="errors.carencia_meses" class="text-xs text-rose-400">{{ errors.carencia_meses }}</p>
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Entrega das chaves</label>
          <input
            :value="form.data_entrega_chaves"
            type="text"
            inputmode="numeric"
            placeholder="dd/mm/aaaa"
            class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            @input="form.data_entrega_chaves = maskDate(($event.target as HTMLInputElement).value)"
          />
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
          <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Valor do aluguel *</label>
          <input v-model="form.valor_aluguel" type="text" inputmode="decimal" required class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
          <p v-if="errors.valor_aluguel" class="text-xs text-rose-400">{{ errors.valor_aluguel }}</p>
        </div>
          <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Desconto mensal</label>
          <input v-model="form.desconto_mensal" type="text" inputmode="decimal" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
          <p v-if="errors.desconto_mensal" class="text-xs text-rose-400">{{ errors.desconto_mensal }}</p>
        </div>
          <div class="grid gap-6 sm:grid-cols-2 lg:col-span-3">
            <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-200">Taxa administrativa (%)</label>
            <input v-model="form.taxa_adm_percentual" type="text" inputmode="decimal" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            <p v-if="errors.taxa_adm_percentual" class="text-xs text-rose-400">{{ errors.taxa_adm_percentual }}</p>
          </div>
            <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-200">Multa por atraso (%)</label>
            <input v-model="form.multa_atraso_percentual" type="text" inputmode="decimal" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            <p v-if="errors.multa_atraso_percentual" class="text-xs text-rose-400">{{ errors.multa_atraso_percentual }}</p>
          </div>
            <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-200">Juros mensal (%)</label>
            <input v-model="form.juros_mora_percentual_mes" type="text" inputmode="decimal" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            <p v-if="errors.juros_mora_percentual_mes" class="text-xs text-rose-400">{{ errors.juros_mora_percentual_mes }}</p>
          </div>
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
        <div v-if="showReajusteCampos" class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Periodicidade (meses)</label>
          <input v-model="form.reajuste_periodicidade_meses" type="number" min="1" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
          <p v-if="errors.reajuste_periodicidade_meses" class="text-xs text-rose-400">{{ errors.reajuste_periodicidade_meses }}</p>
        </div>
        <div v-if="showReajusteCampos" class="flex flex-col gap-1">
          <label class="text-sm font-medium text-slate-200">Próximo reajuste</label>
          <input
            :value="form.data_proximo_reajuste"
            type="text"
            inputmode="numeric"
            placeholder="mm/aaaa"
            class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            @input="form.data_proximo_reajuste = maskMonthYear(($event.target as HTMLInputElement).value)"
          />
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
