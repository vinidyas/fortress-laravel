<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps<{
  token: string;
  username?: string | null;
}>();

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

    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-slate-800">Definir nova senha</h1>
        <p class="mt-1 text-sm text-slate-600">Informe o usuário e escolha uma nova senha segura.</p>
      </div>

      <form class="space-y-5" @submit.prevent="submit">
        <input type="hidden" name="token" :value="form.token" />

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
          <label for="password" class="block text-sm font-medium text-slate-700">Nova senha</label>
          <input
            id="password"
            v-model="form.password"
            type="password"
            autocomplete="new-password"
            required
            class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500"
          />
          <p v-if="form.errors.password" class="mt-2 text-sm text-rose-600">
            {{ form.errors.password }}
          </p>
        </div>

        <div>
          <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirmar nova senha</label>
          <input
            id="password_confirmation"
            v-model="form.password_confirmation"
            type="password"
            autocomplete="new-password"
            required
            class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500"
          />
        </div>

        <button
          type="submit"
          class="flex w-full justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60"
          :disabled="form.processing"
        >
          Atualizar senha
        </button>
      </form>

      <div class="flex items-center justify-between text-sm">
        <Link :href="route('login')" class="text-indigo-600 hover:text-indigo-500">Voltar para o login</Link>
        <Link :href="route('password.request')" class="text-slate-500 hover:text-slate-700">Reenviar link</Link>
      </div>
    </div>
  </GuestLayout>
</template>
