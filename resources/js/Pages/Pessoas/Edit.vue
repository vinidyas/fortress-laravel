<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { computed, onMounted, reactive, ref } from 'vue';
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
}

const props = defineProps<{ pessoaId?: number | null }>();

const isEditing = computed(() => Boolean(props.pessoaId));
const loading = ref(false);
const saving = ref(false);
const errorMessage = ref('');

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
});
const cepLoading = ref(false);

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

  const payload = { ...form, papeis: form.papeis };

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
</script>

<template>
  <AuthenticatedLayout :title="isEditing ? 'Editar pessoa' : 'Nova pessoa'">
    <div class="mb-6 flex items-center justify-between">
      <h2 class="text-2xl font-semibold text-slate-900">
        {{ isEditing ? 'Editar pessoa' : 'Nova pessoa' }}
      </h2>
      <Link class="text-sm font-semibold text-indigo-600 hover:text-indigo-500" href="/pessoas"
        >Voltar</Link
      >
    </div>

    <div
      v-if="errorMessage"
      class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
    >
      {{ errorMessage }}
    </div>

    <form @submit.prevent="submit" class="grid gap-6 md:grid-cols-2">
      <section class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700">Nome / Razao social</label>
          <input
            v-model="form.nome_razao_social"
            type="text"
            required
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Tipo</label>
          <select
            v-model="form.tipo_pessoa"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
          >
            <option v-for="option in tipoOptions" :key="option" :value="option">
              {{ option }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">CPF / CNPJ</label>
          <input
            v-model="form.cpf_cnpj"
            type="text"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
            placeholder="Somente numeros"
          />
        </div>
      </section>

      <section class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700">Email</label>
          <input
            v-model="form.email"
            type="email"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Telefone</label>
          <input
            v-model="form.telefone"
            type="text"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">CEP</label>
          <div class="mt-1 flex items-center gap-2">
            <input
              v-model="form.cep"
              type="text"
              class="w-full rounded-md border border-slate-300 px-3 py-2"
              placeholder="Somente números"
            />
            <button type="button" @click="fetchCep" :disabled="cepLoading" class="whitespace-nowrap rounded-md border border-indigo-500/40 bg-indigo-600/80 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-500/80">
              {{ cepLoading ? 'Buscando...' : 'Buscar' }}
            </button>
          </div>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
          <div>
            <label class="block text-sm font-medium text-slate-700">Estado</label>
            <input v-model="form.estado" type="text" maxlength="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 uppercase" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700">Cidade</label>
            <input v-model="form.cidade" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700">Bairro</label>
            <input v-model="form.bairro" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
          </div>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-slate-700">Rua</label>
            <input v-model="form.rua" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700">Número</label>
            <input v-model="form.numero" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Complemento / Apto</label>
          <input v-model="form.complemento" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Papeis</label>
          <div class="mt-1 grid gap-2 sm:grid-cols-2">
            <label
              v-for="option in papelOptions"
              :key="option.value"
              class="flex items-center gap-2 text-sm text-slate-600"
            >
              <input
                type="checkbox"
                :value="option.value"
                :checked="form.papeis.includes(option.value)"
                @change="togglePapel(option.value)"
                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
              />
              {{ option.label }}
            </label>
          </div>
        </div>
      </section>

      <div class="md:col-span-2 flex items-center justify-end gap-3">
        <Link
          href="/pessoas"
          class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
          Cancelar
        </Link>
        <button
          type="submit"
          class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
          :disabled="saving || loading"
        >
          {{ saving ? 'Salvando...' : 'Salvar' }}
        </button>
      </div>
    </form>
  </AuthenticatedLayout>
</template>
