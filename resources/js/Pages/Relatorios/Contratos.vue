<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DatePicker from '@/Components/Form/DatePicker.vue';
import axios from '@/bootstrap';
import { computed, onMounted, reactive, ref, watch } from 'vue';

type ContratoRow = {
  id: number;
  codigo: string;
  status: string;
  data_inicio: string | null;
  data_fim: string | null;
  proximo_reajuste: string | null;
  valor_aluguel: number;
  imovel?: {
    codigo?: string | null;
    cidade?: string | null;
    bairro?: string | null;
    complemento?: string | null;
    condominio?: string | null;
  } | null;
  locatario?: string | null;
};

type PaginationMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

const props = defineProps<{ canExport?: boolean }>();

const filters = reactive({
  onlyActive: false,
  dateField: 'inicio',
  dateStart: '',
  dateEnd: '',
  perPage: 50,
});

const contratos = ref<ContratoRow[]>([]);
const meta = ref<PaginationMeta | null>(null);
const loading = ref(false);
const errorMessage = ref('');
const exporting = ref(false);

const perPageOptions = [25, 50, 100, 150];
const dateFieldOptions = [
  { value: 'inicio', label: 'Data de início' },
  { value: 'fim', label: 'Data de término' },
];

const hasResults = computed(() => contratos.value.length > 0);

watch(
  () => filters.perPage,
  () => {
    fetchContratos(1);
  }
);

async function fetchContratos(page = 1) {
  loading.value = true;
  errorMessage.value = '';

  const params = buildQueryParams(page);

  try {
    const { data } = await axios.get('/api/reports/contracts', { params });
    contratos.value = data.data ?? [];
    meta.value = data.meta ?? null;
  } catch (error: any) {
    console.error(error);
    errorMessage.value = error?.response?.data?.message ?? 'Não foi possível carregar o relatório.';
  } finally {
    loading.value = false;
  }
}

function applyFilters() {
  fetchContratos(1);
}

function resetFilters() {
  filters.onlyActive = false;
  filters.dateField = 'inicio';
  filters.dateStart = '';
  filters.dateEnd = '';
  filters.perPage = 50;
  fetchContratos(1);
}

function changePage(page: number) {
  if (!meta.value) return;
  if (page < 1 || page > meta.value.last_page) return;
  fetchContratos(page);
}

function buildQueryParams(page?: number): Record<string, unknown> {
  const params: Record<string, unknown> = {
    per_page: filters.perPage,
  };

  if (typeof page === 'number') {
    params.page = page;
  }
  if (filters.onlyActive) {
    params.only_active = 1;
  }
  if (filters.dateField) {
    params.date_field = filters.dateField;
  }
  if (filters.dateStart) {
    params.date_start = filters.dateStart;
  }
  if (filters.dateEnd) {
    params.date_end = filters.dateEnd;
  }

  return params;
}

function formatMonthYear(value: string | null): string {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }
  return date.toLocaleDateString('pt-BR', {
    month: '2-digit',
    year: 'numeric',
  });
}

function formatImovelLabel(imovel: ContratoRow['imovel']): string {
  if (!imovel) return '—';
  const condominio = (imovel.condominio ?? '').trim();
  const complemento = (imovel.complemento ?? '').trim();
  const base = condominio !== '' ? condominio : 'Sem condomínio';
  return complemento !== '' ? `${base} — ${complemento}` : base;
}

function formatImovelInfo(imovel: ContratoRow['imovel']): string {
  if (!imovel) return '—';

  const parts: string[] = [];
  if (imovel.codigo) {
    parts.push(`Código ${imovel.codigo}`);
  }
  if (imovel.cidade) {
    parts.push(imovel.cidade);
  }
  if (imovel.bairro) {
    parts.push(imovel.bairro);
  }

  return parts.length ? parts.join(' • ') : '—';
}

async function exportPdf() {
  if (!props.canExport) return;

  exporting.value = true;

  try {
    const params = buildQueryParams();
    const { data } = await axios.get('/api/reports/contracts/export', {
      params,
      responseType: 'blob',
    });

    const blob = new Blob([data], { type: 'application/pdf' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    const date = new Date().toISOString().slice(0, 10);
    link.download = `relatorio-contratos-${date}.pdf`;
    link.click();
    window.URL.revokeObjectURL(url);
  } catch (error: any) {
    console.error(error);
    errorMessage.value = error?.response?.data?.message ?? 'Não foi possível exportar o relatório.';
  } finally {
    exporting.value = false;
  }
}

function formatCurrency(value: number): string {
  return Number(value ?? 0).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  });
}

const totalLabel = computed(() => {
  if (!meta.value) return '';
  return `${meta.value.total} contratos encontrados`;
});

onMounted(() => {
  fetchContratos();
});
</script>

