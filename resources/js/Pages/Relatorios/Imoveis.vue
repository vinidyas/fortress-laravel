<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from '@/bootstrap';
import { computed, onMounted, reactive, ref, watch } from 'vue';

type ImovelRow = {
  id: number;
  label: string;
  info: string;
  tipo: string | null;
  cidade: string | null;
  valor_locacao: number;
  dormitorios: number;
  vagas: number;
  disponibilidade: string | null;
  area_total: number | null;
};

type PaginationMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

const props = defineProps<{ canExport?: boolean }>();

const filters = reactive({
  onlyAvailable: false,
  perPage: 50,
});

const imoveis = ref<ImovelRow[]>([]);
const meta = ref<PaginationMeta | null>(null);
const loading = ref(false);
const errorMessage = ref('');
const exporting = ref(false);

const perPageOptions = [25, 50, 100, 150];
const hasResults = computed(() => imoveis.value.length > 0);

watch(
  () => filters.perPage,
  () => {
    fetchImoveis(1);
  }
);

async function fetchImoveis(page = 1) {
  loading.value = true;
  errorMessage.value = '';

  try {
    const { data } = await axios.get('/api/reports/properties', {
      params: {
        page,
        per_page: filters.perPage,
        only_available: filters.onlyAvailable ? 1 : undefined,
      },
    });
    imoveis.value = data.data ?? [];
    meta.value = data.meta ?? null;
  } catch (error: any) {
    console.error(error);
    errorMessage.value = error?.response?.data?.message ?? 'Não foi possível carregar os imóveis.';
  } finally {
    loading.value = false;
  }
}

function applyFilters() {
  fetchImoveis(1);
}

function resetFilters() {
  filters.onlyAvailable = false;
  filters.perPage = 50;
  fetchImoveis(1);
}

function changePage(page: number) {
  if (!meta.value) return;
  if (page < 1 || page > meta.value.last_page) return;
  fetchImoveis(page);
}

function formatCurrency(value: number): string {
  return Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatArea(value: number | null): string {
  if (!value) return '—';
  return `${Number(value).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} m²`;
}

const totalLabel = computed(() => {
  if (!meta.value) return '';
  return `${meta.value.total} imóveis encontrados`;
});

async function exportPdf() {
  if (!props.canExport) return;
  exporting.value = true;

  try {
    const { data } = await axios.get('/api/reports/properties/export', {
      params: {
        only_available: filters.onlyAvailable ? 1 : undefined,
      },
      responseType: 'blob',
    });

    const blob = new Blob([data], { type: 'application/pdf' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `relatorio-imoveis-${new Date().toISOString().slice(0, 10)}.pdf`;
    link.click();
    window.URL.revokeObjectURL(url);
  } catch (error: any) {
    console.error(error);
    errorMessage.value = error?.response?.data?.message ?? 'Não foi possível exportar o relatório.';
  } finally {
    exporting.value = false;
  }
}

onMounted(() => {
  fetchImoveis();
});
</script>

<template>
  <AuthenticatedLayout title="Relatório de imóveis">
    <div class="space-y-8 text-slate-100">
      <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-white">Relatório gerencial de imóveis</h1>
          <p class="text-sm text-slate-400">
            Consulte rapidamente os imóveis cadastrados e filtre por disponibilidade.
          </p>
        </div>
      </header>

      <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
        <form class="grid gap-6 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="applyFilters">
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
              Status
            </label>
            <div class="mt-3 flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-900/50 px-4 py-3 text-sm">
              <input
                id="filter-only-available"
                v-model="filters.onlyAvailable"
                type="checkbox"
                class="h-4 w-4 rounded border-slate-600 bg-slate-800 text-indigo-500 focus:ring-0"
              />
              <label class="text-slate-200" for="filter-only-available">Mostrar apenas disponíveis</label>
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
            <p class="text-sm font-semibold text-white">Imóveis selecionados</p>
            <p class="text-xs text-slate-400">{{ totalLabel || '&nbsp;' }}</p>
          </div>
          <div class="text-xs text-slate-500">
            Atualize os filtros ou exporte o PDF com os dados atuais.
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
          Carregando imóveis...
        </div>
        <div
          v-else-if="!hasResults"
          class="rounded-xl border border-dashed border-slate-800 bg-slate-950/40 px-4 py-10 text-center text-sm text-slate-400"
        >
          Nenhum imóvel encontrado com os filtros selecionados.
        </div>
        <div v-else class="overflow-x-auto rounded-xl border border-slate-800">
          <table class="min-w-[960px] divide-y divide-slate-800 text-sm">
            <thead class="bg-slate-900/80 text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th class="px-4 py-3 text-left">Imóvel</th>
                <th class="px-4 py-3 text-left">Tipo</th>
                <th class="px-4 py-3 text-left">Cidade</th>
                <th class="px-4 py-3 text-left">Valor locação</th>
                <th class="px-4 py-3 text-left">Dorms</th>
                <th class="px-4 py-3 text-left">Vagas</th>
                <th class="px-4 py-3 text-left">Disponibilidade</th>
                <th class="px-4 py-3 text-left">Área total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800 bg-slate-950/30 text-slate-200">
              <tr v-for="row in imoveis" :key="row.id" class="hover:bg-slate-900/50">
                <td class="px-4 py-3">
                  <div class="font-semibold text-white">{{ row.label }}</div>
                  <div class="text-xs text-slate-500">{{ row.info }}</div>
                </td>
                <td class="px-4 py-3">{{ row.tipo ?? '—' }}</td>
                <td class="px-4 py-3">{{ row.cidade ?? '—' }}</td>
                <td class="px-4 py-3 font-semibold text-emerald-200">{{ formatCurrency(row.valor_locacao) }}</td>
                <td class="px-4 py-3">{{ row.dormitorios ?? 0 }}</td>
                <td class="px-4 py-3">{{ row.vagas ?? 0 }}</td>
                <td class="px-4 py-3">{{ row.disponibilidade ?? '—' }}</td>
                <td class="px-4 py-3">{{ formatArea(row.area_total) }}</td>
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
