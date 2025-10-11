<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
  username: '',
  password: '',
  remember: false,
});

const submit = () => {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  });
};
</script>

<template>
  <GuestLayout>
    <Head title="Entrar" />

    <form @submit.prevent="submit" class="space-y-6">
      <div>
        <label for="username" class="block text-sm font-medium text-slate-700">Usuário</label>
        <input
          id="username"
          v-model="form.username"
          type="text"
          autocomplete="username"
          required
          class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500"
        />
        <p v-if="form.errors.username" class="mt-2 text-sm text-rose-600">
          {{ form.errors.username }}
        </p>
      </div>

      <div>
        <label for="password" class="block text-sm font-medium text-slate-700">Senha</label>
        <input
          id="password"
          v-model="form.password"
          type="password"
          autocomplete="current-password"
          required
          class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500"
        />
        <p v-if="form.errors.password" class="mt-2 text-sm text-rose-600">
          {{ form.errors.password }}
        </p>
      </div>

      <div class="flex items-center justify-between">
        <label class="flex items-center gap-2 text-sm text-slate-600">
          <input
            v-model="form.remember"
            type="checkbox"
            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
          />
          Lembrar-me
        </label>
        <Link href="/" class="text-sm text-indigo-600 hover:text-indigo-500">Voltar</Link>
      </div>

      <button
        type="submit"
        class="flex w-full justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
        :disabled="form.processing"
      >
        Entrar
      </button>
    </form>
  </GuestLayout>
</template>

