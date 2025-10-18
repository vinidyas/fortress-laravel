<script setup lang="ts">
import DatePicker from '@/Components/Form/DatePicker.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { route } from 'ziggy-js';

type AuditLogUser = { id: number; nome: string | null; username: string };

type AuditLogRow = {
  id: number;
  action: string;
  created_at: string | null;
  user: AuditLogUser | null;
  auditable_type: string | null;
  auditable_id: number | null;
  payload: Record<string, unknown> | null;
  ip_address: string | null;
  user_agent: string | null;
  context: Record<string, unknown> | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
  logs: {
    data: AuditLogRow[];
    links: PaginationLink[];
    meta?: {
      total?: number;
      per_page?: number;
      current_page?: number;
      last_page?: number;
      from?: number;
      to?: number;
    };
  };
  filters: {
    action?: string | null;
    user_id?: number | null;
    auditable_type?: string | null;
    auditable_id?: number | null;
    ip_address?: string | null;
    guard?: string | null;
    origin?: string | null;
    http_method?: string | null;
    date_from?: string | null;
    date_to?: string | null;
    search?: string | null;
    per_page?: number;
  };
  actions: string[];
  resourceTypes: string[];
  guards: string[];
  origins: string[];
  requestMethods: string[];
  users: AuditLogUser[];
  canExport: boolean;
}>();

const logs = computed(() => props.logs?.data ?? []);
const links = computed(() => props.logs?.links ?? []);
const meta = computed(() => props.logs?.meta ?? {});
const totalRows = computed(() => meta.value?.total ?? logs.value.length);
const showingFrom = computed(() => meta.value?.from ?? 0);
const showingTo = computed(() => meta.value?.to ?? logs.value.length);

const defaultPerPage = props.filters.per_page ?? 25;

const localFilters = reactive({
  action: props.filters.action ?? '',
  user_id: props.filters.user_id ? String(props.filters.user_id) : '',
  auditable_type: props.filters.auditable_type ?? '',
  auditable_id: props.filters.auditable_id ? String(props.filters.auditable_id) : '',
  ip_address: props.filters.ip_address ?? '',
  guard: props.filters.guard ?? '',
  origin: props.filters.origin ?? '',
  http_method: props.filters.http_method ?? '',
  date_from: props.filters.date_from ?? '',
  date_to: props.filters.date_to ?? '',
  search: props.filters.search ?? '',
  per_page: String(defaultPerPage),
});

watch(
  () => props.filters,
  (next) => {
    localFilters.action = next.action ?? '';
    localFilters.user_id = next.user_id ? String(next.user_id) : '';
    localFilters.auditable_type = next.auditable_type ?? '';
    localFilters.auditable_id = next.auditable_id ? String(next.auditable_id) : '';
    localFilters.ip_address = next.ip_address ?? '';
    localFilters.guard = next.guard ?? '';
    localFilters.origin = next.origin ?? '';
    localFilters.http_method = next.http_method ?? '';
    localFilters.date_from = next.date_from ?? '';
    localFilters.date_to = next.date_to ?? '';
    localFilters.search = next.search ?? '';
    localFilters.per_page = String(next.per_page ?? defaultPerPage);
  },
  { deep: true }
);

const hasLogs = computed(() => logs.value.length > 0);
const isResourceScoped = computed(
  () => Boolean(localFilters.auditable_type) || Boolean(localFilters.auditable_id)
);

const groupedLogs = computed(() => {
  if (!isResourceScoped.value) {
    return [];
  }

  const groups: Record<string, { key: string; label: string; logs: AuditLogRow[] }> = {};

  logs.value.forEach((log) => {
    const type = log.auditable_type ?? 'Recurso';
    const id = log.auditable_id ?? '-';
    const key = `${type}#${id}`;
    const label = `${friendlyModelName(type)} #${id}`;

    if (!groups[key]) {
      groups[key] = { key, label, logs: [] };
    }

    groups[key].logs.push(log);
  });

  return Object.values(groups).map((group) => ({
    ...group,
    logs: group.logs.sort(
      (a, b) => new Date(b.created_at ?? '').getTime() - new Date(a.created_at ?? '').getTime()
    ),
  }));
});

