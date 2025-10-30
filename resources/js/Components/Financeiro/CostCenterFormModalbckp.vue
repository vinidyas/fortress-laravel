<script setup lang="ts">
import axios from '@/bootstrap';
import type { AxiosError } from 'axios';
import { computed, reactive, ref, watch } from 'vue';
import { useToast } from '@/composables/useToast';

type ParentOption = {
  id: number;
  nome: string;
  codigo: string;
  children: Array<{ id: number; codigo: string }>;
};

interface CostCenterPayload {
  nome: string;
  descricao: string;
  codigo: string;
  tipo: 'principal' | 'sub';
  parent_id: number | null;
}

const props = defineProps<{
  show: boolean;
  mode: 'create' | 'edit';
  center: {
    id: number;
    nome: string;
    descricao: string | null;
    codigo: string;
    parent_id: number | null;
  } | null;
  parents: ParentOption[];
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'saved'): void;
}>();

const toast = useToast();
const submitting = ref(false);
const formError = ref('');
const errors = reactive<{ nome?: string; descricao?: string; codigo?: string; parent_id?: string }>({});
const form = reactive<CostCenterPayload>({
  nome: '',
  descricao: '',
  codigo: '',
  tipo: 'principal',
  parent_id: null,
});

const isEdit = computed(() => props.mode === 'edit');
const parentMap = computed(() =>
  new Map<number, ParentOption>(props.parents.map((parent) => [parent.id, parent]))
);
const availableParents = computed(() => {
  if (!props.center) {
    return props.parents;
  }

  return props.parents.filter((parent) => parent.id !== props.center?.id);
});
const isSub = computed(() => form.tipo === 'sub');

const resetErrors = () => {
  errors.nome = undefined;
  errors.descricao = undefined;
  errors.codigo = undefined;
  errors.parent_id = undefined;
};

const syncForm = () => {
  form.nome = props.center?.nome ?? '';
  form.descricao = props.center?.descricao ?? '';
  form.codigo = props.center?.codigo ?? '';
  form.parent_id = props.center?.parent_id ?? null;
  form.tipo = form.parent_id ? 'sub' : 'principal';
};

const generateSubCodigo = (parentId: number | null): string => {
  if (!parentId) {
    return '';
  }

  const parent = parentMap.value.get(parentId);
  if (!parent) {
    return '';
  }

  const base = parent.codigo.split('.')[0] ?? parent.codigo;
  const siblings = parent.children ?? [];
  const maxSuffix = siblings.reduce((acc, child) => {
    if (isEdit.value && props.center?.id === child.id) {
      return acc;
    }

    const [, suffixValue] = child.codigo.split('.');
    const parsed = Number.parseInt(suffixValue ?? '0', 10);
    return Number.isNaN(parsed) ? acc : Math.max(acc, parsed);
  }, 0);

  if (
    isEdit.value &&
    props.center?.parent_id === parentId &&
    props.center.codigo.startsWith(`${base}.`)
  ) {
    return props.center.codigo;
  }

  const nextSuffix = maxSuffix + 1;

  return `${base}.${nextSuffix}`;
};

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      syncForm();
      resetErrors();
      formError.value = '';

      if (form.tipo === 'sub') {
        if (!form.parent_id && availableParents.value.length) {
          form.parent_id = availableParents.value[0].id;
        }
        form.codigo = generateSubCodigo(form.parent_id);
      }
    }
  }
);

watch(
  () => props.center,
  () => {
    if (props.show) {
      syncForm();
    }
  }
);

watch(
  () => form.tipo,
  (value) => {
    if (value === 'principal') {
      form.parent_id = null;
      if (!isEdit.value) {
        form.codigo = '';
      }
    } else {
      if (!form.parent_id && availableParents.value.length) {
        form.parent_id = availableParents.value[0].id;
      }
      form.codigo = generateSubCodigo(form.parent_id);
    }
  }
);

watch(
  () => form.parent_id,
  (value, oldValue) => {
    if (!isSub.value) {
      return;
    }

    if (value === oldValue && isEdit.value) {
      return;
    }

    form.codigo = generateSubCodigo(value ?? null);
  }
);

const close = () => {
  if (submitting.value) {
    return;
  }

  emit('close');
};

