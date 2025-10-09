<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { computed, onMounted, reactive, ref, watch } from 'vue';

type Nullable<T> = T | null;

type ImovelRow = {
    id: number;
    codigo: string;
    tipo_imovel: string;
    disponibilidade: string;
    enderecos: {
        cidade: Nullable<string>;
        bairro: Nullable<string>;
        rua: Nullable<string>;
        logradouro: Nullable<string>;
    };
    valores: {
        valor_locacao: Nullable<string | number>;
    };
    caracteristicas: {
        dormitorios: Nullable<number>;
        vagas_garagem: Nullable<number>;
    };
};

type MetaPagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

const finalidadeOptions = [
    { value: 'Locacao', label: 'Locacao' },
    { value: 'Venda', label: 'Venda' },
];

const disponibilidadeOptions = [
    { value: 'Disponivel', label: 'Disponivel' },
    { value: 'Indisponivel', label: 'Indisponivel' },
];

const filters = reactive({
    search: '',
    tipo_imovel: '',
    cidade: '',
    disponibilidade: '',
    finalidade: [] as string[],
});

const imoveis = ref<ImovelRow[]>([]);
const meta = ref<MetaPagination | null>(null);
const loading = ref(false);
const errorMessage = ref('');
const perPageOptions = [10, 15, 25, 50];
const perPage = ref(15);

const currencyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

function formatCurrency(value: Nullable<string | number>): string {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const numeric = typeof value === 'number' ? value : Number.parseFloat(String(value));

    if (Number.isNaN(numeric)) {
        return '-';
    }

    return currencyFormatter.format(numeric);
}

function availabilityLabel(value: string): string {
    const normalized = value ? value.toLowerCase() : '';

    if (normalized === 'disponivel') {
        return 'Disponivel';
    }

    if (normalized === 'indisponivel') {
        return 'Indisponivel';
    }

    return value;
}

function availabilityClasses(value: string): string {
    const normalized = availabilityLabel(value);

    return normalized === 'Disponivel'
        ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30'
        : 'bg-rose-500/15 text-rose-300 border border-rose-500/30';
}

async function fetchImoveis(page = 1): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    const params: Record<string, unknown> = {
        page,
        per_page: perPage.value,
    };

    if (filters.search) params['filter[search]'] = filters.search;
    if (filters.tipo_imovel) params['filter[tipo_imovel]'] = filters.tipo_imovel;
    if (filters.disponibilidade) params['filter[disponibilidade]'] = filters.disponibilidade;
    if (filters.cidade) params['filter[cidade]'] = filters.cidade;
    if (filters.finalidade.length > 0) params['filter[finalidade]'] = filters.finalidade;

    try {
        const { data } = await axios.get('/api/imoveis', { params });
        imoveis.value = data.data ?? [];
        meta.value = data.meta ?? null;
    } catch (error) {
        console.error(error);
        errorMessage.value = 'Nao foi possivel carregar os imoveis.';
        imoveis.value = [];
        meta.value = null;
    } finally {
        loading.value = false;
    }
}

function applyFilters(): void {
    fetchImoveis(1);
}

function resetFilters(): void {
    filters.search = '';
    filters.tipo_imovel = '';
    filters.cidade = '';
    filters.disponibilidade = '';
    filters.finalidade = [];
    perPage.value = 15;
    fetchImoveis(1);
}

function toggleFinalidade(value: string): void {
    if (filters.finalidade.includes(value)) {
        filters.finalidade = filters.finalidade.filter((item) => item !== value);
    } else {
        filters.finalidade.push(value);
    }
}

function changePage(page: number): void {
    if (!meta.value) return;
    if (page < 1 || page > meta.value.last_page) return;

    fetchImoveis(page);
}

const hasResults = computed(() => imoveis.value.length > 0);

watch(perPage, () => {
    fetchImoveis(1);
});

onMounted(() => {
    fetchImoveis();
});
</script>

