<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import type { PageProps } from '@/types/page';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps<{
  token: string;
  username?: string | null;
}>();

const page = usePage<PageProps>();
const sharedProps = computed<PageProps>(() => {
  const raw = page?.props as unknown;

  if (!raw) return {} as PageProps;

  if (typeof raw === 'object' && raw && 'value' in raw) {
    return ((raw as { value?: PageProps }).value ?? {}) as PageProps;
  }

  return (raw as PageProps) ?? ({} as PageProps);
});
const portalHost = computed(() => sharedProps.value.portal?.domain ?? 'portal.fortressempreendimentos.com.br');
const isPortal = computed(() => {
  const sharedFlag = sharedProps.value.portal?.isPortalDomain ?? false;

  if (sharedFlag) return true;

  if (typeof window === 'undefined') {
    return false;
  }

  try {
    return window.location.hostname === portalHost.value;
  } catch {
    return false;
  }
});
const normalizedPortalUrl = computed(() => {
  const domain = sharedProps.value.portal?.domain;

  if (!domain) {
    return 'https://portal.fortressempreendimentos.com.br';
  }

  if (domain.startsWith('http://') || domain.startsWith('https://')) {
    return domain.replace(/\/+$/, '');
  }

  return `https://${domain}`.replace(/\/+$/, '');
});
const portalLoginUrl = computed(() => `${normalizedPortalUrl.value}/login`);
const usernameLabel = computed(() => (isPortal.value ? 'E-mail' : 'Usuário'));
const usernameType = computed(() => (isPortal.value ? 'email' : 'text'));
const usernameAutocomplete = computed(() => (isPortal.value ? 'email' : 'username'));
const usernamePlaceholder = computed(() => (isPortal.value ? 'seu@email.com' : 'Digite seu usuário'));

const form = useForm({
  token: props.token,
  username: props.username ?? '',
  password: '',
  password_confirmation: '',
});

const submit = () => {
  form.post(route('password.store'), {
    onSuccess: () => form.reset('password', 'password_confirmation'),
  });
};
</script>

<template>
  <GuestLayout>
    <Head title="Definir nova senha" />

    <div class="space-y-8">
      <header class="space-y-3 text-center sm:text-left">
        <h1 class="text-3xl font-semibold text-white">
          {{ isPortal ? 'Atualize sua senha do portal' : 'Definir nova senha' }}
        </h1>
        <p class="text-sm text-slate-400">
          {{
            isPortal
              ? 'Informe seu e-mail cadastrado e escolha uma nova senha para continuar acessando o Portal do Locatário.'
              : 'Informe o usuário e escolha uma nova senha segura para voltar a acessar o sistema.'
          }}
        </p>
      </header>

      <form class="flex flex-col gap-6" @submit.prevent="submit">
        <input type="hidden" name="token" :value="form.token" />

        <div class="space-y-2">
          <label for="username" class="text-sm font-medium text-slate-200">{{ usernameLabel }}</label>
          <div
            class="relative flex items-center rounded-2xl border bg-slate-900/70 px-4 py-3 transition focus-within:ring-2"
            :class="
              form.errors.username
                ? 'border-rose-500/60 focus-within:ring-rose-400/60'
                : 'border-slate-700/70 focus-within:border-indigo-400 focus-within:ring-indigo-400/50'
            "
          >
            <input
              id="username"
              v-model="form.username"
              :type="usernameType"
              :autocomplete="usernameAutocomplete"
              :placeholder="usernamePlaceholder"
              required
              class="w-full bg-transparent text-base text-slate-100 placeholder-slate-500 focus:outline-none"
            />
          </div>
          <p v-if="form.errors.username" class="text-sm text-rose-300">
            {{ form.errors.username }}
          </p>
        </div>

        <div class="space-y-2">
          <label for="password" class="text-sm font-medium text-slate-200">Nova senha</label>
          <div
            class="relative flex items-center rounded-2xl border bg-slate-900/70 px-4 py-3 transition focus-within:ring-2"
            :class="
              form.errors.password
                ? 'border-rose-500/60 focus-within:ring-rose-400/60'
                : 'border-slate-700/70 focus-within:border-indigo-400 focus-within:ring-indigo-400/50'
            "
          >
            <input
              id="password"
              v-model="form.password"
              type="password"
              autocomplete="new-password"
              required
              class="w-full bg-transparent text-base text-slate-100 placeholder-slate-500 focus:outline-none"
              placeholder="Digite a nova senha"
            />
          </div>
          <p v-if="form.errors.password" class="text-sm text-rose-300">
            {{ form.errors.password }}
          </p>
        </div>

        <div class="space-y-2">
          <label for="password_confirmation" class="text-sm font-medium text-slate-200">Confirmar nova senha</label>
          <div
            class="relative flex items-center rounded-2xl border bg-slate-900/70 px-4 py-3 transition focus-within:ring-2"
            :class="
              form.errors.password_confirmation
                ? 'border-rose-500/60 focus-within:ring-rose-400/60'
                : 'border-slate-700/70 focus-within:border-indigo-400 focus-within:ring-indigo-400/50'
            "
          >
            <input
              id="password_confirmation"
              v-model="form.password_confirmation"
              type="password"
              autocomplete="new-password"
              required
              class="w-full bg-transparent text-base text-slate-100 placeholder-slate-500 focus:outline-none"
              placeholder="Repita a nova senha"
            />
          </div>
          <p v-if="form.errors.password_confirmation" class="text-sm text-rose-300">
            {{ form.errors.password_confirmation }}
          </p>
        </div>

        <button
          type="submit"
          class="flex w-full justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 focus:ring-offset-slate-900 disabled:opacity-60"
          :disabled="form.processing"
        >
          Atualizar senha
        </button>
      </form>

      <div class="flex flex-col gap-3 text-sm text-slate-300 sm:flex-row sm:items-center sm:justify-between">
        <Link
          :href="isPortal ? portalLoginUrl : route('login')"
          class="font-medium text-indigo-300 transition hover:text-indigo-200"
        >
          Voltar para o login
        </Link>
        <Link :href="route('password.request')" class="text-slate-400 transition hover:text-slate-200">
          Reenviar link
        </Link>
      </div>
    </div>
  </GuestLayout>
</template>
