<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { reactive } from 'vue';

const props = defineProps<{
  schedules: {
    data: Array<{
      id: number;
      titulo: string;
      valor_total: number | string;
      valor_total_formatado?: string;
      parcela_atual: number;
      total_parcelas: number;
      vencimento: string;
      status: string;
    }>;
    links: Array<{ url: string | null; label: string; active: boolean }>;
  };
  filters: { status: string | null };
  can: { create: boolean; update: boolean; delete: boolean };
}>();

const localFilters = reactive({ status: props.filters.status ?? '' });

const formatCurrency = (value: number | string) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value ?? 0));

const submitFilters = () => {
  router.get('/financeiro/agendamentos', { status: localFilters.status }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const handlePagination = (link: { url: string | null }) => {
  if (!link.url) {
    return;
  }

  router.visit(link.url, {
    preserveScroll: true,
    preserveState: true,
  });
};
</script>

<template>
  <AuthenticatedLayout title="Agendamentos">
    <Head title="Agendamentos" />

    <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
      <header class="mb-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-lg font-semibold text-white">Agendamento de pagamentos</h1>
          <p class="text-sm text-slate-400">Controle de parcelas e contas a pagar.</p>
        </div>
        <div class="flex items-center gap-3">
          <select
            v-model="localFilters.status"
            class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white"
            @change="submitFilters"
          >
            <option value="">Todos os status</option>
            <option value="aberto">Aberto</option>
            <option value="em_atraso">Em atraso</option>
            <option value="quitado">Quitado</option>
            <option value="cancelado">Cancelado</option>
          </select>
          <Link
            v-if="props.can.create"
            :href="route('financeiro.payment-schedules.create')"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500"
          >
            Novo agendamento
          </Link>
        </div>
      </header>

      <div class="overflow-hidden rounded-xl border border-slate-800">
        <table class="min-w-full divide-y divide-slate-800 text-sm text-slate-100">
          <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
            <tr>
              <th class="px-4 py-3 text-left">Título</th>
              <th class="px-4 py-3 text-left">Valor total</th>
              <th class="px-4 py-3 text-left">Parcela</th>
              <th class="px-4 py-3 text-left">Vencimento</th>
              <th class="px-4 py-3 text-left">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-if="props.schedules.data.length === 0">
              <td colspan="5" class="px-4 py-6 text-center text-slate-400">Nenhum agendamento disponível.</td>
            </tr>
            <tr v-for="schedule in props.schedules.data" :key="schedule.id">
              <td class="px-4 py-3 text-white">{{ schedule.titulo }}</td>
              <td class="px-4 py-3 text-slate-300">{{ schedule.valor_total_formatado ?? formatCurrency(schedule.valor_total) }}</td>
              <td class="px-4 py-3 text-slate-300">{{ schedule.parcela_atual }} / {{ schedule.total_parcelas }}</td>
              <td class="px-4 py-3 text-slate-300">{{ schedule.vencimento }}</td>
              <td class="px-4 py-3 text-xs">
                <span class="inline-flex rounded-full border border-slate-700 px-3 py-1 font-semibold text-slate-200">
                  {{ schedule.status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer
        v-if="props.schedules.links.length > 1"
        class="mt-4 flex flex-wrap items-center justify-center gap-2"
      >
        <button
          v-for="link in props.schedules.links"
          :key="link.label"
          type="button"
          class="rounded-md px-3 py-1 text-xs transition"
          :class="
            link.active
              ? 'bg-indigo-600 text-white'
              : link.url
                ? 'text-slate-300 hover:bg-slate-800'
                : 'text-slate-600 cursor-default'
          "
          v-html="link.label"
          @click="handlePagination(link)"
        />
      </footer>
    </section>
  </AuthenticatedLayout>
</template>

