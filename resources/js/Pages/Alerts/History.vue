<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ResolveAlertModal from '@/Components/Alerts/ResolveAlertModal.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { computed, reactive, ref } from 'vue';
import { useNotificationStore } from '@/Stores/notifications';
import { route } from 'ziggy-js';
import DatePicker from '@/Components/Form/DatePicker.vue';

interface AlertRow {
  id: number;
  key: string;
  category: string;
  severity: 'danger' | 'warning' | 'info' | string;
  title: string;
  message: string;
  resource: { type: string | null; id: number | null } | null;
  payload: Record<string, unknown>;
  occurred_at: string | null;
  resolved_at: string | null;
  resolved_by: { id: number; name: string; username: string } | null;
  resolution_notes: string | null;
}

const props = defineProps<{
  alerts: { data: AlertRow[]; meta?: Record<string, any>; links?: Array<{ url: string | null; label: string; active: boolean }> };
  filters: {
    status: string;
    category?: string | null;
    severity?: string | null;
    search?: string | null;
    date_from?: string | null;
    date_to?: string | null;
    per_page?: number;
  };
  categories: string[];
  severities: string[];
  canResolve: boolean;
}>();

const notificationStore = useNotificationStore();

const alerts = computed(() => props.alerts?.data ?? []);
const meta = computed(() => props.alerts?.meta ?? {});
const links = computed(() => props.alerts?.links ?? []);

const localFilters = reactive({
  status: props.filters.status ?? 'open',
  category: props.filters.category ?? '',
  severity: props.filters.severity ?? '',
  search: props.filters.search ?? '',
  date_from: props.filters.date_from ?? '',
  date_to: props.filters.date_to ?? '',
  per_page: props.filters.per_page ?? 15,
});

const statusOptions = [
  { value: 'open', label: 'Pendentes' },
  { value: 'resolved', label: 'Tratados' },
  { value: 'all', label: 'Todos' },
];

const severityLabel = (severity: string) => {
  switch (severity) {
    case 'danger':
      return 'Crítico';
    case 'warning':
      return 'Atenção';
    default:
      return 'Informativo';
  }
};

const severityClasses = (severity: string) => {
  switch (severity) {
    case 'danger':
      return 'bg-rose-500/15 text-rose-300 border border-rose-500/30';
    case 'warning':
      return 'bg-amber-400/15 text-amber-200 border border-amber-400/30';
    default:
      return 'bg-indigo-500/15 text-indigo-200 border border-indigo-500/30';
  }
};

const categoryLabel = (category: string) => {
  switch (category) {
    case 'contract.expiring':
      return 'Contrato próximo do vencimento';
    case 'invoice.overdue':
      return 'Fatura em atraso';
    case 'invoice.due_soon':
      return 'Fatura a vencer';
    default:
      return category;
  }
};

const formatDateTime = (value: string | null) =>
  value ? new Date(value).toLocaleString('pt-BR') : '-';

const applyFilters = () => {
  router.get(
    route('alerts.history'),
    { ...localFilters },
    { preserveState: true, preserveScroll: true, replace: true }
  );
};

const resetFilters = () => {
  localFilters.status = 'open';
  localFilters.category = '';
  localFilters.severity = '';
  localFilters.search = '';
  localFilters.date_from = '';
  localFilters.date_to = '';
  localFilters.per_page = 15;
  applyFilters();
};

const visitLink = (link: { url: string | null }) => {
  if (!link.url) return;
  router.visit(link.url, { preserveState: true, preserveScroll: true });
};

const selectedAlert = ref<AlertRow | null>(null);
const showResolveModal = ref(false);
const resolving = ref(false);

const openResolveModal = (alert: AlertRow) => {
  selectedAlert.value = alert;
  showResolveModal.value = true;
};

const closeResolveModal = () => {
  if (resolving.value) return;
  showResolveModal.value = false;
  selectedAlert.value = null;
};

