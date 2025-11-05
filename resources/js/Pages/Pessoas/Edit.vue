<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { lookupCep, normalizeCep } from '@/utils/cep';

type Nullable<T> = T | null;

interface PessoaForm {
  nome_razao_social: string;
  tipo_pessoa: string;
  cpf_cnpj: string;
  email: string;
  telefone: string;
  papeis: string[];
  cep: string;
  estado: string;
  cidade: string;
  bairro: string;
  rua: string;
  numero: string;
  complemento: string;
  bank: {
    banco: string;
    agencia: string;
    conta: string;
    tipo_conta: 'corrente' | 'poupanca' | 'pagamento';
    titular: string;
    documento_titular: string;
    pix_chave: string;
  };
}

interface CnpjLookupResponse {
  cnpj: string;
  razao_social: string;
  nome_fantasia?: string | null;
  email?: string | null;
  telefone?: string | null;
  cep?: string | null;
  uf?: string | null;
  municipio?: string | null;
  bairro?: string | null;
  logradouro?: string | null;
  numero?: string | null;
  complemento?: string | null;
  provider?: string | null;
  fetched_at?: string | null;
}

const props = defineProps<{ pessoaId?: number | null }>();

const isEditing = computed(() => Boolean(props.pessoaId));
const pageTitle = computed(() =>
  isEditing.value ? 'Editar pessoa/empresa' : 'Nova pessoa/empresa'
);
const loading = ref(false);
const saving = ref(false);
const errorMessage = ref('');
const inviteMessage = ref('');
const inviteError = ref('');
const inviting = ref(false);

const normalizePapeis = (value: unknown): string[] => {
  if (!Array.isArray(value)) return [];

  return value
    .map((papel) => {
      if (typeof papel !== 'string') return null;
      return papel === 'Inquilino' ? 'Locatario' : papel;
    })
    .filter((papel): papel is string => Boolean(papel));
};

const tipoOptions = ['Fisica', 'Juridica'];
const papelOptions = [
  { value: 'Locatario', label: 'Locatário' },
  { value: 'Proprietario', label: 'Proprietário' },
  { value: 'Fiador', label: 'Fiador' },
  { value: 'Fornecedor', label: 'Fornecedor' },
  { value: 'Cliente', label: 'Cliente' },
];

const inputClass = 'w-full rounded-lg border border-slate-700 bg-slate-950/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500';
const selectClass = inputClass;
const form = reactive<PessoaForm>({
  nome_razao_social: '',
  tipo_pessoa: 'Fisica',
  cpf_cnpj: '',
  email: '',
  telefone: '',
  papeis: [],
  cep: '',
  estado: '',
  cidade: '',
  bairro: '',
  rua: '',
  numero: '',
  complemento: '',
  bank: {
    banco: '',
    agencia: '',
    conta: '',
    tipo_conta: 'corrente',
    titular: '',
    documento_titular: '',
    pix_chave: '',
  },
});
const cepLoading = ref(false);
const cnpjLoading = ref(false);
const cnpjMessage = ref('');

const requiresBoletoData = computed(() => form.papeis.includes('Locatario'));
const page = usePage();
const canInvitePortalAccess = computed(() => {
  const abilities = (page.props?.auth as any)?.abilities ?? [];
  return abilities.includes('admin.access') && isEditing.value && form.papeis.includes('Locatario');
});

async function loadPessoa() {
  if (!props.pessoaId) {
    return;
  }

  loading.value = true;
  errorMessage.value = '';

  try {
    const { data } = await axios.get(`/api/pessoas/${props.pessoaId}`);
    const payload = data.data;

    form.nome_razao_social = payload.nome_razao_social ?? '';
    form.tipo_pessoa = payload.tipo_pessoa ?? 'Fisica';
    form.cpf_cnpj = payload.cpf_cnpj ?? '';
    form.email = payload.email ?? '';
    form.telefone = payload.telefone ?? '';
    form.papeis = normalizePapeis(payload.papeis);
    form.cep = payload.enderecos?.cep ?? '';
    form.estado = payload.enderecos?.estado ?? '';
    form.cidade = payload.enderecos?.cidade ?? '';
    form.bairro = payload.enderecos?.bairro ?? '';
    form.rua = payload.enderecos?.rua ?? '';
    form.numero = payload.enderecos?.numero ?? '';
    form.complemento = payload.enderecos?.complemento ?? '';
    const bank = payload.dados_bancarios ?? {};
    form.bank.banco = bank?.banco ?? '';
    form.bank.agencia = bank?.agencia ?? '';
    form.bank.conta = bank?.conta ?? '';
    form.bank.tipo_conta = (bank?.tipo_conta ?? 'corrente') as 'corrente' | 'poupanca' | 'pagamento';
    form.bank.titular = bank?.titular ?? '';
    form.bank.documento_titular = bank?.documento_titular ?? '';
    form.bank.pix_chave = bank?.pix_chave ?? '';
    cnpjMessage.value = '';
  } catch (error) {
    console.error(error);
    errorMessage.value = 'Nao foi possivel carregar a pessoa.';
  } finally {
    loading.value = false;
  }
}