<template>
    <AuthenticatedLayout title="Imoveis">
        <div class="space-y-8 text-slate-100">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-semibold text-white">Imoveis</h2>
                    <p class="text-sm text-slate-400">Gerencie o portifolio com filtros completos.</p>
                </div>

                <Link
                    class="inline-flex items-center justify-center rounded-xl border border-indigo-500/40 bg-indigo-600/70 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500/80"
                    href="/imoveis/novo"
                >
                    + Novo imovel
                </Link>
            </div>

            <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
                <form @submit.prevent="applyFilters" class="grid gap-5 lg:grid-cols-6">
                    <div class="lg:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Busca</label>
                        <input
                            v-model="filters.search"
                            type="search"
                            class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                            placeholder="Codigo, endereco ou cidade"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tipo</label>
                        <input
                            v-model="filters.tipo_imovel"
                            type="text"
                            class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                            placeholder="Apartamento, Casa..."
                        />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Cidade</label>
                        <input
                            v-model="filters.cidade"
                            type="text"
                            class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Disponibilidade</label>
                        <select
                            v-model="filters.disponibilidade"
                            class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                        >
                            <option value="">Todas</option>
                            <option v-for="option in disponibilidadeOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Registros por pagina</label>
                        <select
                            v-model="perPage"
                            class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                        >
                            <option v-for="option in perPageOptions" :key="option" :value="option">{{ option }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Finalidade</label>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="option in finalidadeOptions"
                                :key="option.value"
                                class="flex items-center gap-2 text-sm text-slate-200"
                            >
                                <input
                                    type="checkbox"
                                    :value="option.value"
                                    :checked="filters.finalidade.includes(option.value)"
                                    @change="toggleFinalidade(option.value)"
                                    class="rounded border-slate-600 bg-slate-800 text-indigo-500 focus:ring-indigo-500"
                                />
                                {{ option.label }}
                            </label>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 lg:col-span-6">
                        <button
                            type="submit"
                            class="rounded-xl border border-indigo-500/40 bg-indigo-600/80 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-900/30 transition hover:bg-indigo-500/80"
                            :disabled="loading"
                        >
                            Aplicar filtros
                        </button>
                        <button
                            type="button"
                            class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800/60"
                            @click="resetFilters"
                            :disabled="loading"
                        >
                            Limpar
                        </button>
                    </div>
                </form>
            </section>

            <div v-if="errorMessage" class="rounded-xl border border-rose-500/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-200">
                {{ errorMessage }}
            </div>

            <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/80 shadow-xl shadow-black/40">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Codigo</th>
                            <th class="px-4 py-3 text-left">Tipo</th>
                            <th class="px-4 py-3 text-left">Cidade / Bairro</th>
                            <th class="px-4 py-3 text-left">Locacao</th>
                            <th class="px-4 py-3 text-left">Dorms / Vagas</th>
                            <th class="px-4 py-3 text-left">Disponibilidade</th>
                            <th class="px-4 py-3 text-right">Acoes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 bg-slate-950/50 text-slate-200">
                        <tr v-if="loading">
                            <td colspan="7" class="px-4 py-6 text-center text-slate-400">Carregando imoveis...</td>
                        </tr>
                        <tr v-else-if="!hasResults">
                            <td colspan="7" class="px-4 py-6 text-center text-slate-400">Nenhum imovel encontrado.</td>
                        </tr>
                        <tr v-else v-for="imovel in imoveis" :key="imovel.id" class="hover:bg-slate-900/60">
                            <td class="px-4 py-3 font-semibold text-white">{{ imovel.codigo }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ imovel.tipo_imovel }}</td>
                            <td class="px-4 py-3">
                                <div class="text-slate-200">{{ imovel.enderecos.cidade ?? '-' }}</div>
                                <div class="text-xs text-slate-500">{{ imovel.enderecos.bairro ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-200">{{ formatCurrency(imovel.valores.valor_locacao) }}</td>
                            <td class="px-4 py-3 text-slate-200">
                                {{ imovel.caracteristicas.dormitorios ?? 0 }} / {{ imovel.caracteristicas.vagas_garagem ?? 0 }}
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold', availabilityClasses(imovel.disponibilidade)]">
                                    {{ availabilityLabel(imovel.disponibilidade) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="`/imoveis/${imovel.id}`"
                                    class="rounded-lg border border-indigo-500/40 bg-indigo-500/20 px-3 py-1.5 text-xs font-semibold text-indigo-200 transition hover:border-indigo-400 hover:text-white"
                                >
                                    Editar
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <div
                v-if="meta"
                class="flex flex-col items-center justify-between gap-3 rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-4 text-sm text-slate-300 shadow-xl shadow-black/40 sm:flex-row"
            >
                <div>
                    Mostrando pagina {{ meta.current_page }} de {{ meta.last_page }} - {{ meta.total }} registros
                </div>
                <div class="flex items-center gap-2">
                    <button
                        class="rounded-lg border border-slate-700 px-3 py-2 transition hover:bg-slate-800/70"
                        :disabled="loading || meta.current_page <= 1"
                        @click="changePage(meta.current_page - 1)"
                    >
                        Anterior
                    </button>
                    <button
                        class="rounded-lg border border-slate-700 px-3 py-2 transition hover:bg-slate-800/70"
                        :disabled="loading || meta.current_page >= meta.last_page"
                        @click="changePage(meta.current_page + 1)"
                    >
                        Proxima
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
