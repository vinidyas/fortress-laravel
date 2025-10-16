<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const form = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const page = usePage();
const status = computed(() => page.props.status ?? null);

const submit = () => {
  form.put(route('profile.password.update'), {
    preserveScroll: true,
    onSuccess: () => form.reset('current_password', 'password', 'password_confirmation'),
  });
};
</script>

<template>
  <AuthenticatedLayout title="Segurança">
    <Head title="Alterar senha" />

    <div class="space-y-6 text-slate-100">
      <section class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 shadow-inner">
        <header class="mb-4">
          <h1 class="text-xl font-semibold text-white">Alterar senha</h1>
          <p class="mt-1 text-sm text-slate-400">
            Utilize uma senha forte com no mínimo 8 caracteres, combinando letras maiúsculas, minúsculas, números e símbolos.
          </p>
        </header>

        <div v-if="status" class="mb-4 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
          {{ status }}
        </div>

        <form class="grid gap-5 md:grid-cols-2" @submit.prevent="submit">
          <div class="md:col-span-2">
            <label class="text-sm font-medium text-slate-200" for="current_password">Senha atual</label>
            <input
              id="current_password"
              v-model="form.current_password"
              type="password"
              autocomplete="current-password"
              required
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            />
            <p v-if="form.errors.current_password" class="mt-1 text-xs text-rose-400">
              {{ form.errors.current_password }}
            </p>
          </div>

          <div>
            <label class="text-sm font-medium text-slate-200" for="password">Nova senha</label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              autocomplete="new-password"
              required
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            />
            <p v-if="form.errors.password" class="mt-1 text-xs text-rose-400">
              {{ form.errors.password }}
            </p>
          </div>

          <div>
            <label class="text-sm font-medium text-slate-200" for="password_confirmation">Confirmar nova senha</label>
            <input
              id="password_confirmation"
              v-model="form.password_confirmation"
              type="password"
              autocomplete="new-password"
              required
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            />
            <p v-if="form.errors.password_confirmation" class="mt-1 text-xs text-rose-400">
              {{ form.errors.password_confirmation }}
            </p>
          </div>

          <div class="md:col-span-2 flex items-center justify-end gap-3">
            <button
              type="submit"
              class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:opacity-60"
              :disabled="form.processing"
            >
              Salvar nova senha
            </button>
          </div>
        </form>
      </section>
    </div>
  </AuthenticatedLayout>
</template>
