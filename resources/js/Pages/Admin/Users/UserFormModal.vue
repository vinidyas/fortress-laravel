<script setup lang="ts">
import axios from '@/bootstrap';
import { useToast } from '@/composables/useToast';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';

type RoleOption = {
  id: number;
  name: string;
  slug: string | null;
  description?: string | null;
  is_system?: boolean;
  permissions: string[];
};

type PermissionOption = {
  name: string;
  description?: string | null;
};

type EditableUser = {
  id: number;
  nome: string;
  username: string;
  email: string | null;
  role_id: number | null;
  roles: Array<{ id: number; name: string; slug: string | null }>;
  direct_permissions: string[];
  custom_permissions: string[];
  avatar_url: string | null;
  ativo: boolean;
};

type Mode = 'create' | 'edit';

const props = defineProps<{
  show: boolean;
  mode: Mode;
  user: EditableUser | null;
  roles: RoleOption[];
  permissions: PermissionOption[];
  currentUserId: number | null;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'saved'): void;
}>();

const toast = useToast();

const form = reactive({
  nome: '',
  username: '',
  email: '',
  password: '',
  password_confirmation: '',
  role_id: null as number | null,
  role_ids: [] as number[],
  permissions: [] as string[],
  ativo: true,
  remove_avatar: false,
  send_password_reset: true,
});

const errors = reactive<Record<string, string>>({});
const saving = ref(false);
const avatarFile = ref<File | null>(null);
const avatarPreview = ref<string | null>(null);
const originalAvatar = ref<string | null>(null);
const avatarInput = ref<HTMLInputElement | null>(null);
let avatarObjectUrl: string | null = null;

const isEditing = computed(() => props.mode === 'edit');

function clearErrors(): void {
  Object.keys(errors).forEach((key) => {
    errors[key] = '';
  });
}

function closeModal(): void {
  if (saving.value) return;
  emit('close');
}

function setAvatarPreview(value: string | null, isObjectUrl = false): void {
  if (avatarObjectUrl) {
    URL.revokeObjectURL(avatarObjectUrl);
    avatarObjectUrl = null;
  }
  avatarPreview.value = value;
  if (isObjectUrl && value) {
    avatarObjectUrl = value;
  }
}

function ensurePrimaryRole(): void {
  if (form.role_id && !form.role_ids.includes(form.role_id)) {
    form.role_ids.push(form.role_id);
  }
  if (!form.role_id && form.role_ids.length > 0) {
    form.role_id = form.role_ids[0];
  }
}

const selectedRoleIds = computed<number[]>(() => {
  const collected = form.role_ids.slice();
  if (form.role_id && !collected.includes(form.role_id)) {
    collected.push(form.role_id);
  }

  return Array.from(
    new Set(collected.filter((id): id is number => Number.isFinite(id)))
  );
});

const inheritedPermissions = computed<Set<string>>(() => {
  const set = new Set<string>();

  props.roles.forEach((role) => {
    if (selectedRoleIds.value.includes(role.id)) {
      (role.permissions ?? []).forEach((permission) => set.add(permission));
    }
  });

  return set;
});

function pruneInheritedPermissions(): void {
  const filtered = form.permissions.filter((permission) => !inheritedPermissions.value.has(permission));
  if (filtered.length !== form.permissions.length) {
    form.permissions.splice(0, form.permissions.length, ...filtered);
  }
}

watch(inheritedPermissions, () => {
  pruneInheritedPermissions();
});

const emailCandidate = computed(() => {
  const email = form.email?.trim();
  if (email) return email;
  return form.username.trim();
});

const isEmail = (value: string): boolean => /[^\s@]+@[^\s@]+\.[^\s@]+/.test(value);

const canSendPasswordReset = computed(() => emailCandidate.value !== '' && isEmail(emailCandidate.value));

watch(canSendPasswordReset, (canSend) => {
  if (!canSend) {
    form.send_password_reset = false;
  } else if (!isEditing.value && !form.send_password_reset) {
    form.send_password_reset = true;
  }
});

const userInitials = computed(() => {
  const source = (form.nome || form.username || '').trim();
  if (!source) return '';
  return source
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((segment) => segment.charAt(0))
    .join('')
    .toUpperCase();
});

function togglePermission(permissionName: string, checked: boolean): void {
  if (inheritedPermissions.value.has(permissionName)) {
    return;
  }

  if (checked) {
    if (!form.permissions.includes(permissionName)) {
      form.permissions.push(permissionName);
    }
    return;
  }

  const index = form.permissions.indexOf(permissionName);
  if (index !== -1) {
    form.permissions.splice(index, 1);
  }
}

