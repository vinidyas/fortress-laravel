<template>
  <PortalLayout>
    <Head title="Minhas faturas" />

    <section class="space-y-8">
      <header class="flex flex-col gap-2">
        <h1 class="text-3xl font-semibold text-white">Minhas faturas</h1>
        <p class="text-sm text-slate-400">
          Consulte os boletos gerados, acompanhe o status dos pagamentos e emita recibos das faturas quitadas.
        </p>
      </header>

      <div v-if="loading" class="rounded-xl border border-slate-800 bg-slate-900/60 p-6 text-sm text-slate-300">
        Carregando faturas...
      </div>

      <div v-else-if="error" class="rounded-xl border border-rose-500/30 bg-rose-500/10 p-6 text-sm text-rose-100">
        {{ error }}
      </div>

      <div
        v-else-if="!hasInvoices"
        class="rounded-xl border border-slate-800 bg-slate-900/60 p-6 text-center text-sm text-slate-300"
      >
        Nenhuma fatura encontrada até o momento. Assim que novos boletos forem emitidos, eles aparecerão aqui.
      </div>

      <div v-else class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/40 shadow-lg shadow-black/30">
        <table class="min-w-full divide-y divide-slate-800 text-sm">
          <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
            <tr>
              <th scope="col" class="px-4 py-3 text-left">Fatura</th>
              <th scope="col" class="px-4 py-3 text-left">Competência</th>
              <th scope="col" class="px-4 py-3 text-left">Vencimento</th>
              <th scope="col" class="px-4 py-3 text-left">Status</th>
              <th scope="col" class="px-4 py-3 text-right">Valor</th>
              <th scope="col" class="px-4 py-3 text-left">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60 text-slate-200">
            <tr
              v-for="invoice in invoices"
              :key="invoice.id"
              class="transition hover:bg-slate-900/70"
            >
              <td class="px-4 py-4">
                <p class="font-semibold text-white">#{{ invoice.id }}</p>
                <p class="text-xs text-slate-400">Contrato {{ invoice.contrato_id }}</p>
              </td>
              <td class="px-4 py-4">
                {{ formatDate(invoice.competencia) }}
              </td>
              <td class="px-4 py-4">
                {{ formatDate(invoice.vencimento) }}
              </td>
              <td class="px-4 py-4">
                <span
                  class="inline-flex items-center justify-center rounded-full px-3 py-1 text-xs font-semibold"
                  :class="statusBadgeClass(invoice.status)"
                >
                  {{ invoice.status || '—' }}
                </span>
                <p v-if="invoice.pago_em" class="mt-1 text-xs text-emerald-300">
                  Pago em {{ formatDate(invoice.pago_em) }}
                </p>
              </td>
              <td class="px-4 py-4 text-right font-semibold text-indigo-200">
                R$ {{ formatCurrency(invoice.valor_total) }}
              </td>
              <td class="px-4 py-4">
                <div class="flex flex-wrap gap-2">
                  <a
                    v-if="invoice.boleto?.pdf_url"
                    :href="invoice.boleto.pdf_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-2 rounded-md border border-indigo-500/40 px-3 py-1.5 text-xs font-semibold text-indigo-200 transition hover:bg-indigo-500/10"
                  >
                    Baixar boleto
                  </a>

                  <button
                    v-if="hasCopyableCode(invoice)"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md border border-amber-500/40 px-3 py-1.5 text-xs font-semibold text-amber-200 transition hover:bg-amber-500/10"
                    @click="copyCode(invoice)"
                  >
                    Copiar código
                  </button>

                    <a
                      v-if="invoice.receipt_url"
                      :href="invoice.receipt_url"
                      target="_blank"
                      rel="noopener"
                      class="inline-flex items-center gap-2 rounded-md border border-emerald-500/40 px-3 py-1.5 text-xs font-semibold text-emerald-200 transition hover:bg-emerald-500/10"
                    >
                      Recibo (PDF)
                    </a>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </PortalLayout>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import axios from '@/bootstrap';
import { usePortalInvoicesStore } from '@/Stores/portal/invoices';
import { route } from 'ziggy-js';

const invoicesStore = usePortalInvoicesStore();

const invoices = computed(() => invoicesStore.invoices);
const loading = computed(() => invoicesStore.loading);
const error = computed(() => invoicesStore.error);
const hasInvoices = computed(() => invoices.value.length > 0);

onMounted(async () => {
  if (invoicesStore.invoices.length === 0) {
    await fetchInvoices();
  }
});

async function fetchInvoices() {
  try {
    invoicesStore.setError(null);
    invoicesStore.setLoading(true);

    const endpoint = route('portal.invoices.index', undefined, false);
    const { data } = await axios.get(endpoint);

    invoicesStore.setInvoices(data?.data ?? []);
  } catch (err: any) {
    const message = err?.response?.data?.message ?? 'Não foi possível carregar as faturas.';
    invoicesStore.setError(message);
    notify('error', message);
  } finally {
    invoicesStore.setLoading(false);
  }
}

function notify(type: 'success' | 'error' | 'info', message: string) {
  window.dispatchEvent(
    new CustomEvent('notify', {
      detail: {
        type,
        message,
      },
    })
  );
}

function hasCopyableCode(invoice: (typeof invoices.value)[number]) {
  return Boolean(invoice.boleto?.linha_digitavel || invoice.boleto?.codigo_barras);
}

async function copyCode(invoice: (typeof invoices.value)[number]) {
  const code = invoice.boleto?.linha_digitavel || invoice.boleto?.codigo_barras;

  if (!code) {
    notify('error', 'Linha digitável não disponível para esta fatura.');
    return;
  }

  try {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      await navigator.clipboard.writeText(code);
    } else {
      fallbackCopy(code);
    }
    notify('success', 'Código copiado para a área de transferência.');
  } catch (error) {
    fallbackCopy(code);
    notify('info', 'Código copiado. Caso não apareça, selecione e copie manualmente.');
  }
}

function fallbackCopy(text: string) {
  if (typeof window === 'undefined') return;
  const textarea = document.createElement('textarea');
  textarea.value = text;
  textarea.style.position = 'fixed';
  textarea.style.opacity = '0';
  document.body.appendChild(textarea);
  textarea.select();
  document.execCommand('copy');
  document.body.removeChild(textarea);
}

function formatDate(date?: string | null) {
  if (!date) return '—';
  return new Date(date).toLocaleDateString('pt-BR');
}

function formatCurrency(value?: number | null) {
  if (typeof value !== 'number') return '0,00';
  return value.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
}

function statusBadgeClass(status?: string | null) {
  const normalized = (status || '').toLowerCase();
  if (normalized.includes('paga')) return 'bg-emerald-500/20 text-emerald-200 border border-emerald-500/40';
  if (normalized.includes('aberta')) return 'bg-amber-500/20 text-amber-200 border border-amber-500/40';
  if (normalized.includes('cancel')) return 'bg-rose-500/20 text-rose-200 border border-rose-500/40';
  return 'bg-slate-800/60 text-slate-200 border border-slate-700/40';
}
</script>
