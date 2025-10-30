<script setup lang="ts">
import axios from '@/bootstrap';
import type { AxiosError } from 'axios';
import { computed, reactive, ref, watch } from 'vue';
import { useToast } from '@/composables/useToast';
import MoneyInput from '@/Components/Form/MoneyInput.vue';

type ParentOption = {
  id: number;
  nome: string;
  codigo: string;
  parent_id: number | null;
  depth: number;
};

type TipoOption = 'fixo' | 'variavel' | 'investimento';

interface CostCenterPayload {
  nome: string;
  descricao: string;
  codigo: string;
  parent_id: number | null;
  tipo: TipoOption;
  ativo: boolean;
  orcamento_anual: string;
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
    tipo: TipoOption;
    ativo: boolean;
    orcamento_anual: string | number | null;
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
const errors = reactive<Record<string, string>>({});
const form = reactive<CostCenterPayload>({
  nome: '',
  descricao: '',
  codigo: '',
  parent_id: null,
  tipo: 'variavel',
  ativo: true,
  orcamento_anual: '',
});

const tipoOptions: Array<{ value: TipoOption; label: string }> = [
  { value: 'fixo', label: 'Fixo' },
  { value: 'variavel', label: 'Variável' },
  { value: 'investimento', label: 'Investimento' },
];

const isEdit = computed(() => props.mode === 'edit');
const parentMap = computed(
  () => new Map<number, ParentOption>(props.parents.map((option) => [option.id, option]))
);

const isDescendant = (candidateId: number, targetId: number): boolean => {
  let current = parentMap.value.get(candidateId);

  while (current && current.parent_id) {
    if (current.parent_id === targetId) {
      return true;
    }

    current = parentMap.value.get(current.parent_id);
  }

  return false;
};

const availableParents = computed(() => {
  if (!props.center) {
    return props.parents;
  }

  const currentId = props.center.id;

  return props.parents.filter(
    (parent) => parent.id !== currentId && !isDescendant(parent.id, currentId)
  );
});

const formatParentLabel = (option: ParentOption): string => {
  const indent = '\u00a0\u00a0'.repeat(option.depth);

  return `${indent}${option.codigo} — ${option.nome}`;
};

const resetErrors = () => {
  Object.keys(errors).forEach((key) => delete errors[key]);
};

const syncForm = () => {
  form.nome = props.center?.nome ?? '';
  form.descricao = props.center?.descricao ?? '';
  form.codigo = props.center?.codigo ?? '';
  form.parent_id = props.center?.parent_id ?? null;
  form.tipo = props.center?.tipo ?? 'variavel';
  form.ativo = props.center?.ativo ?? true;
  form.orcamento_anual = props.center?.orcamento_anual
    ? String(props.center.orcamento_anual)
    : '';
};

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      syncForm();
      resetErrors();
      formError.value = '';

      if (props.mode === 'create' && form.parent_id) {
        form.codigo = generateCodigoForParent(form.parent_id);
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
  () => form.parent_id,
  (value) => {
    if (props.mode !== 'create') {
      return;
    }

    if (!value) {
      form.codigo = '';
      return;
    }

    form.codigo = generateCodigoForParent(value);
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
      parent_id: form.parent_id,
      tipo: form.tipo,
      ativo: form.ativo,
      orcamento_anual:
        form.orcamento_anual.trim() === '' ? null : Number.parseFloat(form.orcamento_anual),
    };

    if (isEdit.value && props.center) {
      const response = await axios.put(
        `/api/financeiro/cost-centers/${props.center.id}`,
        payload
      );
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
      Object.entries(validation).forEach(([key, messages]) => {
        errors[key] = Array.isArray(messages) ? messages[0] : String(messages);
      });
      formError.value =
        axiosError.response.data?.message ?? 'Corrija os campos destacados e tente novamente.';
    } else {
      const message =
        axiosError.response?.data?.message ??
        'Não foi possível salvar o centro de custo. Tente novamente.';
      formError.value = message;
      toast.error(message);
    }
  } finally {
    submitting.value = false;
  }
};

const escapeRegex = (value: string): string => value.replace(/[.*+?^${}()|[\]\\]/g, '\\\\$&');

const normalizeParentPrefix = (codigo: string): string => {
  const segments = codigo.split('.');
  while (segments.length > 1 && segments[segments.length - 1] === '0') {
    segments.pop();
  }

  return segments.join('.') || codigo;
};