function togglePapel(papel: string) {
  if (form.papeis.includes(papel)) {
    form.papeis = form.papeis.filter((item) => item !== papel);
  } else {
    form.papeis.push(papel);
  }
}

async function submit() {
  saving.value = true;
  errorMessage.value = '';

  if (form.papeis.length === 0) {
    errorMessage.value = 'Selecione pelo menos um papel para continuar.';
    saving.value = false;
    return;
  }

  const payload = {
    nome_razao_social: form.nome_razao_social.trim(),
    tipo_pessoa: form.tipo_pessoa,
    cpf_cnpj: form.cpf_cnpj.replace(/\D/g, ''),
    email: form.email || null,
    telefone: form.telefone || null,
    papeis: form.papeis,
    cep: form.cep || null,
    estado: form.estado || null,
    cidade: form.cidade || null,
    bairro: form.bairro || null,
    rua: form.rua || null,
    numero: form.numero || null,
    complemento: form.complemento || null,
    dados_bancarios: {
      banco: form.bank.banco || null,
      agencia: form.bank.agencia || null,
      conta: form.bank.conta || null,
      tipo_conta: form.bank.tipo_conta || null,
      titular: form.bank.titular || null,
      documento_titular: form.bank.documento_titular.replace(/\D/g, '') || null,
      pix_chave: form.bank.pix_chave || null,
    },
  };

  try {
    if (isEditing.value && props.pessoaId) {
      await axios.put(`/api/pessoas/${props.pessoaId}`, payload);
    } else {
      await axios.post('/api/pessoas', payload);
    }
    router.visit('/pessoas');
  } catch (error: any) {
    console.error(error);
    if (error?.response?.status === 422) {
      const messages = error.response.data?.errors ?? {};
      errorMessage.value = Object.values(messages).flat().join(' ');
    } else {
      errorMessage.value = 'Nao foi possivel salvar a pessoa.';
    }
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  if (isEditing.value) {
    loadPessoa();
  }
});

watch(
  () => form.cpf_cnpj,
  () => {
    if (!cnpjLoading.value) {
      cnpjMessage.value = '';
    }
  }
);

async function invitePortalAccess() {
  if (!props.pessoaId) return;

  inviteMessage.value = '';
  inviteError.value = '';
  inviting.value = true;

  try {
    await axios.post('/api/admin/portal/tenant-users', {
      pessoa_id: props.pessoaId,
      email: form.email || page.props?.auth?.user?.email,
    });

    inviteMessage.value = 'Convite enviado. O locatário receberá um e-mail para acessar o portal.';
  } catch (error: any) {
    const message =
      error?.response?.data?.message ||
      error?.response?.data?.errors?.email?.[0] ||
      'Não foi possível enviar o convite.';
    inviteError.value = message;
  } finally {
    inviting.value = false;
  }
}

async function fetchCep() {
  cepLoading.value = true;
  errorMessage.value = '';
  try {
    const cep = normalizeCep(form.cep);
    if (cep.length !== 8) {
      errorMessage.value = 'Informe um CEP válido com 8 dígitos.';
      return;
    }
    const data = await lookupCep(cep);
    if (!data) {
      errorMessage.value = 'CEP não encontrado.';
      return;
    }
    form.cep = data.cep;
    form.estado = data.uf || form.estado;
    form.cidade = data.cidade || form.cidade;
    form.bairro = (data.bairro ?? '') || form.bairro;
    if (data.logradouro) {
      form.rua = form.rua || data.logradouro;
    }
    if (data.complemento) form.complemento = data.complemento;
  } finally {
    cepLoading.value = false;
  }
}

