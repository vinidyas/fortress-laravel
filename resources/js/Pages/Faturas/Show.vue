<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import SendInvoiceEmailModal from '@/Components/Faturas/SendInvoiceEmailModal.vue';
import LiquidateInvoiceModal from '@/Components/Faturas/LiquidateInvoiceModal.vue';

type Nullable<T> = T | null;

type FaturaItem = {
  id: number;
  categoria: string;
  descricao: Nullable<string>;
  quantidade: string;
  valor_unitario: string;
  valor_total: string;
};

type FaturaAttachment = {
  id: number;
  original_name: string;
  display_name: string;
  mime_type: Nullable<string>;
  size: Nullable<number>;
  url: string;
  uploaded_at: Nullable<string>;
};

type AttachmentItem = FaturaAttachment & {
  includeInEmail: boolean;
};

type FaturaEmailLog = {
  id: number;
  subject: string;
  recipients: string[];
  cc: string[];
  bcc: string[];
  attachments?: {
    id: number | null;
    original_name?: Nullable<string>;
    display_name?: Nullable<string>;
    mime_type?: Nullable<string>;
    size?: Nullable<number>;
  }[];
  message: Nullable<string>;
  status: string;
  error_message: Nullable<string>;
  created_at: string;
  user?: {
    id: number;
    nome?: string;
    name?: string;
    email: string;
  } | null;
};

type FaturaEmailMetadata = {
  defaults: {
    to: string[];
    cc: string[];
  };
  history?: FaturaEmailLog[];
  last_sent_at?: Nullable<string>;
  last_status?: Nullable<string>;
};

type FaturaData = {
  id: number;
  contrato_id: number;
  competencia: string;
  vencimento: string;
  status: string;
  valor_total: string;
  valor_pago: Nullable<string>;
  pago_em: Nullable<string>;
  metodo_pagamento: Nullable<string>;
  nosso_numero: Nullable<string>;
  boleto_url: Nullable<string>;
  pix_qrcode: Nullable<string>;
  observacoes: Nullable<string>;
  contrato: Nullable<{
    codigo_contrato?: string;
    imovel?: {
      codigo?: string;
      cidade?: Nullable<string>;
    } | null;
    forma_pagamento_preferida?: Nullable<string>;
    forma_pagamento_preferida_label?: Nullable<string>;
  }>;
  itens: FaturaItem[];
  anexos?: FaturaAttachment[];
  email?: FaturaEmailMetadata | null;
};

const props = defineProps<{ faturaId: Nullable<number> }>();
const page = usePage();

const isNew = computed(() => props.faturaId === null);
const loading = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const fatura = ref<FaturaData | null>(null);

const attachments = ref<AttachmentItem[]>([]);
const selectedAttachmentIds = ref<number[]>([]);
const attachmentsUploading = ref(false);
const attachmentsError = ref('');
const attachmentsSuccess = ref('');
const removingAttachmentId = ref<number | null>(null);
const attachmentUploadInput = ref<HTMLInputElement | null>(null);
const renamingAttachmentId = ref<number | null>(null);
const resettingAttachmentId = ref<number | null>(null);

const baixaForm = reactive({
  valor_pago: '',
  pago_em: '',
  metodo_pagamento: 'PIX',
  observacoes: '',
});

const metodoOptions = ['PIX', 'Boleto', 'Transferencia', 'Dinheiro', 'Cartao', 'Outro'];
const itemCategories = ['Aluguel', 'Condominio', 'IPTU', 'Multa', 'Juros', 'Desconto', 'Outros', 'Agua', 'Luz', 'Gas'];
const formaPagamentoLabels: Record<string, string> = {
  Boleto: 'Boleto',
  Pix: 'Pix',
  Deposito: 'Depósito',
  Transferencia: 'Transferência',
  CartaoCredito: 'Cartão de crédito',
  Dinheiro: 'Dinheiro',
};
const formaPagamentoOptions = [
  { value: '', label: 'Selecione' },
  { value: 'Boleto', label: 'Boleto' },
  { value: 'Pix', label: 'Pix' },
  { value: 'Deposito', label: 'Depósito' },
  { value: 'Transferencia', label: 'Transferência' },
  { value: 'CartaoCredito', label: 'Cartão de crédito' },
  { value: 'Dinheiro', label: 'Dinheiro' },
];

type EditableItem = {
  id: number | null;
  categoria: string | null;
  descricao: string;
  quantidade: string;
  valor_unitario: string;
};

const editableItens = ref<EditableItem[]>([]);
const isSaving = ref(false);
const contratoFormaPagamentoValue = ref('');
const updatingContratoFormaPagamento = ref(false);
const showSendEmailModal = ref(false);
const showLiquidateModal = ref(false);
const liquidating = ref(false);
const liquidateError = ref('');
const sendingEmail = ref(false);
const sendEmailError = ref('');

const headerTitle = computed(() => {
  if (isNew.value) {
    return 'Nova fatura';
  }
  if (fatura.value?.id) {
    return `Fatura #${fatura.value.id}`;
  }
  if (props.faturaId) {
    return `Fatura #${props.faturaId}`;
  }
  return 'Fatura';
});

const headerSubtitle = computed(() => {
  if (isNew.value) {
    return 'Cadastre uma nova fatura para um contrato existente.';
  }

  const parts: string[] = ['Visão geral da Fortress Gestão Imobiliária'];

  if (fatura.value?.competencia) {
    const competence = new Date(fatura.value.competencia).toLocaleDateString('pt-BR', {
      month: '2-digit',
      year: 'numeric',
    });
    parts.push(`Competência ${competence}`);
  }

  if (fatura.value?.vencimento) {
    const due = new Date(fatura.value.vencimento).toLocaleDateString('pt-BR');
    parts.push(`Venc. ${due}`);
  }

  return parts.join(' • ');
});

const contratoFormaPagamento = computed(() => {
  const key = contratoFormaPagamentoValue.value;
  if (!key) {
    return '—';
  }

  return formaPagamentoLabels[key] ?? key;
});