const generateCodigoForParent = (parentId: number | null): string => {
  if (!parentId) {
    return generateRootCodigo();
  }

  const parent = parentMap.value.get(parentId);
  if (!parent) {
    return generateRootCodigo();
  }

  const base = normalizeParentPrefix(parent.codigo);
  const siblings = props.parents.filter((option) => option.parent_id === parentId);
  const pattern = new RegExp(`^${escapeRegex(base)}\\.(\\d+)$`);

  let maxSuffix = 0;
  siblings.forEach((sibling) => {
    const match = sibling.codigo.match(pattern);
    if (!match) {
      return;
    }

    const suffix = Number.parseInt(match[1] ?? '0', 10);
    if (!Number.isNaN(suffix)) {
      maxSuffix = Math.max(maxSuffix, suffix);
    }
  });

  return `${base}.${maxSuffix + 1}`;
};

const generateRootCodigo = (): string => {
  const roots = props.parents.filter((option) => option.parent_id === null);
  let max = 0;

  roots.forEach((root) => {
    const match = root.codigo.match(/^(\d+)\.0$/);
    if (!match) {
      return;
    }

    const value = Number.parseInt(match[1] ?? '0', 10);
    if (!Number.isNaN(value)) {
      max = Math.max(max, value);
    }
  });

  return `${Math.max(1, max + 1)}.0`;
};
</script>

<template>
  <transition name="fade">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur"
      @keydown.esc.prevent.stop="close"
    >
      <div
        class="relative w-full max-w-3xl rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40"
      >
        <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
          <div>
            <h2 class="text-lg font-semibold text-white">
              {{ isEdit ? 'Editar centro de custo' : 'Novo centro de custo' }}
            </h2>
            <p class="text-xs text-slate-400">
              Configure nome, código, hierarquia e orçamento deste centro de custo.
            </p>
          </div>
          <button
            type="button"
            class="rounded-md p-2 text-slate-400 transition hover:text-white"
            @click="close"
          >
            <span class="sr-only">Fechar</span>
            <svg
              class="h-5 w-5"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </header>

        <form class="space-y-5 px-6 py-5" @submit.prevent="submit">
          <div
            v-if="formError"
            class="rounded-lg border border-rose-500/40 bg-rose-500/15 px-4 py-2 text-sm text-rose-100"
          >
            {{ formError }}
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="flex flex-col gap-1 md:col-span-2">
              <label class="text-sm font-medium text-slate-200">Nome *</label>
              <input
                v-model="form.nome"
                type="text"
                required
                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                placeholder="Administrativo"
              />
              <p v-if="errors.nome" class="text-xs text-rose-400">{{ errors.nome }}</p>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-slate-200">Código</label>
              <input
                v-model="form.codigo"
                type="text"
                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                placeholder="1.0"
              />
              <p v-if="errors.codigo" class="text-xs text-rose-400">{{ errors.codigo }}</p>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-slate-200">Centro pai</label>
              <select
                v-model="form.parent_id"
                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              >
                <option :value="null">Sem vínculo</option>
                <option v-for="option in availableParents" :key="option.id" :value="option.id">
                  {{ formatParentLabel(option) }}
                </option>
              </select>
              <p v-if="errors.parent_id" class="text-xs text-rose-400">{{ errors.parent_id }}</p>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-slate-200">Tipo *</label>
              <select
                v-model="form.tipo"
                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              >
                <option v-for="option in tipoOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
              <p v-if="errors.tipo" class="text-xs text-rose-400">{{ errors.tipo }}</p>
            </div>
            <div class="flex flex-col gap-1 md:col-span-2">
              <label class="text-sm font-medium text-slate-200">Descrição</label>
              <textarea
                v-model="form.descricao"
                rows="3"
                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                placeholder="Detalhes sobre o centro de custo"
              />
              <p v-if="errors.descricao" class="text-xs text-rose-400">{{ errors.descricao }}</p>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-slate-200">Orçamento anual</label>
              <MoneyInput
                v-model="form.orcamento_anual"
                name="orcamento_anual"
                placeholder="0,00"
                :input-class="'border-slate-700 bg-slate-900 text-white'"
              />
              <p v-if="errors.orcamento_anual" class="text-xs text-rose-400">
                {{ errors.orcamento_anual }}
              </p>
            </div>
          </div>

          <div class="rounded-xl border border-slate-800 bg-slate-950/40 px-4 py-3">
            <label class="inline-flex items-center gap-2 text-sm text-slate-200">
              <input
                v-model="form.ativo"
                type="checkbox"
                class="rounded border-slate-600 bg-slate-900 text-indigo-500 focus:ring-indigo-500"
              />
              Centro ativo
            </label>
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
              Salvar
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