function buildCnpjMessage(payload: CnpjLookupResponse): string {
  const provider = payload.provider ?? 'BrasilAPI';
  const fetchedAt = payload.fetched_at ? new Date(payload.fetched_at) : null;

  if (fetchedAt instanceof Date && !Number.isNaN(fetchedAt.getTime())) {
    return `Dados preenchidos via ${provider} em ${fetchedAt.toLocaleString('pt-BR')}.`;
  }

  return `Dados preenchidos via ${provider}.`;
}

function applyCnpjDataToForm(payload: CnpjLookupResponse) {
  form.tipo_pessoa = 'Juridica';
  form.nome_razao_social = payload.razao_social ?? form.nome_razao_social;

  if (payload.email) {
    form.email = payload.email;
  }

  if (payload.telefone) {
    form.telefone = payload.telefone.replace(/\D/g, '');
  }

  if (payload.cep) {
    form.cep = payload.cep.replace(/\D/g, '');
  }

  if (payload.uf) {
    form.estado = payload.uf.toUpperCase();
  }

  if (payload.municipio) {
    form.cidade = payload.municipio;
  }

  if (payload.bairro) {
    form.bairro = payload.bairro;
  }

  if (payload.logradouro) {
    form.rua = payload.logradouro;
  }

  if (payload.numero) {
    form.numero = payload.numero;
  }

  if (payload.complemento) {
    form.complemento = payload.complemento;
  }
}

async function fetchCnpjData() {
  if (form.tipo_pessoa !== 'Juridica') {
    errorMessage.value = 'A busca automática só está disponível para pessoas jurídicas.';
    return;
  }

  const documento = form.cpf_cnpj.replace(/\D/g, '');

  if (documento.length !== 14) {
    errorMessage.value = 'Informe um CNPJ com 14 dígitos para buscar os dados automaticamente.';
    return;
  }

  cnpjLoading.value = true;
  errorMessage.value = '';
  cnpjMessage.value = '';

  try {
    const { data } = await axios.get(`/api/cnpj/${documento}`);
    const payload: CnpjLookupResponse = data?.data ?? {};

    form.cpf_cnpj = documento;
    applyCnpjDataToForm(payload);
    cnpjMessage.value = buildCnpjMessage(payload);
  } catch (error: any) {
    const responseMessage = error?.response?.data?.message;
    errorMessage.value = responseMessage || 'Não foi possível consultar o CNPJ no momento.';
  } finally {
    cnpjLoading.value = false;
  }
}
</script>


