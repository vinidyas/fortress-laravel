<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import TransactionStatusBadge from '@/Components/Financeiro/TransactionStatusBadge.vue';
import TransactionTypeBadge from '@/Components/Financeiro/TransactionTypeBadge.vue';

interface TransactionRow {
  id: number;
  data_ocorrencia: string | null;
  data_ocorrencia_formatada?: string | null;
  descricao: string | null;
  tipo: 'credito' | 'debito';
  valor: number | string;
  valor_formatado?: string;
  status: 'pendente' | 'conciliado' | 'cancelado';
  account?: { id: number; nome: string } | null;
}

const props = defineProps<{
  items: TransactionRow[];
  links: Array<{ url: string | null; label: string; active: boolean }>;
  can: { create: boolean; reconcile: boolean; export: boolean };
  filters: Record<string, unknown>;
}>();

export type { TransactionRow };

const hasItems = computed(() => props.items.length > 0);

const exportUrl = () => {
  const params = new URLSearchParams();
  Object.entries(props.filters).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '') {
      return;
    }
    params.append(key, String(value));
  });
  params.append('format', 'csv');

  return `/api/financeiro/transactions/export?${params.toString()}`;
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
  <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70">
    <header
      class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 px-4 py-3 text-sm text-slate-300"
    >
      <h2 class="font-semibold text-white">Lançamentos</h2>
      <div class="flex items-center gap-2">
        <a
          v-if="props.can.export"
          class="rounded-lg border border-slate-700 px-3 py-1 text-xs text-slate-200 transition hover:bg-slate-800"
          :href="exportUrl()"
        >
          Exportar CSV
        </a>
        <Link
          v-if="props.can.create"
          href="/financeiro/transactions/novo"
          class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-semibold text-white shadow hover:bg-indigo-500"
        >
          Novo lançamento
        </Link>
      </div>
    </header>

    <table class="min-w-full divide-y divide-slate-800 text-sm text-slate-100">
      <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
        <tr>
          <th class="px-4 py-3 text-left">Data</th>
          <th class="px-4 py-3 text-left">Conta</th>
          <th class="px-4 py-3 text-left">Descrição</th>
          <th class="px-4 py-3 text-left">Tipo</th>
          <th class="px-4 py-3 text-left">Valor</th>
          <th class="px-4 py-3 text-left">Status</th>
          <th class="px-4 py-3 text-right">Ações</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-800">
        <tr v-if="!hasItems" class="text-slate-400">
          <td colspan="7" class="px-4 py-6 text-center">Nenhum lançamento encontrado.</td>
        </tr>
        <tr v-else v-for="item in props.items" :key="item.id" class="hover:bg-slate-900/60">
          <td class="px-4 py-3 text-slate-300">
            {{ item.data_ocorrencia_formatada ?? item.data_ocorrencia ?? '-' }}
          </td>
          <td class="px-4 py-3 text-slate-200">{{ item.account?.nome ?? '-' }}</td>
          <td class="px-4 py-3 text-slate-200">{{ item.descricao ?? '-' }}</td>
          <td class="px-4 py-3">
            <TransactionTypeBadge :tipo="item.tipo" />
          </td>
          <td class="px-4 py-3 text-right font-semibold text-white">
            {{
              item.valor_formatado ??
              new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL',
              }).format(Number(item.valor ?? 0))
            }}
          </td>
          <td class="px-4 py-3">
            <TransactionStatusBadge :status="item.status" />
          </td>
          <td class="px-4 py-3 text-right text-xs">
            <Link
              :href="`/financeiro/transactions/${item.id}`"
              class="rounded-lg border border-slate-700 px-2 py-1 text-slate-200 transition hover:bg-slate-800"
            >
              Ver
            </Link>
          </td>
        </tr>
      </tbody>
    </table>

    <footer
      v-if="props.links.length > 1"
      class="flex flex-wrap items-center justify-center gap-2 border-t border-slate-800 px-4 py-3"
    >
      <button
        v-for="link in props.links"
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
</template>