const submit = async () => {
  if (submitting.value) {
    return;
  }

  submitting.value = true;
  resetErrors();
  formError.value = '';

  try {
    const payload: Record<string, unknown> = {
      nome: form.nome,
      descricao: form.descricao.trim() === '' ? null : form.descricao,
      codigo: form.codigo.trim() === '' ? null : form.codigo.trim(),
      parent_id: isSub.value ? form.parent_id : null,
    };

    if (isEdit.value && props.center) {
      const response = await axios.put(`/api/financeiro/cost-centers/${props.center.id}`, payload);
      toast.success(response.data?.message ?? 'Centro de custo atualizado com sucesso.');
    } else {
      const response = await axios.post('/api/financeiro/cost-centers', payload);
      toast.success(response.data?.message ?? 'Centro de custo criado com sucesso.');
    }

    emit('saved');
  } catch (error) {
    const axiosError = error as AxiosError<{ errors?: Record<string, string[]>; message?: string }>;

    if (axiosError.response?.status === 422) {
      const validation = axiosError.response.data?.errors ?? {};
      Object.entries(validation).forEach(([field, messages]) => {
        const message = Array.isArray(messages) ? String(messages[0]) : String(messages);
        if (field in errors) {
          (errors as Record<string, string | undefined>)[field] = message;
        }
      });
      formError.value =
        axiosError.response.data?.message ?? 'Corrija os campos destacados e tente novamente.';
      return;
    }

    const message =
      axiosError.response?.data?.message ?? 'Nao foi possivel salvar o centro de custo. Tente novamente.';
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
      v-if="props.show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur"
      @keydown.esc.prevent.stop="close"
    >
      <div
        class="relative w-full max-w-xl rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40"
      >
        <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
          <div>
            <h2 class="text-lg font-semibold text-white">
              {{ isEdit ? 'Editar centro de custo' : 'Novo centro de custo' }}
            </h2>
            <p class="text-xs text-slate-400">
              Estruture centros principais e subcentros para organizar lancamentos.
            </p>
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

        <form class="space-y-4 px-6 py-5" @submit.prevent="submit">
          <div
            v-if="formError"
            class="rounded-lg border border-rose-500/40 bg-rose-500/15 px-4 py-2 text-sm text-rose-100"
          >
            {{ formError }}
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-200">Nome *</label>
            <input
              v-model="form.nome"
              type="text"
              required
              maxlength="150"
              class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              placeholder="Centro de custo"
            />
            <p v-if="errors.nome" class="text-xs text-rose-400">{{ errors.nome }}</p>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-200">Codigo</label>
            <input
              v-model="form.codigo"
              type="text"
              maxlength="20"
              :readonly="isSub"
              class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:opacity-70"
              placeholder="ex: 1.0"
            />
            <p v-if="errors.codigo" class="text-xs text-rose-400">{{ errors.codigo }}</p>
          </div>

          <div class="flex flex-col gap-2">
            <span class="text-sm font-medium text-slate-200">Tipo *</span>
            <div class="flex flex-wrap gap-4 text-sm text-slate-200">
              <label class="inline-flex items-center gap-2">
                <input type="radio" value="principal" v-model="form.tipo" />
                Principal
              </label>
              <label class="inline-flex items-center gap-2">
                <input
                  type="radio"
                  value="sub"
                  v-model="form.tipo"
                  :disabled="!availableParents.length"
                />
                Subcentro
                <span v-if="!availableParents.length" class="text-xs text-slate-400">
                  (cadastre um principal primeiro)
                </span>
              </label>
            </div>
          </div>

          <div v-if="isSub" class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-200">Centro principal *</label>
            <select
              v-model.number="form.parent_id"
              class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              required
            >
              <option :value="null" disabled>Selecione um centro principal</option>
              <option v-for="parent in availableParents" :key="parent.id" :value="parent.id">
                {{ parent.codigo }} - {{ parent.nome }}
              </option>
            </select>
            <p v-if="errors.parent_id" class="text-xs text-rose-400">{{ errors.parent_id }}</p>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-200">Descricao</label>
            <textarea
              v-model="form.descricao"
              rows="3"
              maxlength="255"
              class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              placeholder="Opcional"
            />
            <p v-if="errors.descricao" class="text-xs text-rose-400">{{ errors.descricao }}</p>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2">
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
              {{ submitting ? 'Salvando...' : isEdit ? 'Salvar alteracoes' : 'Criar centro' }}
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