<template>
  <AuthenticatedLayout :title="pageTitle">
    <Head :title="pageTitle" />

    <div class="space-y-6 text-slate-100">
      <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h1 class="text-2xl font-semibold text-white">{{ pageTitle }}</h1>
            <p class="text-sm text-slate-400">
              {{
                isEditing
                  ? 'Atualize as informações da pessoa/empresa cadastrada.'
                  : 'Informe os dados para concluir o cadastro.'
              }}
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <button
              v-if="canInvitePortalAccess"
              type="button"
              class="inline-flex items-center gap-2 rounded-lg border border-indigo-400/50 bg-indigo-600/80 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="inviting"
              @click="invitePortalAccess"
            >
              <svg
                class="h-4 w-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-9-9" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v10m5-5H7" />
              </svg>
              {{ inviting ? 'Enviando...' : 'Convidar para portal' }}
            </button>
            <Link
              href="/pessoas"
              class="inline-flex items-center gap-2 rounded-lg border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800"
            >
              Voltar
            </Link>
          </div>
        </div>

        <div class="mt-4 space-y-3">
          <div
            v-if="errorMessage"
            class="rounded-md border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200"
          >
            {{ errorMessage }}
          </div>
          <div
            v-if="inviteMessage"
            class="rounded-md border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200"
          >
            {{ inviteMessage }}
          </div>
          <div
            v-if="inviteError"
            class="rounded-md border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-200"
          >
            {{ inviteError }}
          </div>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
        <div
          v-if="loading"
          class="mb-4 rounded-md border border-slate-700 bg-slate-900/60 px-4 py-3 text-sm text-slate-200"
        >
          Carregando dados da pessoa...
        </div>
        <form
          @submit.prevent="submit"
          class="grid gap-6 lg:grid-cols-2"
          :class="{ 'pointer-events-none opacity-60': loading }"
        >
          <p class="lg:col-span-2 text-xs text-slate-400">
            Campos marcados com <span class="text-rose-400">*</span> são obrigatórios.
          </p>

          <div class="space-y-5 rounded-xl border border-slate-800/70 bg-slate-900/60 p-5 shadow-inner shadow-black/30">
            <header class="flex items-center gap-3">
              <span class="h-6 w-1 rounded-full bg-indigo-500" />
              <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-200">Dados gerais</h2>
            </header>

            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-slate-200">
                Nome / Razão social
                <span class="text-rose-400">*</span>
              </label>
              <input
                v-model="form.nome_razao_social"
                type="text"
                required
                :class="inputClass"
              />
            </div>

            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-slate-200">
                Tipo
                <span class="text-rose-400">*</span>
              </label>
              <select
                v-model="form.tipo_pessoa"
                required
                :class="inputClass"
              >
                <option v-for="option in tipoOptions" :key="option" :value="option">
                  {{ option }}
                </option>
              </select>
            </div>

            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-slate-200">
                CPF / CNPJ
                <span class="text-rose-400">*</span>
              </label>
              <div class="flex gap-2">
                <input
                  v-model="form.cpf_cnpj"
                  type="text"
                  required
                  maxlength="14"
                  inputmode="numeric"
                  :class="inputClass"
                  placeholder="Somente números"
                  @input="form.cpf_cnpj = form.cpf_cnpj.replace(/\D/g, '')"
                />
                <button
                  v-if="form.tipo_pessoa === 'Juridica'"
                  type="button"
                  class="inline-flex items-center rounded-lg border border-indigo-500/40 bg-indigo-600/80 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="cnpjLoading"
                  @click="fetchCnpjData"
                >
                  {{ cnpjLoading ? 'Buscando...' : 'Buscar CNPJ' }}
                </button>
              </div>
              <p v-if="cnpjMessage" class="text-xs text-emerald-300">{{ cnpjMessage }}</p>
            </div>

            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-slate-200">Papéis</label>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="option in papelOptions"
                  :key="option.value"
                  type="button"
                  class="rounded-full px-3 py-1 text-xs font-semibold transition"
                  :class="form.papeis.includes(option.value)
                    ? 'bg-indigo-600 text-white shadow'
                    : 'border border-slate-700 bg-slate-900/40 text-slate-300 hover:bg-slate-800'"
                  @click="togglePapel(option.value)"
                >
                  {{ option.label }}
                </button>
              </div>
              <p v-if="requiresBoletoData" class="text-xs text-amber-300">
                Para locatários, os dados de contato e endereço tornam-se obrigatórios por exigência bancária.
              </p>
            </div>
          </div>

          <div class="space-y-5 rounded-xl border border-slate-800/70 bg-slate-900/60 p-5 shadow-inner shadow-black/30">
            <header class="flex items-center gap-3">
              <span class="h-6 w-1 rounded-full bg-indigo-500" />
              <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-200">Contato e endereço</h2>
            </header>

            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-slate-200">E-mail</label>
              <input
                v-model="form.email"
                type="email"
                :required="requiresBoletoData"
                :class="inputClass"
              />
            </div>

            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-slate-200">Telefone</label>
              <input
                v-model="form.telefone"
                type="text"
                :required="requiresBoletoData"
                maxlength="15"
                :class="inputClass"
                placeholder="(DDD) 99999-9999"
              />
            </div>

            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-slate-200">CEP</label>
              <div class="flex gap-2">
                <input
                  v-model="form.cep"
                  type="text"
                  :required="requiresBoletoData"
                  maxlength="8"
                  :class="inputClass"
                  placeholder="Somente números"
                />
                <button
                  type="button"
                  class="inline-flex items-center rounded-lg border border-indigo-500/40 bg-indigo-600/80 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="cepLoading"
                  @click="fetchCep"
                >
                  {{ cepLoading ? 'Buscando...' : 'Buscar' }}
                </button>
              </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
              <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-slate-200">Estado</label>
                <input
                  v-model="form.estado"
                  type="text"
                  maxlength="2"
                  :class="[inputClass, 'uppercase']"
                />
              </div>
              <div class="flex flex-col gap-2 md:col-span-2">
                <label class="text-sm font-medium text-slate-200">Cidade</label>
                <input
                  v-model="form.cidade"
                  type="text"
                  :class="inputClass"
                />
              </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
              <div class="flex flex-col gap-2 md:col-span-2">
                <label class="text-sm font-medium text-slate-200">Bairro</label>
                <input
                  v-model="form.bairro"
                  type="text"
                  :class="inputClass"
                />
              </div>
              <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-slate-200">Número</label>
                <input
                  v-model="form.numero"
                  type="text"
                  :class="inputClass"
                />
              </div>
            </div>

            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-slate-200">Rua</label>
              <input
                v-model="form.rua"
                type="text"
                :class="inputClass"
              />
            </div>

            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-slate-200">Complemento</label>
              <input
                v-model="form.complemento"
                type="text"
                :class="inputClass"
              />
            </div>
          </div>

          <div class="lg:col-span-2 space-y-5 rounded-xl border border-slate-800/70 bg-slate-900/60 p-5 shadow-inner shadow-black/30">
            <header class="flex items-center gap-3">
              <span class="h-6 w-1 rounded-full bg-indigo-500" />
              <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-200">Dados bancários</h2>
            </header>

            <div class="grid gap-4 md:grid-cols-3">
              <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-slate-200">Banco</label>
                <input
                  v-model="form.bank.banco"
                  type="text"
                  :class="inputClass"
                  placeholder="Ex.: 001 - Banco do Brasil"
                />
              </div>
              <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-slate-200">Agência</label>
                <input
                  v-model="form.bank.agencia"
                  type="text"
                  inputmode="numeric"
                  :class="inputClass"
                  placeholder="Somente números"
                />
              </div>
              <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-slate-200">Conta</label>
                <input
                  v-model="form.bank.conta"
                  type="text"
                  :class="inputClass"
                  placeholder="Número e dígito"
                />
              </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
              <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-slate-200">Tipo de conta</label>
                <select
                  v-model="form.bank.tipo_conta"
                  :class="inputClass"
                >
                  <option value="corrente">Corrente</option>
                  <option value="poupanca">Poupança</option>
                  <option value="pagamento">Pagamento</option>
                </select>
              </div>
              <div class="flex flex-col gap-2 md:col-span-2">
                <label class="text-sm font-medium text-slate-200">Titular</label>
                <input
                  v-model="form.bank.titular"
                  type="text"
                  :class="inputClass"
                  placeholder="Nome completo"
                />
              </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
              <div class="flex flex-col gap-2 md:col-span-2">
                <label class="text-sm font-medium text-slate-200">Documento do titular</label>
                <input
                  v-model="form.bank.documento_titular"
                  type="text"
                  inputmode="numeric"
                  :class="inputClass"
                  placeholder="CPF/CNPJ (somente números)"
                  @input="form.bank.documento_titular = form.bank.documento_titular.replace(/\D/g, '')"
                />
              </div>
              <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-slate-200">Chave PIX</label>
                <input
                  v-model="form.bank.pix_chave"
                  type="text"
                  :class="inputClass"
                  placeholder="E-mail, CPF/CNPJ, telefone ou aleatória"
                />
              </div>
            </div>
          </div>

          <div class="lg:col-span-2 flex items-center justify-end gap-3">
            <Link
              href="/pessoas"
              class="rounded-lg border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800"
            >
              Cancelar
            </Link>
            <button
              type="submit"
              class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="saving || loading"
            >
              <svg
                v-if="saving"
                class="h-4 w-4 animate-spin text-white/90"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <circle class="opacity-20" cx="12" cy="12" r="9" />
                <path d="M21 12a9 9 0 0 0-9-9" stroke-linecap="round" />
              </svg>
              <span>{{ saving ? 'Salvando...' : isEditing ? 'Salvar alterações' : 'Cadastrar' }}</span>
            </button>
          </div>
        </form>
      </section>
    </div>
  </AuthenticatedLayout>
</template>