const confirmResolution = async (notes: string) => {
  if (!selectedAlert.value) {
    closeResolveModal();
    return;
  }

  try {
    resolving.value = true;
    await axios.post(`/api/alerts/history/${selectedAlert.value.id}/resolve`, {
      notes: notes || undefined,
    });
    notificationStore.success('Alerta marcado como tratado.');
    closeResolveModal();
    router.reload({ only: ['alerts'] });
  } catch (error: any) {
    const message =
      error?.response?.data?.message ?? 'Não foi possível tratar o alerta. Tente novamente.';
    notificationStore.error(message);
  } finally {
    resolving.value = false;
  }
};

const resourceLink = (alert: AlertRow) => {
  if (!alert.resource || !alert.resource.id) return null;
  switch (alert.resource.type) {
    case 'App\\Models\\Contrato':
      return route('contratos.show', alert.resource.id);
    case 'App\\Models\\Fatura':
      return route('faturas.show', alert.resource.id);
    default:
      return null;
  }
};

const resourceLabel = (alert: AlertRow) => {
  if (!alert.resource || !alert.resource.id) return '-';
  switch (alert.resource.type) {
    case 'App\\Models\\Contrato':
      return `Contrato #${alert.resource.id}`;
    case 'App\\Models\\Fatura':
      return `Fatura #${alert.resource.id}`;
    default:
      return `${alert.resource.type ?? 'Recurso'} #${alert.resource.id}`;
  }
};

const formatPayload = (payload: Record<string, unknown>) => {
  if (!payload || Object.keys(payload).length === 0) return '';

  return Object.entries(payload)
    .filter(([, value]) => value !== null && value !== '')
    .map(([key, value]) => `${key}: ${value}`)
    .join(' · ');
};
</script>

