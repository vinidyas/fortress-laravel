<script setup lang="ts">
import axios from '@/bootstrap';
import { useToast } from '@/composables/useToast';
import { computed, reactive, ref, watch } from 'vue';

type ModalMode = 'create' | 'edit';

interface CondominioPayload {
  nome: string;
  cnpj: string;
  cep: string;
  estado: string;
  cidade: string;
  bairro: string;
  rua: string;
  numero: string;
  complemento: string;
  telefone: string;
  email: string;
  observacoes: string;
}

export type CondominioFormDraft = {
  form: CondominioPayload;
};

export type CondominioSavedPayload = {
  id: number;
  nome: string;
  cep: string | null;
  estado: string | null;
  cidade: string | null;
  bairro: string | null;
  rua: string | null;
  numero: string | null;
  complemento: string | null;
};

type Props = {
  mode: ModalMode;
  condominioId?: number | null;
  draft?: CondominioFormDraft | null;
};

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'saved', payload: CondominioSavedPayload): void;
  (e: 'cancel'): void;
}>();

const toast = useToast();
const isEditing = computed(() => props.mode === 'edit' && Boolean(props.condominioId));
const loading = ref(isEditing.value);
const saving = ref(false);
const errorMessage = ref('');

const form = reactive<CondominioPayload>({
  nome: '',
  cnpj: '',
  cep: '',
  estado: '',
  cidade: '',
  bairro: '',
  rua: '',
  numero: '',
  complemento: '',
  telefone: '',
  email: '',
  observacoes: '',
});

const inputClass =
  'w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40';

function resetForm(): void {
  form.nome = '';
  form.cnpj = '';
  form.cep = '';
  form.estado = '';
  form.cidade = '';
  form.bairro = '';
  form.rua = '';
  form.numero = '';
  form.complemento = '';
  form.telefone = '';
  form.email = '';
  form.observacoes = '';
  errorMessage.value = '';
}

function applyDraft(draft: CondominioFormDraft): void {
  Object.assign(form, JSON.parse(JSON.stringify(draft.form)));
}

async function loadCondominio(id: number): Promise<void> {
  loading.value = true;
  errorMessage.value = '';

  try {
    const { data } = await axios.get(`/api/condominios/${id}`);
    const payload = data.data;
    form.nome = payload.nome ?? '';
    form.cnpj = payload.cnpj ?? '';
    form.cep = payload.cep ?? '';
    form.estado = payload.estado ?? '';
    form.cidade = payload.cidade ?? '';
    form.bairro = payload.bairro ?? '';
    form.rua = payload.rua ?? '';
    form.numero = payload.numero ?? '';
    form.complemento = payload.complemento ?? '';
    form.telefone = payload.telefone ?? '';
    form.email = payload.email ?? '';
    form.observacoes = payload.observacoes ?? '';
  } catch (error) {
    console.error(error);
    errorMessage.value = 'Não foi possível carregar o condomínio.';
  } finally {
    loading.value = false;
  }
}

async function submit(): Promise<void> {
  if (saving.value) return;

  saving.value = true;
  errorMessage.value = '';

  try {
    if (isEditing.value && props.condominioId) {
      const { data } = await axios.put(`/api/condominios/${props.condominioId}`, { ...form });
      toast.success('Condomínio atualizado com sucesso.');
      emit('saved', mapCondominioPayload(data?.data));
    } else {
      const { data } = await axios.post('/api/condominios', { ...form });
      toast.success('Condomínio criado com sucesso.');
      emit('saved', mapCondominioPayload(data?.data));
      resetForm();
    }
  } catch (error: any) {
    console.error(error);
    if (error?.response?.status === 422) {
      const messages = error.response.data?.errors ?? {};
      errorMessage.value = Object.values(messages).flat().join(' ');
    } else {
      errorMessage.value = error?.response?.data?.message ?? 'Não foi possível salvar o condomínio.';
    }
    toast.error(errorMessage.value || 'Não foi possível salvar o condomínio.');
  } finally {
    saving.value = false;
  }
}