const emailDefaults = computed(() => fatura.value?.email?.defaults ?? { to: [], cc: [] });
const emailHistory = computed(() => fatura.value?.email?.history ?? []);
const lastEmailSentAt = computed(() => fatura.value?.email?.last_sent_at ?? null);
const lastEmailStatus = computed(() => fatura.value?.email?.last_status ?? null);
const lastEmailSentLabel = computed(() => {
  const value = lastEmailSentAt.value;
  if (!value) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return null;
  }
  return date.toLocaleString('pt-BR');
});
const lastEmailStatusLabel = computed(() => {
  const status = lastEmailStatus.value;
  if (!status) return null;
  if (status === 'sent') return 'Enviado';
  if (status === 'failed') return 'Falhou';
  return status;
});

const isEditMode = computed(() => {
  const url = page.url ?? '';
  const query = url.includes('?') ? url.split('?')[1] : '';
  const params = new URLSearchParams(query);

  return params.get('mode') === 'edit' && !isNew.value;
});

async function fetchFatura() {
  if (isNew.value || !props.faturaId) {
    return;
  }

  loading.value = true;
  errorMessage.value = '';

  try {
    const { data } = await axios.get(`/api/faturas/${props.faturaId}`);
    fatura.value = data.data;
    hydrateEditableItens();
    hydrateAttachments();
    contratoFormaPagamentoValue.value = data.data?.contrato?.forma_pagamento_preferida ?? '';
  } catch (error) {
    console.error(error);
    errorMessage.value = 'Não foi possível carregar a fatura.';
  } finally {
    loading.value = false;
  }
}

async function settle() {
  if (!props.faturaId) return;

  successMessage.value = '';
  errorMessage.value = '';
  liquidateError.value = '';
  liquidating.value = true;

  try {
    const payload = {
      valor_pago: baixaForm.valor_pago,
      pago_em: baixaForm.pago_em,
      metodo_pagamento: baixaForm.metodo_pagamento,
      observacoes: baixaForm.observacoes,
    };

    const { data } = await axios.post(`/api/faturas/${props.faturaId}/settle`, payload);
    fatura.value = data.data;
    hydrateAttachments();
    successMessage.value = 'Fatura quitada com sucesso.';
    baixaForm.valor_pago = '';
    baixaForm.pago_em = '';
    baixaForm.metodo_pagamento = 'PIX';
    baixaForm.observacoes = '';
    showLiquidateModal.value = false;
  } catch (error: any) {
    console.error(error);
    if (error?.response?.status === 422) {
      const messages = error.response.data?.errors ?? {};
      const msg = Object.values(messages).flat().join(' ');
      liquidateError.value = msg;
      errorMessage.value = msg;
    } else {
      liquidateError.value = 'Não foi possível quitar a fatura.';
      errorMessage.value = 'Não foi possível quitar a fatura.';
    }
  } finally {
    liquidating.value = false;
  }
}

function openSendEmailModal() {
  sendEmailError.value = '';
  showSendEmailModal.value = true;
}

function closeSendEmailModal() {
  if (sendingEmail.value) return;
  sendEmailError.value = '';
  showSendEmailModal.value = false;
}

type SendEmailPayload = {
  recipients: string;
  cc: string;
  bcc: string;
  message: string;
  attachments: number[];
};

async function handleSendInvoiceEmail(payload: SendEmailPayload) {
  if (!fatura.value?.id || sendingEmail.value) {
    return;
  }

  sendingEmail.value = true;
  sendEmailError.value = '';

  try {
    const requestPayload = {
      recipients: payload.recipients,
      cc: payload.cc,
      bcc: payload.bcc,
      message: payload.message,
      attachments: payload.attachments ?? [],
    };

    const { data } = await axios.post(`/api/faturas/${fatura.value.id}/email`, requestPayload);
    fatura.value = data.data;
    successMessage.value = 'E-mail enviado com sucesso.';
    showSendEmailModal.value = false;
    hydrateAttachments();
  } catch (error: any) {
    console.error(error);
    if (axios.isAxiosError(error) && error.response?.status === 422) {
      const messages = error.response.data?.errors ?? {};
      sendEmailError.value = Object.values(messages).flat().join(' ');
    } else if (axios.isAxiosError(error) && error.response?.data?.message) {
      sendEmailError.value = error.response.data.message;
    } else {
      sendEmailError.value = 'Não foi possível enviar a fatura por e-mail.';
    }
  } finally {
    sendingEmail.value = false;
  }
}

function clearAttachmentFeedback(): void {
  attachmentsError.value = '';
  attachmentsSuccess.value = '';
}

function triggerAttachmentUpload(): void {
  clearAttachmentFeedback();
  attachmentUploadInput.value?.click();
}