<template>
  <AuthenticatedLayout title="Histórico de alertas">
    <Head title="Histórico de alertas" />

    <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
      <header class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-lg font-semibold text-white">Histórico de alertas</h1>
          <p class="text-sm text-slate-400">
            Consulte alertas gerados automaticamente e registre o tratamento realizado para fins de compliance.
          </p>
        </div>
      </header>

      <form class="grid gap-4 md:grid-cols-6" @submit.prevent="applyFilters">
        <div>
          <label class="text-xs font-semibold uppercase text-slate-400">Status</label>
          <select
            v-model="localFilters.status"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
          >
            <option v-for="option in statusOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold uppercase text-slate-400">Categoria</label>
          <select
            v-model="localFilters.category"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
          >
            <option value="">Todas</option>
            <option v-for="category in props.categories" :key="category" :value="category">
              {{ categoryLabel(category) }}
            </option>
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold uppercase text-slate-400">Severidade</label>
          <select
            v-model="localFilters.severity"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
          >
            <option value="">Todas</option>
            <option v-for="severity in props.severities" :key="severity" :value="severity">
              {{ severityLabel(severity) }}
            </option>
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="text-xs font-semibold uppercase text-slate-400">Busca</label>
          <input
            v-model="localFilters.search"
            type="search"
            placeholder="Título, mensagem ou chave"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
          />
        </div>
        <div>
          <label class="text-xs font-semibold uppercase text-slate-400">Por página</label>
          <select
            v-model.number="localFilters.per_page"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none"
          >
            <option :value="15">15</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold uppercase text-slate-400">Data inicial</label>
          <DatePicker v-model="localFilters.date_from" placeholder="dd/mm/aaaa" />
        </div>
        <div>
          <label class="text-xs font-semibold uppercase text-slate-400">Data final</label>
          <DatePicker v-model="localFilters.date_to" placeholder="dd/mm/aaaa" />
        </div>
        <div class="md:col-span-2 flex items-end gap-2">
          <button
            type="submit"
            class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow transition hover:bg-indigo-500"
          >
            Aplicar filtros
          </button>
          <button
            type="button"
            class="inline-flex items-center rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-200 transition hover:bg-slate-800"
            @click="resetFilters"
          >
            Limpar
          </button>
        </div>
      </form>

      <div class="mt-6 overflow-hidden rounded-xl border border-slate-800">
        <table class="min-w-full divide-y divide-slate-800 text-sm text-slate-100">
          <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
            <tr>
              <th class="px-4 py-3 text-left">Alerta</th>
              <th class="px-4 py-3 text-left">Categoria</th>
              <th class="px-4 py-3 text-left">Recurso</th>
              <th class="px-4 py-3 text-left">Gerado em</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-right">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800 bg-slate-950/40">
            <tr v-if="!alerts.length">
              <td colspan="6" class="px-4 py-6 text-center text-slate-400">
                Nenhum alerta encontrado com os filtros atuais.
              </td>
            </tr>
            <tr v-for="alert in alerts" :key="alert.id" class="hover:bg-slate-900/60">
              <td class="px-4 py-4">
                <p class="font-semibold text-white">{{ alert.title }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ alert.message }}</p>
              </td>
              <td class="px-4 py-4 text-xs">
                <span :class="['inline-flex items-center gap-2 rounded-full px-3 py-1 font-semibold', severityClasses(alert.severity)]">
                  <span class="h-1.5 w-1.5 rounded-full bg-current" />
                  {{ categoryLabel(alert.category) }}
                </span>
              </td>
              <td class="px-4 py-4 text-xs text-slate-300">
                <div class="flex flex-col gap-1">
                  <span>{{ resourceLabel(alert) }}</span>
                  <span v-if="alert.payload && Object.keys(alert.payload).length" class="text-[0.65rem] text-slate-500">
                    {{ formatPayload(alert.payload) }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-4 text-xs text-slate-300">
                <div class="flex flex-col">
                  <span>{{ formatDateTime(alert.occurred_at) }}</span>
                  <span v-if="alert.resolved_at" class="text-emerald-300">
                    Tratado {{ formatDateTime(alert.resolved_at) }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-4 text-xs text-slate-300">
                <div class="flex flex-col gap-1">
                  <span
                    :class="[
                      'inline-flex items-center gap-2 rounded-full px-3 py-1 font-semibold',
                      alert.resolved_at ? 'bg-emerald-500/15 text-emerald-200 border border-emerald-500/30' : 'bg-sky-500/15 text-sky-200 border border-sky-500/30',
                    ]"
                  >
                    <span class="h-1.5 w-1.5 rounded-full bg-current" />
                    {{ alert.resolved_at ? 'Tratado' : 'Pendente' }}
                  </span>
                  <span v-if="alert.resolved_by" class="text-[0.65rem] text-slate-500">
                    por {{ alert.resolved_by.name }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-4 text-right text-xs">
                <div class="flex items-center justify-end gap-2">
                  <a
                    v-if="resourceLink(alert)"
                    :href="resourceLink(alert) ?? '#'"
                    class="rounded-lg border border-slate-700 px-3 py-1 text-slate-200 transition hover:bg-slate-800"
                  >
                    Abrir recurso
                  </a>
                  <button
                    v-if="props.canResolve && !alert.resolved_at"
                    type="button"
                    class="rounded-lg border border-emerald-500/40 px-3 py-1 text-emerald-300 transition hover:border-emerald-400 hover:text-emerald-100"
                    @click="openResolveModal(alert)"
                  >
                    Tratar alerta
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer v-if="links.length > 1" class="mt-4 flex flex-wrap items-center justify-center gap-2 text-xs">
        <button
          v-for="link in links"
          :key="link.label"
          type="button"
          class="rounded-md px-3 py-1 transition"
          :class="
            link.active
              ? 'bg-indigo-600 text-white'
              : link.url
                ? 'text-slate-300 hover:bg-slate-800'
                : 'text-slate-600 cursor-default'
          "
          v-html="link.label"
          @click="visitLink(link)"
        />
      </footer>
    </section>

    <ResolveAlertModal
      :show="showResolveModal"
      :alert="selectedAlert"
      :processing="resolving"
      @close="closeResolveModal"
      @confirm="confirmResolution"
    />
  </AuthenticatedLayout>
</template>
