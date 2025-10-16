<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const form = useForm({
  username: '',
});

const page = usePage();
const status = computed(() => page.props.status ?? null);

const submit = () => {
  form.post(route('password.email'), {
    onSuccess: () => form.reset(),
  });
};
</script>

<template>
  <GuestLayout>
    <Head title="Recuperar senha" />

    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-slate-800">Esqueci minha senha</h1>
        <p class="mt-1 text-sm text-slate-600">
          Informe o seu usuário. Enviaremos um link de redefinição para o e-mail cadastrado.
        </p>
      </div>

      <div v-if="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
        {{ status }}
      </div>

      <form class="space-y-5" @submit.prevent="submit">
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

        <button
          type="submit"
          class="flex w-full justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60"
          :disabled="form.processing"
        >
          Enviar link de redefinição
        </button>
      </form>

      <div class="flex items-center justify-between text-sm">
        <Link :href="route('login')" class="text-indigo-600 hover:text-indigo-500">Voltar para o login</Link>
        <Link href="/" class="text-slate-500 hover:text-slate-700">Ir para o site</Link>
      </div>
    </div>
  </GuestLayout>
</template>