function populateFromUser(user: EditableUser | null): void {
  form.nome = user?.nome ?? '';
  form.username = user?.username ?? '';
  form.email = user?.email ?? '';
  form.password = '';
  form.password_confirmation = '';
  form.role_id = user?.role_id ?? (user?.roles?.[0]?.id ?? props.roles[0]?.id ?? null);
  const roleIds = user ? user.roles.map((role) => role.id) : form.role_id ? [form.role_id] : [];
  form.role_ids = Array.from(new Set(roleIds.filter((id): id is number => Number.isFinite(id))));
  ensurePrimaryRole();
  form.permissions = user
    ? Array.from(new Set([...(user.direct_permissions ?? []), ...(user.custom_permissions ?? [])]))
    : [];
  pruneInheritedPermissions();
  form.ativo = user?.ativo ?? true;
  form.remove_avatar = false;
  form.send_password_reset = isEditing.value ? false : true;
  avatarFile.value = null;
  originalAvatar.value = user?.avatar_url ?? null;
  setAvatarPreview(originalAvatar.value);
  clearErrors();
}

function toggleRole(roleId: number, checked: boolean): void {
  if (checked) {
    if (!form.role_ids.includes(roleId)) {
      form.role_ids.push(roleId);
    }
  } else {
    if (form.role_id === roleId) {
      return;
    }
    form.role_ids = form.role_ids.filter((id) => id !== roleId);
  }
  pruneInheritedPermissions();
}

function setPrimaryRole(roleId: number): void {
  form.role_id = roleId;
  ensurePrimaryRole();
  pruneInheritedPermissions();
}

