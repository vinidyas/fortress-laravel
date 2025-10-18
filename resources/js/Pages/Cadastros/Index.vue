<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { computed } from 'vue';
import type { PageProps } from '@/types/page';

type CadastroItem = {
  key: string;
  title: string;
  description: string;
  href: string;
  icon: string;
  ability?: string;
};

const page = usePage<PageProps>();
const abilities = computed<string[]>(() => page.props.auth?.abilities ?? []);
const can = (permission?: string) => !permission || abilities.value.includes(permission);

const cadastros: CadastroItem[] = [
  {
    key: 'pessoas',
    title: 'Pessoas',
    description: 'Cadastre locadores, locatários, fiadores e demais participantes do ecossistema.',
    href: route('pessoas.index'),
    icon: 'M5.5 17a6.5 6.5 0 0113 0M12 9a4 4 0 110-8 4 4 0 010 8z',
    ability: 'pessoas.view',
  },
  {
    key: 'financeiro-accounts',
    title: 'Contas Financeiras',
    description: 'Cadastre e mantenha as contas utilizadas para movimentações e repasses.',
    href: route('financeiro.accounts'),
    icon: 'M3 3h18M5 7h14v12H5z',
    ability: 'financeiro.view',
  },
  {
    key: 'centros-custo',
    title: 'Centros de Custo',
    description: 'Organize lançamentos financeiros por centro de custo e acompanhe suas métricas.',
    href: route('financeiro.cost-centers'),
    icon: 'M4 4h16v16H4z M9 9h6v6H9z',
    ability: 'financeiro.view',
  },
  {
    key: 'condominios',
    title: 'Condomínios',
    description: 'Cadastre condomínios e seus dados de endereço e contato.',
    href: '/condominios',
    icon: 'M4 4h16v16H4z M9 9h6v6H9z',
    ability: 'condominios.view',
  },
];
</script>

<template>
  <AuthenticatedLayout title="Cadastros">
    <Head title="Cadastros" />

    <div class="space-y-8 text-slate-100">
      <header class="space-y-2">
        <h2 class="text-2xl font-semibold text-white">Cadastros</h2>
        <p class="text-sm text-slate-400">
          Centralize o acesso aos principais cadastros do sistema e mantenha informações sempre atualizadas.
        </p>
      </header>

      <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        <article
          v-for="item in cadastros.filter(c => can(c.ability))"
          :key="item.key"
          class="group flex h-full flex-col rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40 transition hover:border-indigo-500/60 hover:bg-indigo-950/30"
        >
          <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600/20 text-indigo-300 transition group-hover:bg-indigo-600 group-hover:text-white">
              <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
              </svg>
            </div>
            <h3 class="text-lg font-semibold text-white">{{ item.title }}</h3>
          </div>

          <p class="mt-4 flex-1 text-sm text-slate-400">{{ item.description }}</p>

          <div class="mt-6">
            <Link :href="item.href" class="inline-flex items-center gap-2 rounded-lg border border-indigo-500/40 bg-indigo-500/20 px-3 py-2 text-sm font-semibold text-indigo-200 transition hover:border-indigo-400 hover:bg-indigo-500/30 hover:text-white">
              Acessar
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          </div>
        </article>
      </section>
    </div>
  </AuthenticatedLayout>
</template>


