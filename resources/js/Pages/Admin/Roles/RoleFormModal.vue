<script setup lang="ts">
import axios from '@/bootstrap';
import { useToast } from '@/composables/useToast';
import { computed, reactive, watch } from 'vue';

type PermissionOption = {
  name: string;
  description?: string | null;
};

type EditableRole = {
  id: number;
  name: string;
  slug: string | null;
  description?: string | null;
  is_system?: boolean;
  permissions: Array<{ name: string; description?: string | null }>;
};

type Mode = 'create' | 'edit';

const props = defineProps<{
  show: boolean;
  mode: Mode;
  role: EditableRole | null;
  permissions: PermissionOption[];
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'saved'): void;
}>();

const toast = useToast();
const form = reactive({
  name: '',
  slug: '',
  description: '',
  permissions: [] as string[],
});
const saving = reactive({ value: false });
const errors = reactive<Record<string, string>>({});

const isEditing = computed(() => props.mode === 'edit');
const isSystemRole = computed(() => Boolean(props.role?.is_system));
const isAdminRole = computed(() => props.role?.slug === 'admin');

function clearErrors(): void {
  Object.keys(errors).forEach((key) => {
    errors[key] = '';
  });
}

function populateForm(role: EditableRole | null): void {
  form.name = role?.name ?? '';
  form.slug = role?.slug ?? '';
  form.description = role?.description ?? '';
  form.permissions = role ? role.permissions.map((permission) => permission.name) : [];
  clearErrors();
}

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      populateForm(props.role);
    }
  }
);

watch(
  () => props.role,
  (role) => {
    if (props.show) {
      populateForm(role ?? null);
    }
  }
);

function closeModal(): void {
  if (saving.value) return;
  emit('close');
}

async function submit(): Promise<void> {
  if (saving.value) return;
  clearErrors();

  const payload: Record<string, unknown> = {
    name: form.name,
    slug: form.slug || null,
    description: form.description || null,
    permissions: form.permissions,
  };

  saving.value = true;

  try {
    if (isEditing.value && props.role) {
      await axios.put(`/api/admin/roles/${props.role.id}`, payload);
      toast.success('Papel atualizado com sucesso.');
    } else {
      await axios.post('/api/admin/roles', payload);
      toast.success('Papel criado com sucesso.');
    }
    emit('saved');
    emit('close');
  } catch (error: any) {
    console.error(error);
    const responseErrors: Record<string, string[]> | undefined = error?.response?.data?.errors;
    if (responseErrors) {
      Object.entries(responseErrors).forEach(([field, messages]) => {
        errors[field] = Array.isArray(messages) ? messages[0] : String(messages);
      });
      const firstError = Object.values(errors).find((message) => message);
      if (firstError) toast.error(firstError);
    } else {
      const message = error?.response?.data?.message ?? 'Não foi possível salvar o papel.';
      toast.error(message);
    }
  } finally {
    saving.value = false;
  }
}

const modalTitle = computed(() => (isEditing.value ? 'Editar papel' : 'Novo papel'));
const inputClass = 'w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40';
</script>

<template>
  <transition name="fade">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur"
      @keydown.esc.prevent.stop="closeModal"
    >
      <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40">
        <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
          <div>
            <h2 class="text-lg font-semibold text-white">{{ modalTitle }}</h2>
            <p class="text-xs text-slate-400">Configure o nome, identificador e permissões deste papel.</p>
          </div>
          <button type="button" class="rounded-md p-2 text-slate-400 transition hover:text-white" @click="closeModal">
            <span class="sr-only">Fechar</span>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </header>

        <form class="max-h-[80vh] overflow-y-auto px-6 py-5" @submit.prevent="submit">
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Nome *</label>
            <input v-model="form.name" type="text" :class="inputClass" required />
            <p v-if="errors.name" class="text-xs text-rose-400">{{ errors.name }}</p>
          </div>

          <div class="mt-4 flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Identificador (slug) *</label>
            <input
              v-model="form.slug"
              type="text"
              :class="inputClass"
              placeholder="admin"
              :readonly="isEditing && (isSystemRole || isAdminRole)"
            />
            <p class="text-xs text-slate-500">Usado internamente para identificar o papel.</p>
            <p v-if="errors.slug" class="text-xs text-rose-400">{{ errors.slug }}</p>
            <p v-if="isAdminRole" class="text-xs text-amber-400">O identificador do papel de administrador não pode ser alterado.</p>
          </div>

          <div class="mt-4 flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Descrição</label>
            <textarea v-model="form.description" rows="2" :class="inputClass" placeholder="Resumo das responsabilidades"></textarea>
            <p v-if="errors.description" class="text-xs text-rose-400">{{ errors.description }}</p>
          </div>

          <section class="mt-6 space-y-3 rounded-xl border border-slate-800 bg-slate-950/60 p-4">
            <header>
              <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Permissões</h3>
              <p class="text-xs text-slate-400">Selecione as permissões que este papel concederá.</p>
            </header>
            <div class="grid max-h-64 gap-2 overflow-y-auto md:grid-cols-2">
              <label
                v-for="permission in permissions"
                :key="permission.name"
                class="flex items-start gap-2 rounded-lg border border-slate-800 bg-slate-900/60 p-3 text-xs text-slate-200"
              >
                <input
                  type="checkbox"
                  :value="permission.name"
                  v-model="form.permissions"
                  class="mt-0.5 h-4 w-4 rounded border-slate-600 bg-slate-800 text-indigo-500 focus:ring-indigo-500"
                />
                <span>
                  <span class="font-semibold text-white">{{ permission.name }}</span>
                  <span v-if="permission.description" class="block text-[11px] text-slate-400">{{ permission.description }}</span>
                </span>
              </label>
            </div>
            <p v-if="errors.permissions" class="text-xs text-rose-400">{{ errors.permissions }}</p>
          </section>

          <div class="mt-6 flex items-center justify-end gap-3">
            <button
              type="button"
              class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800/60"
              :disabled="saving.value"
              @click="closeModal"
            >
              Cancelar
            </button>
            <button
              type="submit"
              class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:opacity-60"
              :disabled="saving.value"
            >
              <svg
                v-if="saving.value"
                class="h-4 w-4 animate-spin"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8 8 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8 8 0 01-15.357-2m15.357 2H15" />
              </svg>
              {{ saving.value ? 'Salvando...' : isEditing ? 'Atualizar papel' : 'Criar papel' }}
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