async function submit(): Promise<void> {
  if (saving.value) return;
  clearErrors();
  const formData = new FormData();
  formData.append('nome', form.nome);
  formData.append('username', form.username);
  if (form.email) {
    formData.append('email', form.email);
  }
  if (form.role_id !== null) {
    formData.append('role_id', String(form.role_id));
  }
  selectedRoleIds.value.forEach((id) => {
    formData.append('roles[]', String(id));
  });
  form.permissions.forEach((permission) => {
    formData.append('permissions[]', permission);
  });

  if (isEditing.value) {
    if (form.password.trim() !== '') {
      formData.append('password', form.password);
      formData.append('password_confirmation', form.password_confirmation);
    }
    formData.append('ativo', form.ativo ? '1' : '0');
  } else {
    formData.append('password', form.password);
    formData.append('password_confirmation', form.password_confirmation);
    formData.append('ativo', '1');
  }

  if (avatarFile.value) {
    formData.append('avatar', avatarFile.value);
  }

  if (form.remove_avatar) {
    formData.append('remove_avatar', '1');
  }

  if (form.send_password_reset) {
    formData.append('send_password_reset', '1');
  }

  saving.value = true;

  try {
    if (isEditing.value && props.user) {
      formData.append('_method', 'PUT');
      await axios.post(`/api/admin/users/${props.user.id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      toast.success('Usuário atualizado com sucesso.');
    } else {
      await axios.post('/api/admin/users', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      toast.success('Usuário criado com sucesso.');
    }
    emit('saved');
    emit('close');
  } catch (error: any) {
    console.error(error);
    const responseErrors: Record<string, string[]> | undefined = error?.response?.data?.errors;
    if (responseErrors) {
      const friendlyMap: Record<string, string> = {
        'validation.required': 'Preencha todos os campos obrigatórios.',
        'validation.min.string': 'A senha deve conter no mínimo :min caracteres.',
        'validation.confirmed': 'A confirmação da senha não confere.',
      };

      Object.entries(responseErrors).forEach(([field, messages]) => {
        const message = Array.isArray(messages) ? messages[0] : String(messages);
        const friendly = friendlyMap[message] ?? message;
        errors[field] = friendly.replace(':min', '8');
      });

      const firstError = Object.values(errors).find((message) => message);
      if (firstError) toast.error(firstError);
    } else {
      const message = error?.response?.data?.message ?? 'Não foi possível salvar o usuário.';
      toast.error(message);
    }
  } finally {
    saving.value = false;
  }
}

const modalTitle = computed(() => (isEditing.value ? 'Editar usuário' : 'Novo usuário'));

const inputClass = 'w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40';

function handleAvatarChange(event: Event): void {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0] ?? null;
  if (!file) return;
  avatarFile.value = file;
  form.remove_avatar = false;
  const previewUrl = URL.createObjectURL(file);
  setAvatarPreview(previewUrl, true);
}

function removeAvatar(): void {
  avatarFile.value = null;
  form.remove_avatar = true;
  setAvatarPreview(null);
}

function resetAvatarToOriginal(): void {
  avatarFile.value = null;
  form.remove_avatar = false;
  setAvatarPreview(originalAvatar.value);
}

function triggerAvatarPicker(): void {
  avatarInput.value?.click();
}

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      populateFromUser(props.user);
    }
  }
);

watch(
  () => props.user,
  (user) => {
    if (props.show) {
      populateFromUser(user ?? null);
    }
  }
);

onBeforeUnmount(() => {
  setAvatarPreview(null);
});
</script>

<template>
  <transition name="fade">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur"
      @keydown.esc.prevent.stop="closeModal"
    >
      <div class="relative w-full max-w-3xl overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40">
        <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
          <div>
            <h2 class="text-lg font-semibold text-white">{{ modalTitle }}</h2>
            <p class="text-xs text-slate-400">Defina as credenciais, papéis e permissões do usuário.</p>
          </div>
          <button type="button" class="rounded-md p-2 text-slate-400 transition hover:text-white" @click="closeModal">
            <span class="sr-only">Fechar</span>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </header>

        <form class="max-h-[80vh] overflow-y-auto px-6 py-5" @submit.prevent="submit">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-slate-200">Nome completo *</label>
              <input v-model="form.nome" type="text" :class="inputClass" required />
              <p v-if="errors.nome" class="text-xs text-rose-400">{{ errors.nome }}</p>
            </div>
            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-slate-200">Usuário *</label>
              <input v-model="form.username" type="text" :class="inputClass" required />
              <p v-if="errors.username" class="text-xs text-rose-400">{{ errors.username }}</p>
            </div>
          </div>

          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-slate-200">E-mail (para notificações)</label>
              <input v-model="form.email" type="email" :class="inputClass" placeholder="usuario@empresa.com" />
              <p v-if="errors.email" class="text-xs text-rose-400">{{ errors.email }}</p>
              <p class="text-xs text-slate-500">Utilizado para envio automático de links de redefinição.</p>
            </div>
            <div class="flex flex-col gap-2">
              <span class="text-sm font-medium text-slate-200">Redefinição de senha</span>
              <label class="flex items-start gap-2 rounded-lg border border-slate-700/70 bg-slate-900/60 p-3 text-xs text-slate-200">
                <input
                  v-model="form.send_password_reset"
                  type="checkbox"
                  :disabled="!canSendPasswordReset"
                  class="mt-0.5 h-4 w-4 rounded border-slate-600 bg-slate-800 text-indigo-500 focus:ring-indigo-500 disabled:opacity-60"
                />
                <span>{{ isEditing ? 'Enviar link de redefinição ao salvar alterações' : 'Enviar link para o usuário definir a senha após criar' }}</span>
              </label>
              <p v-if="!canSendPasswordReset" class="text-xs text-amber-400">
                Informe um e-mail válido (ou utilize um usuário no formato de e-mail) para habilitar o envio automático.
              </p>
            </div>
          </div>

          <section class="mt-6 rounded-xl border border-slate-800 bg-slate-950/60 p-4">
            <header class="mb-4">
              <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Avatar</h3>
              <p class="text-xs text-slate-400">Imagem exibida no cabeçalho ao lado do nome.</p>
            </header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
              <div class="flex items-center justify-center">
                <div class="flex h-20 w-20 items-center justify-center rounded-full border border-slate-700 bg-slate-900 text-lg font-semibold text-white shadow-inner">
                  <img
                    v-if="avatarPreview"
                    :src="avatarPreview"
                    alt="Pré-visualização do avatar"
                    class="h-full w-full rounded-full object-cover"
                  />
                  <span v-else>{{ userInitials }}</span>
                </div>
              </div>
              <div class="flex flex-1 flex-col gap-3">
                <input ref="avatarInput" type="file" accept="image/*" class="hidden" @change="handleAvatarChange" />
                <div class="flex flex-wrap items-center gap-3">
                  <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-slate-800/60 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-800/80"
                    @click="triggerAvatarPicker"
                  >
                    Selecionar imagem
                  </button>
                  <button
                    v-if="avatarPreview"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-rose-500/40 px-3 py-2 text-sm font-medium text-rose-200 transition hover:bg-rose-500/10"
                    @click="removeAvatar"
                  >
                    Remover
                  </button>
                  <button
                    v-if="originalAvatar && (form.remove_avatar || avatarFile)"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-800/70"
                    @click="resetAvatarToOriginal"
                  >
                    Restaurar original
                  </button>
                </div>
                <p class="text-xs text-slate-400">Imagens quadradas até 1&nbsp;MB. O sistema reduz automaticamente para 256&nbsp;px para garantir desempenho.</p>
                <p v-if="errors.avatar" class="text-xs text-rose-400">{{ errors.avatar }}</p>
              </div>
            </div>
          </section>

          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-slate-200">Senha {{ isEditing ? '(opcional)' : '*' }}</label>
              <input v-model="form.password" type="password" :class="inputClass" :required="!isEditing" placeholder="••••••••" />
              <p v-if="errors.password" class="text-xs text-rose-400">{{ errors.password }}</p>
            </div>
            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-slate-200">Confirmar senha {{ isEditing ? '(opcional)' : '*' }}</label>
              <input v-model="form.password_confirmation" type="password" :class="inputClass" :required="!isEditing" placeholder="••••••••" />
              <p v-if="errors.password_confirmation" class="text-xs text-rose-400">{{ errors.password_confirmation }}</p>
            </div>
          </div>

          <section class="mt-6 space-y-3 rounded-xl border border-slate-800 bg-slate-950/60 p-4">
            <header>
              <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Papéis</h3>
              <p class="text-xs text-slate-400">Selecione o papel principal e demais papéis atribuídos.</p>
            </header>
            <div class="space-y-3">
              <div
                v-for="role in roles"
                :key="role.id"
                class="flex flex-col gap-2 rounded-lg border border-slate-700/60 bg-slate-900/70 p-3 text-sm text-slate-200"
              >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                  <div class="flex flex-col">
                    <span class="font-semibold text-white">{{ role.name }}</span>
                    <span class="text-xs text-slate-400">{{ role.description ?? 'Papel do sistema' }}</span>
                  </div>
                  <div class="flex flex-wrap items-center gap-3 text-xs text-slate-300">
                    <label class="inline-flex items-center gap-2">
                      <input
                        type="radio"
                        name="primary-role"
                        :value="role.id"
                        :checked="form.role_id === role.id"
                        @change="setPrimaryRole(role.id)"
                        class="h-4 w-4 rounded-full border-slate-600 bg-slate-800 text-indigo-500 focus:ring-indigo-500"
                      />
                      Papel principal
                    </label>
                    <label class="inline-flex items-center gap-2">
                      <input
                        type="checkbox"
                        :value="role.id"
                        :checked="form.role_ids.includes(role.id)"
                        @change="toggleRole(role.id, ($event.target as HTMLInputElement).checked)"
                        class="h-4 w-4 rounded border-slate-600 bg-slate-800 text-indigo-500 focus:ring-indigo-500"
                      />
                      Atribuir
                    </label>
                  </div>
                </div>
              </div>
            </div>
            <p v-if="errors.roles" class="text-xs text-rose-400">{{ errors.roles }}</p>
          </section>

          <section class="mt-6 space-y-3 rounded-xl border border-slate-800 bg-slate-950/60 p-4">
            <header>
              <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Permissões extras</h3>
              <p class="text-xs text-slate-400">Selecione permissões adicionais além das concedidas pelos papéis.</p>
            </header>
            <div class="grid gap-2 md:grid-cols-2">
              <label
                v-for="permission in permissions"
                :key="permission.name"
                class="flex items-start gap-2 rounded-lg border border-slate-800 bg-slate-900/60 p-3 text-xs text-slate-200"
              >
                <input
                  type="checkbox"
                  :value="permission.name"
                  :checked="inheritedPermissions.has(permission.name) || form.permissions.includes(permission.name)"
                  :disabled="inheritedPermissions.has(permission.name)"
                  @change="togglePermission(permission.name, ($event.target as HTMLInputElement).checked)"
                  class="mt-0.5 h-4 w-4 rounded border-slate-600 bg-slate-800 text-indigo-500 focus:ring-indigo-500 disabled:opacity-60"
                />
                <span>
                  <span class="font-semibold text-white">{{ permission.name }}</span>
                  <span v-if="permission.description" class="block text-[11px] text-slate-400">{{ permission.description }}</span>
                  <span
                    v-if="inheritedPermissions.has(permission.name)"
                    class="mt-1 inline-flex items-center rounded-full bg-indigo-500/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-200"
                  >
                    pelo papel selecionado
                  </span>
                </span>
              </label>
            </div>
            <p v-if="errors.permissions" class="text-xs text-rose-400">{{ errors.permissions }}</p>
          </section>

          <div v-if="isEditing" class="mt-6">
            <label class="inline-flex items-center gap-2 text-sm text-slate-200">
              <input v-model="form.ativo" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-800 text-indigo-500 focus:ring-indigo-500" />
              Usuário ativo
            </label>
            <p v-if="props.currentUserId === props.user?.id" class="text-xs text-amber-400">Você não pode desativar o próprio usuário.</p>
            <p v-if="errors.ativo" class="text-xs text-rose-400">{{ errors.ativo }}</p>
          </div>

          <div class="mt-6 flex items-center justify-end gap-3">
            <button
              type="button"
              class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800/60"
              :disabled="saving"
              @click="closeModal"
            >
              Cancelar
            </button>
            <button
              type="submit"
              class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:opacity-60"
              :disabled="saving"
            >
              <svg
                v-if="saving"
                class="h-4 w-4 animate-spin"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8 8 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8 8 0 01-15.357-2m15.357 2H15" />
              </svg>
              {{ saving ? 'Salvando...' : isEditing ? 'Atualizar' : 'Criar usuário' }}
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
