<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import { route } from 'ziggy-js';

type UserProps = {
  id: number;
  nome: string;
  email: string | null;
  username: string;
  avatar_url: string | null;
};

const props = defineProps<{
  user: UserProps;
}>();

const page = usePage();
const status = computed(() => page.props.status ?? null);

const form = useForm({
  nome: props.user.nome ?? '',
  email: props.user.email ?? '',
  avatar: null as File | null,
  remove_avatar: false,
});

const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const avatarPreview = ref<string | null>(props.user.avatar_url ?? null);
let avatarObjectUrl: string | null = null;

const userInitials = computed(() => {
  const source = props.user.nome?.trim() || props.user.username?.trim() || '';
  if (!source) return '';
  return source
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((segment) => segment.charAt(0))
    .join('')
    .toUpperCase();
});

const setPreview = (url: string | null, isObject = false) => {
  if (avatarObjectUrl) {
    URL.revokeObjectURL(avatarObjectUrl);
    avatarObjectUrl = null;
  }
  avatarPreview.value = url;
  if (isObject && url) {
    avatarObjectUrl = url;
  }
};

function handleAvatarChange(event: Event): void {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0] ?? null;
  if (!file) return;
  form.avatar = file;
  form.remove_avatar = false;
  setPreview(URL.createObjectURL(file), true);
}

function removeAvatar(): void {
  form.avatar = null;
  form.remove_avatar = true;
  setPreview(null);
}

function resetAvatar(): void {
  form.avatar = null;
  form.remove_avatar = false;
  setPreview(props.user.avatar_url ?? null);
}

const submitAccount = () => {
  form.post(route('profile.update'), {
    method: 'put',
    preserveScroll: true,
    onSuccess: () => {
      form.avatar = null;
      form.remove_avatar = false;
    },
  });
};

const submitPassword = () => {
  passwordForm.put(route('profile.password.update'), {
    preserveScroll: true,
    onSuccess: () => passwordForm.reset(),
  });
};

onBeforeUnmount(() => {
  setPreview(null);
});
</script>

<template>
  <AuthenticatedLayout title="Minha Conta">
    <Head title="Perfil" />

    <div class="space-y-6 text-slate-100">
      <section class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 shadow-inner">
        <header class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 class="text-xl font-semibold text-white">Informações pessoais</h1>
            <p class="text-sm text-slate-400">Atualize nome, e-mail e foto exibidos no sistema.</p>
          </div>
          <span v-if="status" class="rounded-lg border border-emerald-400/40 bg-emerald-400/10 px-3 py-1 text-sm text-emerald-200">
            {{ status }}
          </span>
        </header>

        <form class="mt-6 grid gap-6 lg:grid-cols-[240px,1fr]" @submit.prevent="submitAccount">
          <div class="flex flex-col items-center gap-3">
            <div class="relative flex h-32 w-32 items-center justify-center rounded-full border border-slate-700 bg-slate-900 text-3xl font-semibold text-white shadow-inner">
              <img
                v-if="avatarPreview"
                :src="avatarPreview"
                alt="Avatar"
                class="h-full w-full rounded-full object-cover"
              />
              <span v-else>{{ userInitials }}</span>
            </div>
            <div class="flex flex-wrap items-center justify-center gap-3">
              <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-slate-800/60 px-4 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-800/80">
                <input type="file" accept="image/*" class="hidden" @change="handleAvatarChange" />
                Selecionar imagem
              </label>
              <button
                v-if="avatarPreview"
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-rose-500/40 px-4 py-2 text-sm font-medium text-rose-200 transition hover:bg-rose-500/15"
                @click="removeAvatar"
              >
                Remover
              </button>
              <button
                v-if="props.user.avatar_url && (form.remove_avatar || form.avatar)"
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-800/70"
                @click="resetAvatar"
              >
                Restaurar
              </button>
            </div>
            <p v-if="form.errors.avatar" class="text-xs text-rose-400">{{ form.errors.avatar }}</p>
            <p class="text-xs text-slate-500 text-center">Formatos comuns até 1 MB. A imagem será reduzida para 256px automaticamente.</p>
          </div>

          <div class="space-y-4">
            <div>
              <label class="text-sm font-medium text-slate-200" for="nome">Nome completo *</label>
              <input
                id="nome"
                v-model="form.nome"
                type="text"
                required
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
              />
              <p v-if="form.errors.nome" class="mt-1 text-xs text-rose-400">{{ form.errors.nome }}</p>
            </div>

            <div>
              <label class="text-sm font-medium text-slate-200" for="email">E-mail</label>
              <input
                id="email"
                v-model="form.email"
                type="email"
                placeholder="usuario@empresa.com"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
              />
              <p v-if="form.errors.email" class="mt-1 text-xs text-rose-400">{{ form.errors.email }}</p>
              <p class="mt-1 text-xs text-slate-500">Utilizado para notificações e recuperação de senha.</p>
            </div>

            <div>
              <label class="text-sm font-medium text-slate-200" for="username">Usuário</label>
              <input
                id="username"
                :value="props.user.username"
                type="text"
                disabled
                class="mt-1 w-full cursor-not-allowed rounded-lg border border-slate-700 bg-slate-900/30 px-3 py-2 text-sm text-slate-500"
              />
              <p class="mt-1 text-xs text-slate-500">O usuário é utilizado para login e não pode ser alterado.</p>
            </div>

            <div class="flex justify-end">
              <button
                type="submit"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:opacity-60"
                :disabled="form.processing"
              >
                Salvar alterações
              </button>
            </div>
          </div>
        </form>
      </section>

      <section class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 shadow-inner">
        <header class="mb-4">
          <h2 class="text-lg font-semibold text-white">Alterar senha</h2>
          <p class="text-sm text-slate-400">Informe a senha atual e defina uma nova combinação segura.</p>
        </header>

        <form class="grid gap-4 md:grid-cols-3" @submit.prevent="submitPassword">
          <div>
            <label class="text-sm font-medium text-slate-200" for="current_password">Senha atual *</label>
            <input
              id="current_password"
              v-model="passwordForm.current_password"
              type="password"
              autocomplete="current-password"
              required
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            />
            <p v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-rose-400">
              {{ passwordForm.errors.current_password }}
            </p>
          </div>

          <div>
            <label class="text-sm font-medium text-slate-200" for="password">Nova senha *</label>
            <input
              id="password"
              v-model="passwordForm.password"
              type="password"
              autocomplete="new-password"
              required
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            />
            <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-rose-400">
              {{ passwordForm.errors.password }}
            </p>
          </div>

          <div>
            <label class="text-sm font-medium text-slate-200" for="password_confirmation">Confirmar nova senha *</label>
            <input
              id="password_confirmation"
              v-model="passwordForm.password_confirmation"
              type="password"
              autocomplete="new-password"
              required
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            />
          </div>

          <div class="md:col-span-3 flex justify-end">
            <button
              type="submit"
              class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:opacity-60"
              :disabled="passwordForm.processing"
            >
              Atualizar senha
            </button>
          </div>
        </form>
      </section>
    </div>
  </AuthenticatedLayout>
</template>
