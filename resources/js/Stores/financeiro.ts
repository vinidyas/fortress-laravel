import { defineStore } from 'pinia';

type FilterState = {
    search: string;
    tipo: 'credito' | 'debito' | '';
    status: 'pendente' | 'conciliado' | 'cancelado' | '';
    accountId: number | null;
    costCenterId: number | null;
    dateFrom: string | null;
    dateTo: string | null;
    perPage: number;
};

type FinanceiroState = {
    filters: FilterState;
};

const defaultFilters = (): FilterState => ({
    search: '',
    tipo: '',
    status: '',
    accountId: null,
    costCenterId: null,
    dateFrom: null,
    dateTo: null,
    perPage: 15,
});

export const useFinanceiroStore = defineStore('financeiro', {
    state: (): FinanceiroState => ({
        filters: defaultFilters(),
    }),
    getters: {
        query(state) {
            const query: Record<string, unknown> = {};

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

            query['per_page'] = state.filters.perPage;

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
