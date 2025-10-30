import axios from '@/bootstrap';
import { computed, reactive, ref } from 'vue';

export type AuditLogEntry = {
  id: number;
  action: string;
  payload: Record<string, unknown> | null;
  created_at: string | null;
  user?: { id: number; nome?: string; username?: string } | null;
};

export type AuditTimelineMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

type EndpointResolver = () => string | null;

export function useAuditTimeline(resolveEndpoint: EndpointResolver) {
  const entries = ref<AuditLogEntry[]>([]);
  const meta = ref<AuditTimelineMeta | null>(null);
  const loading = ref(false);
  const errorMessage = ref('');
  const filters = reactive({
    dateFrom: '',
    dateTo: '',
  });
  const perPage = ref(20);

  const hasMore = computed(() => {
    if (!meta.value) return false;
    return meta.value.current_page < meta.value.last_page;
  });

  const hasFilters = computed(() => Boolean(filters.dateFrom || filters.dateTo));

  function resetTimeline(): void {
    entries.value = [];
    meta.value = null;
    errorMessage.value = '';
  }

  async function fetchTimeline(page = 1, append = false): Promise<void> {
    const endpoint = resolveEndpoint();
    if (!endpoint) {
      resetTimeline();
      return;
    }

    loading.value = true;
    errorMessage.value = '';

    try {
      const params: Record<string, unknown> = {
        page,
        per_page: perPage.value,
      };

      if (filters.dateFrom) params.date_from = filters.dateFrom;
      if (filters.dateTo) params.date_to = filters.dateTo;

      const { data } = await axios.get(endpoint, { params });
      const rows: AuditLogEntry[] = Array.isArray(data?.data) ? data.data : [];

      entries.value = append ? [...entries.value, ...rows] : rows;
      meta.value = data?.meta ?? null;
    } catch (error: any) {
      console.error(error);
      errorMessage.value = error?.response?.data?.message ?? 'Não foi possível carregar o histórico.';
    } finally {
      loading.value = false;
    }
  }

  function exportTimeline(format: 'csv' | 'json'): void {
    const endpoint = resolveEndpoint();
    if (!endpoint) return;

    const params = new URLSearchParams();
    params.append('format', format);
    if (filters.dateFrom) params.append('date_from', filters.dateFrom);
    if (filters.dateTo) params.append('date_to', filters.dateTo);

    window.open(`${endpoint}/export?${params.toString()}`, '_blank');
  }

  return {
    entries,
    meta,
    loading,
    errorMessage,
    filters,
    perPage,
    hasMore,
    hasFilters,
    fetchTimeline,
    resetTimeline,
    exportTimeline,
  };
}