const selectedLog = ref<AuditLogRow | null>(null);
const showRawPayload = ref(false);

const exportUrl = (format: 'csv' | 'json') => {
  const params = new URLSearchParams();
  const payload = buildQueryParams(format);

  Object.entries(payload).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      params.append(key, String(value));
    }
  });

  const qs = params.toString();
  return `${route('auditoria.export')}${qs ? `?${qs}` : ''}`;
};

const buildQueryParams = (format?: 'csv' | 'json') => {
  const payload: Record<string, string | number> = {};
  Object.entries(localFilters).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      payload[key] = value;
    }
  });

  if (format) {
    payload.format = format;
  }

  return payload;
};

const applyFilters = () => {
  const query: Record<string, string> = {};

  Object.entries(localFilters).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      query[key] = value;
    }
  });

  router.get(route('auditoria.index'), query, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  Object.assign(localFilters, {
    action: '',
    user_id: '',
    auditable_type: '',
    auditable_id: '',
    ip_address: '',
    guard: '',
    origin: '',
    http_method: '',
    date_from: '',
    date_to: '',
    search: '',
    per_page: String(defaultPerPage),
  });
  applyFilters();
};

const handlePagination = (link: PaginationLink) => {
  if (!link.url) return;
  router.visit(link.url, { preserveState: true, preserveScroll: true });
};