<template>
  <AuthenticatedLayout title="Relatório de contratos">
    <div class="space-y-8 text-slate-100">
      <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-white">Relatório gerencial de contratos</h1>
          <p class="text-sm text-slate-400">
            Consulte contratos cadastrados com os principais dados operacionais.
          </p>
        </div>
      </header>

      <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
        <form class="grid gap-6 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="applyFilters">
          <div class="md:col-span-2">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
              Período do contrato
            </label>
            <div class="mt-2 flex flex-col gap-3 md:flex-row md:items-center">
              <select
                v-model="filters.dateField"
                class="w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 md:w-48"
              >
                <option v-for="option in dateFieldOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
              <div class="flex flex-1 items-center gap-3">
                <DatePicker v-model="filters.dateStart" placeholder="De" class="flex-1" />
                <DatePicker v-model="filters.dateEnd" placeholder="Até" class="flex-1" />
              </div>
            </div>
          </div>

          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
              Status
            </label>
            <div class="mt-3 flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-900/50 px-4 py-3 text-sm">
              <input id="filter-only-active" v-model="filters.onlyActive" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-800 text-indigo-500 focus:ring-0" />
              <label class="text-slate-200" for="filter-only-active">Mostrar apenas contratos ativos</label>
            </div>
          </div>

          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Registros por página</label>
            <select
              v-model.number="filters.perPage"
              class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            >
              <option v-for="option in perPageOptions" :key="option" :value="option">
                {{ option }}
              </option>
            </select>
          </div>

          <div class="md:col-span-2 xl:col-span-4 flex flex-wrap items-center gap-3">
            <button
              type="submit"
              class="rounded-xl border border-indigo-500/40 bg-indigo-600/80 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500/80 disabled:opacity-60"
              :disabled="loading"
            >
              Aplicar filtros
            </button>
            <button
              type="button"
              class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800/60"
              :disabled="loading"
              @click="resetFilters"
            >
              Limpar
            </button>
          </div>
        </form>
      </section>

      <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-sm font-semibold text-white">Contratos selecionados</p>
            <p class="text-xs text-slate-400">{{ totalLabel || '&nbsp;' }}</p>
          </div>
          <div class="text-xs text-slate-500">
            Atualize os filtros para refinar o resultado ou exporte o PDF para compartilhar o relatório.
          </div>
          <div v-if="props.canExport" class="flex items-center gap-3 sm:ml-auto">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl border border-amber-500/40 bg-amber-500/20 px-4 py-2 text-sm font-semibold text-amber-100 transition hover:bg-amber-500/30 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="exporting || loading || !hasResults"
              @click="exportPdf"
            >
              <span>{{ exporting ? 'Gerando PDF...' : 'Exportar PDF' }}</span>
            </button>
          </div>
        </div>

        <div v-if="errorMessage" class="mb-4 rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
          {{ errorMessage }}
        </div>

        <div
          v-if="loading"
          class="rounded-xl border border-slate-800 bg-slate-950/40 px-4 py-6 text-center text-sm text-slate-400"
        >
          Carregando contratos...
        </div>
        <div
          v-else-if="!hasResults"
          class="rounded-xl border border-dashed border-slate-800 bg-slate-950/40 px-4 py-10 text-center text-sm text-slate-400"
        >
          Nenhum contrato encontrado com os filtros selecionados.
        </div>
        <div v-else class="overflow-x-auto rounded-xl border border-slate-800">
          <table class="min-w-[960px] divide-y divide-slate-800 text-sm">
            <thead class="bg-slate-900/80 text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th class="px-4 py-3 text-left">Código</th>
                <th class="px-4 py-3 text-left">Imóvel</th>
                <th class="px-4 py-3 text-left">Locatário</th>
                <th class="px-4 py-3 text-left">Início</th>
                <th class="px-4 py-3 text-left">Fim</th>
                <th class="px-4 py-3 text-left">Reajuste</th>
                <th class="px-4 py-3 text-left">Valor aluguel</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800 bg-slate-950/30 text-slate-200">
              <tr v-for="row in contratos" :key="row.id" class="hover:bg-slate-900/50">
                <td class="px-4 py-3 font-semibold text-white">{{ row.codigo }}</td>
                <td class="px-4 py-3">
                  <div>{{ formatImovelLabel(row.imovel) }}</div>
                  <div class="text-xs text-slate-500">
                    {{ formatImovelInfo(row.imovel) }}
                  </div>
                </td>
                <td class="px-4 py-3 text-slate-200">{{ row.locatario ?? '—' }}</td>
                <td class="px-4 py-3">{{ formatMonthYear(row.data_inicio) }}</td>
                <td class="px-4 py-3">{{ formatMonthYear(row.data_fim) }}</td>
                <td class="px-4 py-3">{{ formatMonthYear(row.proximo_reajuste) }}</td>
                <td class="px-4 py-3 font-semibold text-emerald-200">{{ formatCurrency(row.valor_aluguel) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          v-if="meta && hasResults"
          class="mt-4 flex flex-col items-center justify-between gap-3 rounded-xl border border-slate-800 bg-slate-950/50 px-4 py-4 text-sm text-slate-300 md:flex-row"
        >
          <div>Mostrando página {{ meta.current_page }} de {{ meta.last_page }}</div>
          <div class="flex items-center gap-2">
            <button
              class="rounded-lg border border-slate-700 px-3 py-2 transition hover:bg-slate-800/70 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="loading || meta.current_page <= 1"
              @click="changePage(meta.current_page - 1)"
            >
              Anterior
            </button>
            <button
              class="rounded-lg border border-slate-700 px-3 py-2 transition hover:bg-slate-800/70 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="loading || meta.current_page >= meta.last_page"
              @click="changePage(meta.current_page + 1)"
            >
              Próxima
            </button>
          </div>
        </div>
      </section>
    </div>
  </AuthenticatedLayout>
</template>
