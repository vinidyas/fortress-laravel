<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const form = useForm({
  username: '',
  password: '',
  remember: false,
});

const page = usePage();
const status = computed(() => page.props.status ?? null);

const submit = () => {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  });
};
</script>

<template>
  <GuestLayout>
    <Head title="Entrar" />

    <div class="space-y-8">
      <header class="space-y-2">
        <h1 class="text-3xl font-semibold text-white">Bem-vindo de volta</h1>
        <p class="text-sm text-slate-400">
          Use suas credenciais para acessar a plataforma Fortress e continuar a gestão das suas operações.
        </p>
      </header>

      <div
        v-if="status"
        class="rounded-2xl border border-emerald-400/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200 shadow-inner shadow-emerald-500/10"
        role="status"
      >
        {{ status }}
      </div>

      <form @submit.prevent="submit" class="flex flex-col gap-6">
        <div class="space-y-2">
          <label for="username" class="text-sm font-medium text-slate-200">Usuário</label>
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
              type="text"
              autocomplete="username"
              required
              class="w-full bg-transparent text-base text-slate-100 placeholder-slate-500 focus:outline-none"
              placeholder="Digite seu usuário"
            />
          </div>
          <p v-if="form.errors.username" class="text-sm text-rose-300">
            {{ form.errors.username }}
          </p>
        </div>

        <div class="space-y-2">
          <label for="password" class="text-sm font-medium text-slate-200">Senha</label>
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
              autocomplete="current-password"
              required
              class="w-full bg-transparent text-base text-slate-100 placeholder-slate-500 focus:outline-none"
              placeholder="Digite sua senha"
            />
          </div>
          <p v-if="form.errors.password" class="text-sm text-rose-300">
            {{ form.errors.password }}
          </p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4">
          <label class="flex items-center gap-3 text-sm text-slate-300">
            <input
              v-model="form.remember"
              type="checkbox"
              class="h-4 w-4 rounded border-slate-600 bg-slate-900 text-indigo-500 focus:ring-indigo-400/60"
            />
            Lembrar-me neste dispositivo
          </label>
          <Link :href="route('password.request')" class="text-sm font-medium text-indigo-300 transition hover:text-indigo-200">
            Esqueci minha senha
          </Link>
        </div>

        <button
          type="submit"
          class="relative flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-indigo-500 via-indigo-500 to-violet-500 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-500/40 transition duration-150 hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:ring-offset-2 focus:ring-offset-slate-950 disabled:cursor-not-allowed disabled:opacity-70"
          :class="{ 'cursor-progress': form.processing }"
          :disabled="form.processing"
        >
          <svg
            v-if="form.processing"
            class="h-4 w-4 animate-spin text-white/90"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <circle class="opacity-20" cx="12" cy="12" r="9" />
            <path d="M21 12a9 9 0 0 0-9-9" stroke-linecap="round" />
          </svg>
          <span>{{ form.processing ? 'Entrando...' : 'Entrar' }}</span>
        </button>
      </form>
    </div>
  </GuestLayout>
</template>