const formatDateTime = (value: string | null): string => {
  if (!value) return '-';
  return new Date(value).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const relativeFormatter = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' });

const formatRelativeTime = (value: string | null): string => {
  if (!value) return '';
  const target = new Date(value).getTime();
  if (Number.isNaN(target)) return '';

  const now = Date.now();
  const diff = target - now;

  const units = [
    { divisor: 1000 * 60 * 60 * 24 * 30, unit: 'month' as const },
    { divisor: 1000 * 60 * 60 * 24, unit: 'day' as const },
    { divisor: 1000 * 60 * 60, unit: 'hour' as const },
    { divisor: 1000 * 60, unit: 'minute' as const },
  ];

  for (const { divisor, unit } of units) {
    const delta = diff / divisor;
    if (Math.abs(delta) >= 1) {
      return relativeFormatter.format(Math.round(delta), unit);
    }
  }

  const seconds = Math.round(diff / 1000);
  return relativeFormatter.format(seconds, 'second');
};

const friendlyModelName = (value: string | null): string => {
  if (!value) return 'Recurso';
  const base = value.split('\\').pop() ?? value;
  return base.replace(/([a-z])([A-Z])/g, '$1 $2');
};

const resourceLabel = (log: AuditLogRow): string => {
  if (!log.auditable_type) {
    return '-';
  }

  const base = friendlyModelName(log.auditable_type);
  const id = log.auditable_id ?? '-';
  return `${base} #${id}`;
};

const resourceLink = (log: AuditLogRow): string | null => {
  if (!log.auditable_type || !log.auditable_id) return null;

  switch (log.auditable_type) {
    case 'App\\Models\\Contrato':
      return route('contratos.show', log.auditable_id);
    case 'App\\Models\\Fatura':
      return route('faturas.show', log.auditable_id);
    case 'App\\Models\\Imovel':
      return route('imoveis.edit', { imovel: log.auditable_id });
    case 'App\\Models\\Pessoa':
      return route('pessoas.edit', { pessoa: log.auditable_id });
    default:
      return null;
  }
};

const friendlyOrigin = (log: AuditLogRow): string => {
  const origin = (log.context?.origin as string | undefined) ?? null;
  return origin ?? (log.context?.guard ? log.context.guard.toString() : '—');
};

const friendlyMethod = (log: AuditLogRow): string => {
  return (log.context?.http_method as string | undefined) ?? '—';
};

const summarizePayload = (payload: Record<string, unknown> | null | undefined): string => {
  if (!payload || Object.keys(payload).length === 0) {
    return '—';
  }

  const before = payload.before as Record<string, unknown> | undefined;
  const after = payload.after as Record<string, unknown> | undefined;

  if (before || after) {
    const keys = new Set<string>([
      ...(before ? Object.keys(before) : []),
      ...(after ? Object.keys(after) : []),
    ]);
    const entries = Array.from(keys)
      .slice(0, 4)
      .map((key) => {
        const nextValue = after && key in after ? after[key] : before ? before[key] : null;
        return `${key}: ${stringifyValue(nextValue)}`;
      });

    return entries.join(' · ');
  }

  return Object.entries(payload)
    .filter(([, value]) => value !== null && value !== '')
    .slice(0, 4)
    .map(([key, value]) => `${key}: ${stringifyValue(value)}`)
    .join(' · ');
};

const stringifyValue = (value: unknown): string => {
  if (value === null || value === undefined) return '—';
  if (Array.isArray(value)) return `[${value.length} itens]`;
  if (typeof value === 'object') return '[dados]';
  const string = String(value);
  return string.length > 40 ? `${string.slice(0, 37)}...` : string;
};

const openDetails = (log: AuditLogRow) => {
  selectedLog.value = log;
  showRawPayload.value = false;
};

const closeDetails = () => {
  selectedLog.value = null;
  showRawPayload.value = false;
};

const detailedChanges = computed(() => {
  if (!selectedLog.value?.payload) return [];

  const before = (selectedLog.value.payload.before ?? {}) as Record<string, unknown>;
  const after = (selectedLog.value.payload.after ?? {}) as Record<string, unknown>;
  const keys = new Set([...Object.keys(before), ...Object.keys(after)]);

  return Array.from(keys).map((key) => ({
    key,
    before: before[key],
    after: after[key],
  }));
});

const prettyPayload = computed(() => {
  if (!selectedLog.value?.payload) {
    return '{}';
  }

  try {
    return JSON.stringify(selectedLog.value.payload, null, 2);
  } catch (error) {
    return String(selectedLog.value.payload);
  }
});
</script>

<template>
  <AuthenticatedLayout title="Logs de Auditoria">
    <Head title="Logs de Auditoria" />

    <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
      <header class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 class="text-lg font-semibold text-white">Logs de auditoria</h1>
          <p class="text-sm text-slate-400">
            Acompanhe as ações executadas pelos usuários e detalhe alterações de dados.
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <a
            v-if="canExport"
            :href="exportUrl('csv')"
            class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-200 transition hover:bg-slate-800"
          >
            Exportar CSV
          </a>
          <a
            v-if="canExport"
            :href="exportUrl('json')"
            class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-200 transition hover:bg-slate-800"
          >
            Exportar JSON
          </a>
        </div>
      </header>

      <form class="mb-6 space-y-4 rounded-2xl border border-slate-800 bg-slate-950/60 p-4" @submit.prevent="applyFilters">
        <div class="grid gap-3 md:grid-cols-4">
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Usuário</label>
            <select v-model="localFilters.user_id" class="rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
              <option value="">Todos</option>
              <option v-for="user in users" :key="user.id" :value="String(user.id)">
                {{ user.nome ?? user.username }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Ação</label>
            <select v-model="localFilters.action" class="rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
              <option value="">Todas</option>
              <option v-for="action in actions" :key="action" :value="action">
                {{ action }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Modelo</label>
            <select v-model="localFilters.auditable_type" class="rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
              <option value="">Todos</option>
              <option v-for="type in resourceTypes" :key="type" :value="type">
                {{ friendlyModelName(type) }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">ID do recurso</label>
            <input
              v-model="localFilters.auditable_id"
              type="number"
              min="0"
              class="rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
              placeholder="Opcional"
            />
          </div>
        </div>

        <div class="grid gap-3 md:grid-cols-4">
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Origem</label>
            <select v-model="localFilters.origin" class="rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
              <option value="">Todas</option>
              <option v-for="origin in origins" :key="origin" :value="origin">
                {{ origin }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Guard</label>
            <select v-model="localFilters.guard" class="rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
              <option value="">Todos</option>
              <option v-for="guard in guards" :key="guard" :value="guard">
                {{ guard }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Método HTTP</label>
            <select v-model="localFilters.http_method" class="rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
              <option value="">Todos</option>
              <option v-for="method in requestMethods" :key="method" :value="method">
                {{ method }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">IP</label>
            <input
              v-model="localFilters.ip_address"
              type="text"
              class="rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
              placeholder="192.168..."
            />
          </div>
        </div>

        <div class="grid gap-3 md:grid-cols-4">
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Data inicial</label>
            <DatePicker v-model="localFilters.date_from" class="w-full" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Data final</label>
            <DatePicker v-model="localFilters.date_to" class="w-full" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Busca</label>
            <input
              v-model="localFilters.search"
              type="text"
              class="rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
              placeholder="Texto livre"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Itens por página</label>
            <select v-model="localFilters.per_page" class="rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
              <option value="15">15</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
          </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3 pt-2">
          <button
            type="button"
            class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:bg-slate-800"
            @click="resetFilters"
          >
            Limpar filtros
          </button>
          <button
            type="submit"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500"
          >
            Aplicar filtros
          </button>
        </div>
      </form>

      <div class="mb-3 flex flex-wrap items-center justify-between gap-2 text-sm text-slate-400">
        <p>
          Exibindo
          <span class="font-semibold text-slate-200">{{ showingFrom }}</span>
          -
          <span class="font-semibold text-slate-200">{{ showingTo }}</span>
          de
          <span class="font-semibold text-slate-200">{{ totalRows }}</span>
          registros.
        </p>
        <p v-if="isResourceScoped" class="text-xs font-medium uppercase tracking-wide text-emerald-300">
          Visualização agrupada por recurso habilitada.
        </p>
      </div>

      <div class="overflow-hidden rounded-xl border border-slate-800">
        <table class="min-w-full divide-y divide-slate-800 text-sm text-slate-100">
          <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
            <tr>
              <th class="px-4 py-3 text-left">Usuário</th>
              <th class="px-4 py-3 text-left">Ação</th>
              <th class="px-4 py-3 text-left">Recurso</th>
              <th class="px-4 py-3 text-left">Origem</th>
              <th class="px-4 py-3 text-left">IP</th>
              <th class="px-4 py-3 text-left">Quando</th>
              <th class="px-4 py-3 text-left">Resumo</th>
              <th class="px-4 py-3 text-right">Detalhes</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-if="!hasLogs" class="text-slate-400">
              <td colspan="8" class="px-4 py-6 text-center">Nenhum log encontrado.</td>
            </tr>
            <tr v-else v-for="log in logs" :key="log.id" class="hover:bg-slate-900/40">
              <td class="px-4 py-3">
                <div class="font-medium text-slate-200">{{ log.user?.nome ?? log.user?.username ?? 'Sistema' }}</div>
                <div v-if="log.user?.username" class="text-xs text-slate-500">@{{ log.user.username }}</div>
              </td>
              <td class="px-4 py-3">
                <div class="font-medium text-slate-200">{{ log.action }}</div>
                <div v-if="log.context?.http_method" class="text-xs text-slate-400">
                  Método: {{ friendlyMethod(log) }}
                </div>
              </td>
              <td class="px-4 py-3">
                <div class="font-medium text-slate-200">
                  <template v-if="resourceLink(log)">
                    <a :href="resourceLink(log)" class="text-indigo-300 hover:text-indigo-200">
                      {{ resourceLabel(log) }}
                    </a>
                  </template>
                  <template v-else>
                    {{ resourceLabel(log) }}
                  </template>
                </div>
                <div v-if="log.context?.route_name" class="text-xs text-slate-500">
                  rota: {{ log.context.route_name }}
                </div>
              </td>
              <td class="px-4 py-3">
                <div class="font-medium text-slate-200">{{ friendlyOrigin(log) }}</div>
                <div v-if="log.context?.guard" class="text-xs text-slate-500">guard: {{ log.context.guard }}</div>
              </td>
              <td class="px-4 py-3">
                <div>{{ log.ip_address ?? '—' }}</div>
                <div v-if="log.context?.ip_forwarded_for" class="text-xs text-slate-500">
                  proxy: {{ log.context.ip_forwarded_for }}
                </div>
              </td>
              <td class="px-4 py-3">
                <div>{{ formatDateTime(log.created_at) }}</div>
                <div class="text-xs text-slate-500">{{ formatRelativeTime(log.created_at) }}</div>
              </td>
              <td class="px-4 py-3 text-sm text-slate-300">
                <p class="line-clamp-2">{{ summarizePayload(log.payload) }}</p>
              </td>
              <td class="px-4 py-3 text-right">
                <button
                  type="button"
                  class="rounded-md border border-indigo-500/40 px-3 py-1 text-xs font-semibold text-indigo-300 transition hover:bg-indigo-500/10"
                  @click="openDetails(log)"
                >
                  Ver detalhes
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <section
        v-if="groupedLogs.length > 0"
        class="mt-8 space-y-6 rounded-2xl border border-slate-800 bg-slate-950/50 p-5 shadow-inner shadow-black/20"
      >
        <header class="flex items-center gap-3">
          <span class="h-5 w-1 rounded-full bg-emerald-500"></span>
          <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-300">
            Timeline do recurso filtrado
          </h2>
        </header>

        <div v-for="group in groupedLogs" :key="group.key" class="space-y-4">
          <h3 class="text-sm font-semibold text-slate-200">{{ group.label }}</h3>
          <ol class="relative border-l border-slate-700/60 pl-6">
            <li
              v-for="log in group.logs"
              :key="log.id"
              class="mb-6 ml-2 rounded-xl border border-slate-800 bg-slate-900/60 p-4 shadow-sm shadow-black/20"
            >
              <span
                class="absolute -left-[9px] flex h-4 w-4 items-center justify-center rounded-full border border-slate-700 bg-slate-900 text-xs text-slate-300"
              >
                •
              </span>
              <div class="flex flex-col gap-1 text-sm text-slate-200">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <span class="font-semibold">{{ log.action }}</span>
                  <span class="text-xs text-slate-400">{{ formatDateTime(log.created_at) }}</span>
                </div>
                <div class="text-xs text-slate-400">
                  {{ log.user?.nome ?? log.user?.username ?? 'Sistema' }} · {{ friendlyOrigin(log) }}
                </div>
                <div v-if="log.context?.url" class="text-xs text-indigo-300 break-words">
                  {{ log.context.url }}
                </div>
                <div class="mt-2 text-xs text-slate-300">
                  {{ summarizePayload(log.payload) }}
                </div>
              </div>
              <div class="mt-3 flex items-center justify-end">
                <button
                  type="button"
                  class="rounded-md border border-slate-700 px-3 py-1 text-xs font-semibold text-slate-200 transition hover:bg-slate-800"
                  @click="openDetails(log)"
                >
                  Detalhar
                </button>
              </div>
            </li>
          </ol>
        </div>
      </section>
    </section>

    <footer
      v-if="links.length > 1"
      class="mt-6 flex flex-wrap items-center justify-center gap-2"
    >
      <button
        v-for="link in links"
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

    <transition name="fade">
      <div
        v-if="selectedLog"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 px-4 py-6 backdrop-blur"
        @click.self="closeDetails"
      >
        <div class="relative w-full max-w-4xl overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40">
          <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
            <div>
              <h2 class="text-lg font-semibold text-white">{{ selectedLog?.action }}</h2>
              <p class="text-xs text-slate-400">
                {{ formatDateTime(selectedLog?.created_at ?? null) }}
                <span v-if="selectedLog?.created_at"> · {{ formatRelativeTime(selectedLog?.created_at ?? null) }}</span>
              </p>
            </div>
            <button
              type="button"
              class="rounded-full bg-slate-900/80 p-2 text-slate-400 transition hover:text-white"
              @click="closeDetails"
            >
              <span class="sr-only">Fechar</span>
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </header>

          <div class="max-h-[70vh] space-y-6 overflow-y-auto px-6 py-5 text-sm text-slate-200">
            <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
              <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Contexto</h3>
              <dl class="grid gap-2 md:grid-cols-2">
                <div>
                  <dt class="text-xs text-slate-500">Usuário</dt>
                  <dd>{{ selectedLog?.user?.nome ?? selectedLog?.user?.username ?? 'Sistema' }}</dd>
                </div>
                <div>
                  <dt class="text-xs text-slate-500">Origem / Guard</dt>
                  <dd>{{ friendlyOrigin(selectedLog as AuditLogRow) }} <span v-if="selectedLog?.context?.guard" class="text-xs text-slate-500">(guard: {{ selectedLog?.context?.guard }})</span></dd>
                </div>
                <div>
                  <dt class="text-xs text-slate-500">Recurso</dt>
                  <dd>{{ resourceLabel(selectedLog as AuditLogRow) }}</dd>
                </div>
                <div>
                  <dt class="text-xs text-slate-500">IP</dt>
                  <dd>{{ selectedLog?.ip_address ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-xs text-slate-500">Método / URL</dt>
                  <dd>
                    {{ friendlyMethod(selectedLog as AuditLogRow) }}
                    <div v-if="selectedLog?.context?.url" class="truncate text-xs text-indigo-300">
                      {{ selectedLog?.context?.url }}
                    </div>
                  </dd>
                </div>
                <div>
                  <dt class="text-xs text-slate-500">User Agent</dt>
                  <dd class="text-xs text-slate-400">
                    {{ selectedLog?.user_agent ?? '—' }}
                  </dd>
                </div>
              </dl>
            </section>

            <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
              <header class="mb-3 flex items-center justify-between">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Alterações</h3>
                <button
                  type="button"
                  class="text-xs font-semibold text-indigo-300 transition hover:text-indigo-200"
                  @click="showRawPayload = !showRawPayload"
                >
                  {{ showRawPayload ? 'Ver resumo' : 'Ver JSON' }}
                </button>
              </header>
              <div v-if="showRawPayload" class="rounded-lg border border-slate-800 bg-slate-900/70 p-3 text-xs text-slate-200">
                <pre class="whitespace-pre-wrap break-words">{{ prettyPayload }}</pre>
              </div>
              <div v-else>
                <template v-if="detailedChanges.length > 0">
                  <table class="min-w-full divide-y divide-slate-800 text-xs text-slate-200">
                    <thead class="bg-slate-900/40 text-slate-400">
                      <tr>
                        <th class="px-3 py-2 text-left">Campo</th>
                        <th class="px-3 py-2 text-left">Antes</th>
                        <th class="px-3 py-2 text-left">Depois</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                      <tr v-for="item in detailedChanges" :key="item.key">
                        <td class="px-3 py-2 font-semibold text-slate-300">{{ item.key }}</td>
                        <td class="px-3 py-2 text-rose-300">{{ stringifyValue(item.before) }}</td>
                        <td class="px-3 py-2 text-emerald-300">{{ stringifyValue(item.after) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </template>
                <p v-else class="text-xs text-slate-400">
                  Nenhuma alteração detalhada disponível para este evento.
                </p>
              </div>
            </section>
          </div>
        </div>
      </div>
    </transition>
  </AuthenticatedLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