async function handleAttachmentFileChange(event: Event) {
  const input = event.target as HTMLInputElement;

  if (!input.files || input.files.length === 0 || !fatura.value?.id) {
    if (attachmentUploadInput.value) {
      attachmentUploadInput.value.value = '';
    }
    return;
  }

  clearAttachmentFeedback();
  attachmentsUploading.value = true;

  const formData = new FormData();
  Array.from(input.files).forEach((file) => {
    formData.append('attachments[]', file, file.name);
  });

  try {
    const { data } = await axios.post(`/api/faturas/${fatura.value.id}/attachments`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    const items = Array.isArray(data?.data) ? (data.data as FaturaAttachment[]) : [];

    if (items.length > 0) {
      const mapped = items.map((item) => toAttachmentItem(item, true));
      attachments.value = [...mapped, ...attachments.value];
      selectedAttachmentIds.value = Array.from(
        new Set([...selectedAttachmentIds.value, ...mapped.map((item) => item.id)]),
      );

      if (fatura.value) {
        const current = Array.isArray(fatura.value.anexos) ? fatura.value.anexos : [];
        const sanitized = mapped.map(({ includeInEmail, ...rest }) => rest);
        fatura.value.anexos = [...sanitized, ...current];
      }
    }

    attachmentsSuccess.value =
      data?.meta?.message ?? (items.length > 1 ? 'Arquivos anexados com sucesso.' : 'Arquivo anexado com sucesso.');
  } catch (error: any) {
    console.error(error);
    if (axios.isAxiosError(error) && error.response?.status === 422) {
      const validation = error.response.data?.errors ?? {};
      attachmentsError.value = Object.values(validation).flat().join(' ') || 'Não foi possível anexar o arquivo.';
    } else {
      attachmentsError.value = error?.response?.data?.message ?? 'Não foi possível anexar o arquivo.';
    }
  } finally {
    attachmentsUploading.value = false;
    if (attachmentUploadInput.value) {
      attachmentUploadInput.value.value = '';
    }
  }
}

function toggleAttachmentEmailSelection(attachmentId: number, checked: boolean): void {
  const target = attachments.value.find((attachment) => attachment.id === attachmentId);
  if (!target) {
    return;
  }

  target.includeInEmail = checked;

  if (checked) {
    if (!selectedAttachmentIds.value.includes(attachmentId)) {
      selectedAttachmentIds.value = [...selectedAttachmentIds.value, attachmentId];
    }
  } else {
    selectedAttachmentIds.value = selectedAttachmentIds.value.filter((id) => id !== attachmentId);
  }
}

function setAllAttachmentsForEmail(checked: boolean): void {
  attachments.value = attachments.value.map((attachment) => ({
    ...attachment,
    includeInEmail: checked,
  }));

  selectedAttachmentIds.value = checked ? attachments.value.map((attachment) => attachment.id) : [];
}

function onAttachmentCheckboxChange(attachmentId: number, event: Event): void {
  const target = event.target as HTMLInputElement;
  toggleAttachmentEmailSelection(attachmentId, target.checked);
}

async function removeAttachment(attachmentId: number): Promise<void> {
  if (!fatura.value?.id) {
    return;
  }

  clearAttachmentFeedback();
  removingAttachmentId.value = attachmentId;

  try {
    await axios.delete(`/api/faturas/${fatura.value.id}/attachments/${attachmentId}`);

    attachments.value = attachments.value.filter((attachment) => attachment.id !== attachmentId);
    selectedAttachmentIds.value = selectedAttachmentIds.value.filter((id) => id !== attachmentId);

    if (fatura.value) {
      fatura.value.anexos = (fatura.value.anexos ?? []).filter((attachment) => attachment.id !== attachmentId);
    }

    attachmentsSuccess.value = 'Anexo removido com sucesso.';
  } catch (error: any) {
    console.error(error);
    attachmentsError.value = error?.response?.data?.message ?? 'Não foi possível remover o anexo.';
  } finally {
    removingAttachmentId.value = null;
  }
}

async function applyStandardAttachmentName(attachmentId: number): Promise<void> {
  if (!fatura.value?.id || renamingAttachmentId.value === attachmentId) {
    return;
  }

  clearAttachmentFeedback();
  renamingAttachmentId.value = attachmentId;

  try {
    const { data } = await axios.patch(`/api/faturas/${fatura.value.id}/attachments/${attachmentId}/rename`);
    const updated = data?.data as FaturaAttachment;

    const include = selectedAttachmentIds.value.includes(updated.id);
    const mapped = toAttachmentItem(updated, include);

    attachments.value = attachments.value.map((attachment) =>
      attachment.id === mapped.id ? { ...mapped } : attachment,
    );

    if (fatura.value) {
      const current = Array.isArray(fatura.value.anexos) ? fatura.value.anexos : [];
      fatura.value.anexos = current.map((attachment) =>
        attachment.id === mapped.id ? updated : attachment,
      );
    }

    attachmentsSuccess.value = data?.meta?.message ?? 'Nome padrão aplicado com sucesso.';
  } catch (error: any) {
    console.error(error);
    if (axios.isAxiosError(error) && error.response?.status === 422) {
      const validation = error.response.data?.errors ?? {};
      attachmentsError.value = Object.values(validation).flat().join(' ');
    } else {
      attachmentsError.value = error?.response?.data?.message ?? 'Não foi possível aplicar o nome padrão.';
    }
  } finally {
    renamingAttachmentId.value = null;
  }
}

async function resetAttachmentName(attachmentId: number): Promise<void> {
  if (!fatura.value?.id || resettingAttachmentId.value === attachmentId) {
    return;
  }

  clearAttachmentFeedback();
  resettingAttachmentId.value = attachmentId;

  try {
    const { data } = await axios.patch(
      `/api/faturas/${fatura.value.id}/attachments/${attachmentId}/reset-name`,
    );

    const updated = data?.data as FaturaAttachment;
    const include = selectedAttachmentIds.value.includes(updated.id);
    const mapped = toAttachmentItem(updated, include);

    attachments.value = attachments.value.map((attachment) =>
      attachment.id === mapped.id ? { ...mapped } : attachment,
    );

    if (fatura.value) {
      const current = Array.isArray(fatura.value.anexos) ? fatura.value.anexos : [];
      fatura.value.anexos = current.map((attachment) =>
        attachment.id === mapped.id ? updated : attachment,
      );
    }

    attachmentsSuccess.value = data?.meta?.message ?? 'Nome original restaurado.';
  } catch (error: any) {
    console.error(error);
    if (axios.isAxiosError(error) && error.response?.status === 422) {
      const validation = error.response.data?.errors ?? {};
      attachmentsError.value = Object.values(validation).flat().join(' ');
    } else {
      attachmentsError.value = error?.response?.data?.message ?? 'Não foi possível restaurar o nome.';
    }
  } finally {
    resettingAttachmentId.value = null;
  }
}

function openLiquidateModal() {
  liquidateError.value = '';
  if (!baixaForm.valor_pago) {
    fillBaixaComTotal();
  }
  showLiquidateModal.value = true;
}

function closeLiquidateModal() {
  if (liquidating.value) return;
  showLiquidateModal.value = false;
}

watch(
  () => fatura.value?.contrato?.forma_pagamento_preferida ?? '',
  (value) => {
    contratoFormaPagamentoValue.value = value ?? '';
  },
);

async function handleContratoFormaPagamentoChange(): Promise<void> {
  if (!fatura.value?.id) {
    return;
  }

  const currentValue = fatura.value?.contrato?.forma_pagamento_preferida ?? '';
  const selectedValue = contratoFormaPagamentoValue.value ?? '';

  if (currentValue === selectedValue) {
    return;
  }

  updatingContratoFormaPagamento.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  try {
    const payload = {
      forma_pagamento_preferida: selectedValue === '' ? null : selectedValue,
    };

    const { data } = await axios.patch(`/api/faturas/${fatura.value.id}/contrato-forma-pagamento`, payload);
    fatura.value = data.data;
    successMessage.value = 'Forma de pagamento preferida atualizada.';
    hydrateAttachments();
  } catch (error: any) {
    console.error(error);
    contratoFormaPagamentoValue.value = currentValue;
    if (error?.response?.status === 422) {
      const messages = error.response.data?.errors ?? {};
      errorMessage.value = Object.values(messages).flat().join(' ');
    } else {
      errorMessage.value = error?.response?.data?.message ?? 'Não foi possível atualizar a forma de pagamento.';
    }
  } finally {
    updatingContratoFormaPagamento.value = false;
  }
}

async function cancel() {
  if (!props.faturaId) return;

  successMessage.value = '';
  errorMessage.value = '';

  try {
    const { data } = await axios.post(`/api/faturas/${props.faturaId}/cancel`);
    fatura.value = data.data;
    successMessage.value = 'Fatura cancelada.';
    hydrateAttachments();
  } catch (error: any) {
    console.error(error);
    errorMessage.value = error?.response?.data?.message ?? 'Não foi possível cancelar a fatura.';
  }
}

onMounted(() => {
  fetchFatura();
});

function hydrateEditableItens(): void {
  if (!fatura.value) {
    editableItens.value = [];

    return;
  }

  editableItens.value = (fatura.value.itens ?? []).map((item) => {
    const qty = Number.parseFloat(item.quantidade ?? '1');
    const unit = Number.parseFloat(item.valor_unitario ?? '0');

    return {
      id: item.id ?? null,
      categoria: item.categoria ?? itemCategories[0],
      descricao: item.descricao ?? '',
      quantidade: Number.isNaN(qty) ? '1' : String(Math.max(0, Math.round(qty))),
      valor_unitario: Number.isNaN(unit) ? '0.00' : unit.toFixed(2),
    };
  });
}

function toAttachmentItem(payload: FaturaAttachment, includeInEmail = false): AttachmentItem {
  return {
    id: payload.id,
    original_name: payload.original_name ?? payload.display_name,
    display_name: payload.display_name ?? payload.original_name ?? 'Arquivo',
    mime_type: payload.mime_type ?? null,
    size: payload.size ?? null,
    url: payload.url,
    uploaded_at: payload.uploaded_at ?? null,
    includeInEmail,
  };
}

function hydrateAttachments(): void {
  const anexos = fatura.value?.anexos ?? [];
  const selectedSet = new Set(selectedAttachmentIds.value);

  const normalized = anexos.map((attachment) => toAttachmentItem(attachment, selectedSet.has(attachment.id)));

  attachments.value = normalized;
  selectedAttachmentIds.value = normalized
    .filter((attachment) => attachment.includeInEmail)
    .map((attachment) => attachment.id);
}

watch(
  () => isEditMode.value,
  (editing) => {
    if (editing) {
      hydrateEditableItens();
    }
  },
);

function extractDateParts(value: Nullable<string>): [string | null, string | null, string | null] {
  if (!value) return [null, null, null];

  const dateSegment = value.includes('T') ? value.split('T')[0] : value;
  const parts = dateSegment.split('-');

  if (parts.length !== 3) {
    return [null, null, null];
  }

  return parts as [string, string, string];
}

function formatCompetencia(value: Nullable<string>): string {
  const [year, month] = extractDateParts(value);

  if (!year || !month) {
    const date = value ? new Date(value) : null;
    return date && !Number.isNaN(date.getTime())
      ? date.toLocaleDateString('pt-BR', { month: '2-digit', year: 'numeric' })
      : '-';
  }

  return `${month.padStart(2, '0')}/${year}`;
}

function formatVencimento(value: Nullable<string>): string {
  const [year, month, day] = extractDateParts(value);

  if (!year || !month || !day) {
    const date = value ? new Date(value) : null;
    return date && !Number.isNaN(date.getTime())
      ? date.toLocaleDateString('pt-BR')
      : '-';
  }

  return `${day.padStart(2, '0')}/${month.padStart(2, '0')}/${year}`;
}

function formatCurrency(value: Nullable<string | number>): string {
  if (value === null || value === undefined || value === '') {
    return 'R$ 0,00';
  }

  let numericValue = 0;

  if (typeof value === 'number') {
    numericValue = value;
  } else {
    let normalized = value.trim();
    normalized = normalized.replace(/[^0-9.,-]/g, '');
    normalized = normalized.replace(/ /g, '').replace(/\s/g, '');
    if (normalized.includes(',') && normalized.includes('.')) {
      normalized = normalized.replace(/\./g, '').replace(',', '.');
    } else if (normalized.includes(',')) {
      normalized = normalized.replace(',', '.');
    }
    numericValue = Number.parseFloat(normalized);
    if (Number.isNaN(numericValue)) {
      numericValue = Number.parseFloat(normalized.replace(',', '.'));
    }
  }

  if (Number.isNaN(numericValue)) {
    return 'R$ 0,00';
  }

  return numericValue.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatFileSize(value: Nullable<number>): string {
  if (!value || value <= 0) {
    return '';
  }

  const units = ['bytes', 'KB', 'MB', 'GB'];
  let size = value;
  let unitIndex = 0;

  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024;
    unitIndex += 1;
  }

  if (unitIndex === 0) {
    return `${size} ${units[unitIndex]}`;
  }

  return `${size.toFixed(1)} ${units[unitIndex]}`;
}

function formatDateTime(value: Nullable<string>): string {
  if (!value) {
    return '';
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleString('pt-BR');
}

function formatQuantity(value: Nullable<string | number>): string {
  if (value === null || value === undefined || value === '') {
    return '0';
  }

  let numericValue = 0;

  if (typeof value === 'number') {
    numericValue = value;
  } else {
    const normalized = String(value).replace(',', '.');
    numericValue = Number.parseFloat(normalized);
    if (Number.isNaN(numericValue)) {
      numericValue = Number.parseInt(String(value).replace(/\D/g, '') || '0', 10);
    }
  }

  if (Number.isNaN(numericValue)) {
    return '0';
  }

  return Math.trunc(numericValue).toLocaleString('pt-BR');
}

function statusBadgeClasses(status: Nullable<string>): string {
  switch (status) {
    case 'Aberta':
      return 'bg-amber-500/15 text-amber-300 border border-amber-500/40';
    case 'Paga':
      return 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/40';
    case 'Cancelada':
      return 'bg-rose-500/15 text-rose-300 border border-rose-500/40';
    default:
      return 'bg-slate-500/20 text-slate-300 border border-slate-600/40';
  }
}

const editableTotal = computed(() =>
  editableItens.value.reduce((total, item) => {
    const qty = Number.parseInt(item.quantidade ?? '0', 10);
    const unit = Number.parseFloat(item.valor_unitario ?? '0');

    if (Number.isNaN(qty) || Number.isNaN(unit)) {
      return total;
    }

    return total + qty * unit;
  }, 0),
);

const displayTotal = computed(() =>
  isEditMode.value ? formatCurrency(editableTotal.value) : formatCurrency(fatura.value?.valor_total ?? null),
);

function fillBaixaComTotal(): void {
  const total = isEditMode.value
    ? editableTotal.value
    : Number.parseFloat(fatura.value?.valor_total ?? '0');

  if (Number.isNaN(total)) {
    baixaForm.valor_pago = '';
    return;
  }

  baixaForm.valor_pago = total.toFixed(2);
}

function addItem(): void {
  editableItens.value.push({
    id: null,
    categoria: itemCategories[0],
    descricao: '',
    quantidade: '1',
    valor_unitario: '0.00',
  });
}

function removeItem(index: number): void {
  editableItens.value.splice(index, 1);
}

function formatQuantityInputValue(value: Nullable<string>): string {
  if (value === null || value === undefined || value === '') {
    return '';
  }

  const numericValue = Number.parseInt(value, 10);

  if (Number.isNaN(numericValue)) {
    const fallback = Number.parseFloat(String(value).replace(',', '.'));
    if (Number.isNaN(fallback)) {
      return '';
    }
    return String(Math.trunc(fallback));
  }

  return String(numericValue);
}

function formatCurrencyForInput(value: Nullable<string | number>): string {
  if (value === null || value === undefined || value === '') {
    return '0,00';
  }

  let numeric = 0;
  if (typeof value === 'number') {
    numeric = value;
  } else {
    const normalized = String(value).replace(/[^0-9]/g, '');
    numeric = Number.parseInt(normalized || '0', 10) / 100;
  }

  if (Number.isNaN(numeric)) {
    return '0,00';
  }

  return numeric.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function handleQuantityInput(index: number, event: Event): void {
  const input = event.target as HTMLInputElement;
  const digits = input.value.replace(/\D/g, '');
  const normalized = digits === '' ? '0' : String(Number.parseInt(digits, 10));
  editableItens.value[index].quantidade = normalized;
  input.value = normalized;
}

function handleCurrencyInput(index: number, event: Event): void {
  const input = event.target as HTMLInputElement;
  const digits = input.value.replace(/\D/g, '');
  const numeric = Number.parseInt(digits || '0', 10) / 100;
  editableItens.value[index].valor_unitario = numeric.toFixed(2);
  input.value = formatCurrency(numeric);
}

function handleCurrencyInputBaixa(event: Event): void {
  const input = event.target as HTMLInputElement;
  const digits = input.value.replace(/\D/g, '');
  const numeric = Number.parseInt(digits || '0', 10) / 100;
  baixaForm.valor_pago = numeric.toFixed(2);
  input.value = formatCurrency(numeric);
}

async function saveItens(): Promise<void> {
  if (!props.faturaId) {
    return;
  }

  isSaving.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  try {
    const payload = {
      itens: editableItens.value.map((item) => ({
        categoria: item.categoria,
        descricao: item.descricao || null,
        quantidade:
          item.quantidade !== '' ? Number.parseInt(item.quantidade, 10) : null,
        valor_unitario:
          item.valor_unitario !== '' ? Number.parseFloat(item.valor_unitario) : null,
      })),
    };

    const { data } = await axios.put(`/api/faturas/${props.faturaId}`, payload);
    fatura.value = data.data;
    hydrateEditableItens();
    hydrateAttachments();
    successMessage.value = 'Itens atualizados com sucesso.';
  } catch (error: any) {
    console.error(error);
    if (error?.response?.status === 422) {
      const messages = error.response.data?.errors ?? {};
      errorMessage.value = Object.values(messages).flat().join(' ');
    } else {
      errorMessage.value = error?.response?.data?.message ?? 'Não foi possível atualizar os itens.';
    }
  } finally {
    isSaving.value = false;
  }
}
</script>

<template>
  <AuthenticatedLayout :title="isNew ? 'Nova fatura' : `Fatura #${fatura?.id ?? ''}`">
    <div class="space-y-8 text-slate-100">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
          <h2 class="text-2xl font-semibold text-white">
            {{ headerTitle }}
          </h2>
          <p class="text-sm text-slate-400">{{ headerSubtitle }}</p>
          <p v-if="!isNew && (fatura?.contrato_id || fatura?.contrato?.codigo_contrato)" class="text-xs uppercase tracking-wide text-slate-500">Contrato {{ fatura?.contrato?.codigo_contrato ?? '-' }} • ID {{ fatura?.contrato_id ?? props.faturaId }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 md:gap-4">
          <div class="flex flex-wrap items-center gap-2">
            <a
              v-if="!isNew && fatura"
              :href="`/faturas/${fatura.id}/cobranca`"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-500/40 bg-emerald-600/80 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-500/80"
            >
              <span>Gerar fatura</span>
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 3h6m0 0v6m0-6L10 14" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 10v10a1 1 0 01-1 1H5a2 2 0 01-2-2V4a1 1 0 011-1h10" />
              </svg>
            </a>
            <a
              v-if="!isNew && fatura"
              :href="`/faturas/${fatura.id}/recibo`"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center justify-center gap-2 rounded-xl border border-indigo-500/40 bg-indigo-600/80 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500/80"
            >
              <span>Gerar recibo</span>
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 3h6m0 0v6m0-6L10 14" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 10v10a1 1 0 01-1 1H5a2 2 0 01-2-2V4a1 1 0 011-1h10" />
              </svg>
            </a>
            <button
              v-if="!isNew && fatura"
              type="button"
              class="inline-flex items-center justify-center gap-2 rounded-xl border border-sky-500/40 bg-sky-600/80 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-500/80"
              @click="openSendEmailModal"
            >
              <span>Enviar por e-mail</span>
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a1 1 0 001.22 0L20 8" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </button>
          </div>
          <div class="hidden h-6 w-px bg-slate-700 md:block" />
          <div class="flex flex-wrap items-center gap-2 md:pl-2">
            <button
              v-if="!isNew && fatura?.status === 'Aberta'"
              type="button"
              class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-500/40 bg-emerald-600/80 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-500/80"
              @click="openLiquidateModal"
            >
              <span>Liquidar</span>
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5l7 7-7 7" />
              </svg>
            </button>
            <button
              v-if="!isNew && fatura && fatura.status !== 'Cancelada'"
              type="button"
              class="inline-flex items-center justify-center gap-2 rounded-xl border border-rose-500/40 bg-rose-600/80 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-500/80"
              @click="cancel"
            >
              <span>Cancelar fatura</span>
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
            <Link
              class="inline-flex items-center justify-center rounded-xl border border-slate-700 bg-slate-800/60 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-indigo-500 hover:bg-indigo-500/20 hover:text-white"
              href="/faturas"
            >
              Voltar
            </Link>
          </div>
        </div>
      </div>

      <div
        v-if="loading"
        class="rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-6 text-center text-sm text-slate-300 shadow-xl shadow-black/40"
      >
        Carregando fatura...
      </div>

      <div
        v-else-if="isNew"
        class="rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-6 text-sm text-slate-300 shadow-xl shadow-black/40"
      >
        Selecione um contrato na listagem para gerar faturas pelo comando ou pela API.
      </div>

      <div v-else class="space-y-6">
        <div
          v-if="errorMessage"
          class="rounded-xl border border-rose-500/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-200"
        >
          {{ errorMessage }}
        </div>
        <div
          v-if="successMessage"
          class="rounded-xl border border-emerald-500/40 bg-emerald-500/15 px-4 py-3 text-sm text-emerald-200"
        >
          {{ successMessage }}
        </div>

        <section class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
          <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
              <p class="text-sm uppercase tracking-wide text-slate-400">Contrato</p>
              <p class="text-lg font-semibold text-white">
                {{ fatura?.contrato?.codigo_contrato ?? '-' }}
                <span class="text-sm text-slate-400">(ID {{ fatura?.contrato_id }})</span>
              </p>
              <p class="text-sm text-slate-400">
                Imóvel:
                <span class="font-medium text-slate-200">
                  {{ fatura?.contrato?.imovel?.codigo ?? '-' }}
                </span>
                <span class="text-xs text-slate-500">
                  • {{ fatura?.contrato?.imovel?.cidade ?? '-' }}
                </span>
              </p>
            </div>
            <div class="text-right">
              <p class="text-sm uppercase tracking-wide text-slate-400">Status</p>
              <span
                :class="[
                  'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold',
                  statusBadgeClasses(fatura?.status ?? null),
                ]"
              >
                {{ fatura?.status }}
              </span>
              <div v-if="lastEmailSentLabel" class="mt-2 text-xs text-slate-400">
                Último envio:
                <span class="font-medium text-slate-200">{{ lastEmailSentLabel }}</span>
                <span v-if="lastEmailStatusLabel" class="ml-1">({{ lastEmailStatusLabel }})</span>
              </div>
              <div v-else-if="!emailDefaults.to.length" class="mt-2 text-xs text-amber-400">
                Nenhum e-mail de locatário cadastrado.
              </div>
            </div>
          </div>
          <div class="grid gap-4 text-sm text-slate-300 md:grid-cols-5">
            <div>
              <p class="text-xs uppercase tracking-wide text-slate-500">Competência</p>
              <p class="font-semibold text-white">
                {{ formatCompetencia(fatura?.competencia ?? null) }}
              </p>
            </div>
            <div>
              <p class="text-xs uppercase tracking-wide text-slate-500">Vencimento</p>
              <p class="font-semibold text-white">
                {{ formatVencimento(fatura?.vencimento ?? null) }}
              </p>
            </div>
            <div>
              <p class="text-xs uppercase tracking-wide text-slate-500">Valor total</p>
              <p class="font-semibold text-white">
                {{ displayTotal }}
              </p>
            </div>
            <div>
              <p class="text-xs uppercase tracking-wide text-slate-500">Valor pago</p>
              <p class="font-semibold text-white">
                {{ fatura?.valor_pago ? formatCurrency(fatura.valor_pago) : '—' }}
              </p>
            </div>
            <div>
              <p class="text-xs uppercase tracking-wide text-slate-500">Forma de pagamento (contrato)</p>
              <select
                v-model="contratoFormaPagamentoValue"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white transition focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="updatingContratoFormaPagamento || !fatura?.contrato?.id"
                @change="handleContratoFormaPagamentoChange"
              >
                <option v-for="option in formaPagamentoOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
              <p class="mt-1 text-xs text-slate-500">
                {{ contratoFormaPagamento === '—' ? 'Selecione uma opção para atualizar.' : `Atual: ${contratoFormaPagamento}` }}
              </p>
            </div>
          </div>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Itens</h3>

          <div
            v-if="isEditMode"
            class="mb-4 flex items-center justify-between gap-3 text-sm text-slate-300"
          >
            <span>Atualize os lançamentos desta fatura conforme o fechamento do mês.</span>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg border border-indigo-500/40 bg-indigo-600/70 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-indigo-500/80"
              @click="addItem"
            >
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
              </svg>
              Adicionar item
            </button>
          </div>

          <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
              <thead class="bg-slate-900/60">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Categoria
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Descrição
                  </th>
                  <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Qtd
                  </th>
                  <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Valor unitário
                  </th>
                  <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Valor total
                  </th>
                  <th
                    v-if="isEditMode"
                    class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"
                  >
                    Ações
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800 text-sm text-slate-300">
                <template v-if="isEditMode">
                  <tr v-if="!editableItens.length">
                    <td colspan="6" class="px-4 py-6 text-center text-slate-500">
                      Nenhum lançamento cadastrado para esta fatura.
                    </td>
                  </tr>
                  <tr v-for="(item, index) in editableItens" :key="item.id ?? `novo-${index}`">
                    <td class="px-4 py-3">
                      <select
                        v-model="item.categoria"
                        class="w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                      >
                        <option v-for="category in itemCategories" :key="category" :value="category">
                          {{ category }}
                        </option>
                      </select>
                    </td>
                    <td class="px-4 py-3">
                      <input
                        v-model="item.descricao"
                        type="text"
                        class="w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                        placeholder="Descrição do item"
                        maxlength="200"
                      />
                    </td>
                    <td class="px-4 py-3 text-right">
                      <input
                        :value="formatQuantityInputValue(item.quantidade)"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        class="w-28 rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-right text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                        @input="handleQuantityInput(index, $event)"
                      />
                    </td>
                    <td class="px-4 py-3 text-right">
                      <input
                        :value="formatCurrencyForInput(item.valor_unitario)"
                        type="text"
                        inputmode="decimal"
                        class="w-36 rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-right text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                        @input="handleCurrencyInput(index, $event)"
                      />
                    </td>
                    <td class="px-4 py-3 text-right">
                      {{
                        formatCurrency(
                          Number.parseInt(item.quantidade ?? '0', 10) *
                            Number.parseFloat(item.valor_unitario ?? '0')
                        )
                      }}
                    </td>
                    <td class="px-4 py-3 text-right">
                      <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-500/40 bg-rose-500/10 text-rose-300 transition hover:border-rose-500 hover:bg-rose-500/20 hover:text-rose-100"
                        @click="removeItem(index)"
                        title="Excluir item"
                      >
                        <span class="sr-only">Excluir item</span>
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12" />
                          <path stroke-linecap="round" stroke-linejoin="round" d="M9 7V4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5V7" />
                          <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 11l.75 8.5h3l.75-8.5" />
                        </svg>
                      </button>
                    </td>
                  </tr>
                </template>
                <template v-else>
                  <tr v-if="!fatura?.itens?.length">
                    <td colspan="5" class="px-4 py-6 text-center text-slate-500">
                      Nenhum lançamento cadastrado para esta fatura.
                    </td>
                  </tr>
                  <tr v-for="item in fatura?.itens ?? []" :key="item.id">
                    <td class="px-4 py-3">{{ item.categoria }}</td>
                    <td class="px-4 py-3">{{ item.descricao ?? '-' }}</td>
                    <td class="px-4 py-3 text-right">{{ formatQuantity(item.quantidade) }}</td>
                    <td class="px-4 py-3 text-right">{{ formatCurrency(item.valor_unitario) }}</td>
                    <td class="px-4 py-3 text-right">{{ formatCurrency(item.valor_total) }}</td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <div
            v-if="isEditMode && editableItens.length"
            class="mt-4 flex items-center justify-end gap-3 text-sm text-slate-300"
          >
            <span class="text-xs text-slate-500">
              Total calculado: <span class="font-semibold text-slate-200">{{ displayTotal }}</span>
            </span>
          </div>

          <div
            v-if="isEditMode"
            class="mt-6 flex items-center justify-end gap-3"
          >
            <button
              type="button"
              class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800/70"
              :disabled="isSaving"
              @click="hydrateEditableItens"
            >
              Desfazer alterações
            </button>
            <button
              type="button"
              class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:opacity-60"
              :disabled="isSaving"
              @click="saveItens"
            >
              {{ isSaving ? 'Salvando...' : 'Salvar itens' }}
            </button>
          </div>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
          <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
              <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Documentos anexos</h3>
              <p class="text-xs text-slate-500">
                Marque os arquivos que deseja incluir no envio por e-mail.
              </p>
              <p class="mt-1 text-xs text-slate-500">
                {{ selectedAttachmentIds.length }} de {{ attachments.length }} anexos selecionados.
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <input
                ref="attachmentUploadInput"
                type="file"
                class="hidden"
                multiple
                accept=".pdf,image/*"
                @change="handleAttachmentFileChange"
              />
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-indigo-500/40 bg-indigo-600/80 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-indigo-500/70 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="attachmentsUploading || !fatura?.id"
                @click="triggerAttachmentUpload"
              >
                <svg
                  v-if="attachmentsUploading"
                  class="h-4 w-4 animate-spin"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 4.5a6 6 0 11-9 0" />
                </svg>
                <svg
                  v-else
                  class="h-4 w-4"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>{{ attachmentsUploading ? 'Enviando...' : 'Adicionar arquivo' }}</span>
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:bg-slate-800/70 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="!attachments.length"
                @click="setAllAttachmentsForEmail(true)"
              >
                Selecionar todos
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:bg-slate-800/70 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="!attachments.length"
                @click="setAllAttachmentsForEmail(false)"
              >
                Limpar seleção
              </button>
            </div>
          </div>

          <div
            v-if="attachmentsError"
            class="mt-4 rounded-lg border border-rose-500/40 bg-rose-500/15 px-4 py-2 text-xs text-rose-200"
          >
            {{ attachmentsError }}
          </div>
          <div
            v-if="attachmentsSuccess"
            class="mt-4 rounded-lg border border-emerald-500/40 bg-emerald-500/15 px-4 py-2 text-xs text-emerald-200"
          >
            {{ attachmentsSuccess }}
          </div>

          <div v-if="attachments.length" class="mt-4 space-y-3">
            <div
              v-for="attachment in attachments"
              :key="attachment.id"
              class="flex flex-col gap-3 rounded-xl border border-slate-800 bg-slate-900/60 p-4 md:flex-row md:items-center md:justify-between"
            >
              <div class="flex flex-1 items-start gap-3">
                <input
                  type="checkbox"
                  class="mt-1 h-4 w-4 rounded border border-slate-600 bg-slate-900 text-indigo-500 focus:ring-indigo-500"
                  :checked="attachment.includeInEmail"
                  @change="onAttachmentCheckboxChange(attachment.id, $event)"
                />
                <div class="flex flex-1 items-start gap-3">
                  <div class="flex items-center gap-2 pt-0.5">
                    <button
                      type="button"
                      class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-emerald-500/50 bg-emerald-500/15 text-emerald-200 transition hover:bg-emerald-500/25 disabled:cursor-not-allowed disabled:opacity-60"
                      :disabled="renamingAttachmentId === attachment.id"
                      @click="applyStandardAttachmentName(attachment.id)"
                      title="Aplicar nome padrão"
                    >
                      <svg
                        v-if="renamingAttachmentId === attachment.id"
                        class="h-4 w-4 animate-spin"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                      >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 4.5a6 6 0 11-9 0" />
                      </svg>
                      <svg
                        v-else
                        class="h-4 w-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                      >
                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                      </svg>
                    </button>
                    <button
                      type="button"
                      class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-slate-600 bg-slate-800/80 text-slate-200 transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                      :disabled="resettingAttachmentId === attachment.id"
                      @click="resetAttachmentName(attachment.id)"
                      title="Restaurar nome original"
                    >
                      <svg
                        v-if="resettingAttachmentId === attachment.id"
                        class="h-4 w-4 animate-spin"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                      >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 4.5a6 6 0 11-9 0" />
                      </svg>
                      <svg
                        v-else
                        class="h-4 w-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                      >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15V9m0 0-3 3m3-3 3 3M6 19.5h12A1.5 1.5 0 0019.5 18V6A1.5 1.5 0 0018 4.5H6A1.5 1.5 0 004.5 6v12A1.5 1.5 0 006 19.5z" />
                      </svg>
                    </button>
                  </div>
                  <div>
                    <p class="font-semibold text-white">
                      {{ attachment.display_name }}
                    </p>
                    <p class="text-xs text-slate-400">
                      {{ attachment.mime_type ?? 'Arquivo' }}
                      <span v-if="formatFileSize(attachment.size)" class="ml-1">
                        • {{ formatFileSize(attachment.size) }}
                      </span>
                    </p>
                    <p v-if="attachment.uploaded_at" class="text-xs text-slate-500">
                      Enviado em {{ formatDateTime(attachment.uploaded_at) }}
                    </p>
                  </div>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <a
                  :href="attachment.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:border-indigo-500 hover:bg-indigo-500/20 hover:text-white"
                >
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6a3 3 0 116 0v6" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 11V5m0 12l-3-3m3 3l3-3" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 20h10" />
                  </svg>
                  Baixar
                </a>
                <button
                  type="button"
                  class="inline-flex items-center gap-2 rounded-lg border border-rose-500/40 bg-rose-500/10 px-3 py-1.5 text-xs font-semibold text-rose-200 transition hover:border-rose-500 hover:bg-rose-500/20 hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="removingAttachmentId === attachment.id"
                  @click="removeAttachment(attachment.id)"
                >
                  <svg
                    v-if="removingAttachmentId === attachment.id"
                    class="h-4 w-4 animate-spin"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 4.5a6 6 0 11-9 0" />
                  </svg>
                  <svg
                    v-else
                    class="h-4 w-4"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7V4.5A1.5 1.5 0 0110.5 3h3A1.5 1.5 0 0115 4.5V7" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 11l.75 8.5h3l.75-8.5" />
                  </svg>
                  Remover
                </button>
              </div>
            </div>
          </div>
          <div
            v-else
            class="mt-4 rounded-lg border border-slate-800 bg-slate-900/50 px-4 py-4 text-sm text-slate-400"
          >
            Nenhum anexo cadastrado para esta fatura.
          </div>
          <p class="mt-3 text-xs text-slate-500">
            Arquivos permitidos: PDF, JPG, PNG e WEBP (até 5 MB por arquivo).
          </p>
        </section>

      </div>
    </div>
    <SendInvoiceEmailModal
      :show="showSendEmailModal"
      :defaults="emailDefaults"
      :submitting="sendingEmail"
      :error="sendEmailError"
      :history="emailHistory"
      :attachments="attachments"
      :selected-attachment-ids="selectedAttachmentIds"
      @close="closeSendEmailModal"
      @send="handleSendInvoiceEmail"
      @toggle-attachment="toggleAttachmentEmailSelection"
      @toggle-all-attachments="setAllAttachmentsForEmail"
    />
    <LiquidateInvoiceModal
      :show="showLiquidateModal"
      :submitting="liquidating"
      :error="liquidateError"
      :form="baixaForm"
      :metodo-options="metodoOptions"
      :format-currency="formatCurrencyForInput"
      @close="closeLiquidateModal"
      @submit="settle"
      @use-total="fillBaixaComTotal"
      @valor-input="handleCurrencyInputBaixa"
    />
  </AuthenticatedLayout>
</template>