watch(
  () => [props.mode, props.condominioId, props.draft] as const,
  async ([mode, id]) => {
    if (mode === 'edit' && id) {
      await loadCondominio(id);
    } else {
      resetForm();
      if (props.draft) {
        applyDraft(props.draft);
      }
      loading.value = false;
    }
  },
  { immediate: true }
);

function handleCancel(): void {
  emit('cancel');
}

function mapCondominioPayload(data: any): CondominioSavedPayload {
  return {
    id: data?.id ?? 0,
    nome: data?.nome ?? '',
    cep: data?.cep ?? null,
    estado: data?.estado ?? null,
    cidade: data?.cidade ?? null,
    bairro: data?.bairro ?? null,
    rua: data?.rua ?? data?.logradouro ?? null,
    numero: data?.numero ?? null,
    complemento: data?.complemento ?? null,
  };
}
</script>

<template>
  <div class="space-y-6">
    <div
      v-if="errorMessage"
      class="rounded-xl border border-rose-500/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-200"
    >
      {{ errorMessage }}
    </div>

    <div
      v-if="loading"
      class="flex items-center justify-center rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-10 text-sm text-slate-300"
    >
      Carregando dados do condomínio...
    </div>

    <form v-else class="space-y-8" @submit.prevent="submit">
      <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-5 shadow-inner shadow-black/20">
        <header class="flex items-center gap-3">
          <span class="h-6 w-1 rounded-full bg-indigo-500"></span>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Informações gerais</h3>
        </header>
        <div class="grid gap-4 md:grid-cols-2">
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Nome *</label>
            <input v-model="form.nome" type="text" required :class="inputClass" />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">CNPJ</label>
            <input v-model="form.cnpj" type="text" :class="inputClass" placeholder="Somente números" />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Telefone</label>
            <input v-model="form.telefone" type="text" :class="inputClass" />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Email</label>
            <input v-model="form.email" type="email" :class="inputClass" />
          </div>
        </div>
      </section>

      <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-5 shadow-inner shadow-black/20">
        <header class="flex items-center gap-3">
          <span class="h-6 w-1 rounded-full bg-emerald-500"></span>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Endereço</h3>
        </header>
        <div class="grid gap-3 md:grid-cols-3">
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">CEP</label>
            <input v-model="form.cep" type="text" :class="inputClass" />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Estado</label>
            <input v-model="form.estado" type="text" maxlength="2" :class="inputClass" />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Cidade</label>
            <input v-model="form.cidade" type="text" :class="inputClass" />
          </div>
        </div>

        <div class="grid gap-3 md:grid-cols-3">
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Bairro</label>
            <input v-model="form.bairro" type="text" :class="inputClass" />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Rua</label>
            <input v-model="form.rua" type="text" :class="inputClass" />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Número</label>
            <input v-model="form.numero" type="text" :class="inputClass" />
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium text-slate-200">Complemento / Apto</label>
          <input v-model="form.complemento" type="text" :class="inputClass" />
        </div>
      </section>

      <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-5 shadow-inner shadow-black/20">
        <header class="flex items-center gap-3">
          <span class="h-6 w-1 rounded-full bg-amber-500"></span>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Observações</h3>
        </header>
        <div class="flex flex-col gap-2">
          <textarea v-model="form.observacoes" rows="4" :class="inputClass"></textarea>
        </div>
      </section>

      <div class="flex items-center justify-end gap-3">
        <button
          type="button"
          class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800/60"
          @click="handleCancel"
          :disabled="saving"
        >
          Cancelar
        </button>
        <button
          type="submit"
          class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500"
          :disabled="saving"
        >
          {{ saving ? 'Salvando...' : 'Salvar' }}
        </button>
      </div>
    </form>
  </div>
</template>
