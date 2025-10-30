import { defineStore } from 'pinia';

type PerPageOption = number | 'all';

const formatDate = (date: Date): string => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const defaultMonthRange = () => {
  const today = new Date();
  const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

  return {
    from: formatDate(startOfMonth),
    to: formatDate(today),
  };
};

type FilterState = {
  search: string;
  tipo: 'receita' | 'despesa' | 'transferencia' | '';
  status:
    | ''
    | 'open'
    | 'settled'
    | 'cancelled'
    | 'overdue'
    | 'planejado'
    | 'pendente'
    | 'pago'
    | 'cancelado'
    | 'atrasado';
  accountId: number | null;
  costCenterId: number | null;
  dateFrom: string | null;
  dateTo: string | null;
  perPage: PerPageOption;
};

type FinanceiroState = {
  filters: FilterState;
};

const defaultFilters = (): FilterState => {
  const { from, to } = defaultMonthRange();

  return {
    search: '',
    tipo: '',
    status: '',
    accountId: null,
    costCenterId: null,
    dateFrom: from,
    dateTo: to,
    perPage: 'all',
  };
};

export const useFinanceiroStore = defineStore('financeiro', {
  state: (): FinanceiroState => ({
    filters: defaultFilters(),
  }),
  getters: {
    query(state) {
      const query: Record<string, string | number | null> = {};

      if (state.filters.search) {
        query['filter[search]'] = state.filters.search;
      }
      if (state.filters.tipo) {
        query['filter[tipo]'] = state.filters.tipo;
      }
      if (state.filters.status) {
        query['filter[status]'] = state.filters.status;
      }
      if (state.filters.accountId) {
        query['filter[account_id]'] = state.filters.accountId;
      }
      if (state.filters.costCenterId) {
        query['filter[cost_center_id]'] = state.filters.costCenterId;
      }
      if (state.filters.dateFrom) {
        query['filter[data_de]'] = state.filters.dateFrom;
      }
      if (state.filters.dateTo) {
        query['filter[data_ate]'] = state.filters.dateTo;
      }

      query['per_page'] = state.filters.perPage === 'all' ? 'all' : state.filters.perPage;

      return query;
    },
  },
  actions: {
    setFilters(partial: Partial<FilterState>) {
      this.filters = {
        ...this.filters,
        ...partial,
      };
    },
    resetFilters() {
      this.filters = defaultFilters();
    },
  },
});
