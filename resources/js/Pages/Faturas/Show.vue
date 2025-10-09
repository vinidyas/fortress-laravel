<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { computed, onMounted, reactive, ref } from 'vue';

type Nullable<T> = T | null;

type FaturaItem = {
    id: number;
    categoria: string;
    descricao: Nullable<string>;
    quantidade: string;
    valor_unitario: string;
    valor_total: string;
};

type FaturaData = {
    id: number;
    contrato_id: number;
    competencia: string;
    vencimento: string;
    status: string;
    valor_total: string;
    valor_pago: Nullable<string>;
    pago_em: Nullable<string>;
    metodo_pagamento: Nullable<string>;
    nosso_numero: Nullable<string>;
    boleto_url: Nullable<string>;
    pix_qrcode: Nullable<string>;
    observacoes: Nullable<string>;
    contrato: Nullable<{
        codigo_contrato?: string;
        imovel?: {
            codigo?: string;
            cidade?: Nullable<string>;
        } | null;
    }>;
    itens: FaturaItem[];
};

const props = defineProps<{ faturaId: Nullable<number> }>();

const isNew = computed(() => props.faturaId === null);
const loading = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const fatura = ref<FaturaData | null>(null);

const baixaForm = reactive({
    valor_pago: '',
    pago_em: '',
    metodo_pagamento: 'PIX',
    observacoes: '',
});

const metodoOptions = ['PIX', 'Boleto', 'Transferencia', 'Dinheiro', 'Cartao', 'Outro'];

async function fetchFatura() {
    if (isNew.value || !props.faturaId) {
        return;
    }

    loading.value = true;
    errorMessage.value = '';

    try {
        const { data } = await axios.get(`/api/faturas/${props.faturaId}`);
        fatura.value = data.data;
    } catch (error) {
        console.error(error);
        errorMessage.value = 'Nao foi possivel carregar a fatura.';
    } finally {
        loading.value = false;
    }
}

async function settle() {
    if (!props.faturaId) return;

    successMessage.value = '';
    errorMessage.value = '';

    try {
        const payload = {
            valor_pago: baixaForm.valor_pago,
            pago_em: baixaForm.pago_em,
            metodo_pagamento: baixaForm.metodo_pagamento,
            observacoes: baixaForm.observacoes,
        };

        const { data } = await axios.post(`/api/faturas/${props.faturaId}/settle`, payload);
        fatura.value = data.data;
        successMessage.value = 'Fatura quitada com sucesso.';
    } catch (error: any) {
        console.error(error);
        if (error?.response?.status === 422) {
            const messages = error.response.data?.errors ?? {};
            errorMessage.value = Object.values(messages).flat().join(' ');
        } else {
            errorMessage.value = 'Nao foi possivel quitar a fatura.';
        }
    }
}

async function cancel() {
    if (!props.faturaId) return;

    successMessage.value = '';
    errorMessage.value = '';

    try {
        const { data } = await axios.post(`/api/faturas/${props.faturaId}/cancel`);
        fatura.value = data.data;
        successMessage.value = 'Fatura cancelada.';
    } catch (error: any) {
        console.error(error);
        errorMessage.value = error?.response?.data?.message ?? 'Nao foi possivel cancelar a fatura.';
    }
}

onMounted(() => {
    fetchFatura();
});
</script>

<template>
    <AuthenticatedLayout :title="isNew ? 'Nova fatura' : `Fatura #${fatura?.id ?? ''}`">
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-2xl font-semibold text-slate-900">
                {{ isNew ? 'Nova fatura' : `Fatura #${fatura?.id}` }}
            </h2>
            <Link class="text-sm font-semibold text-indigo-600 hover:text-indigo-500" href="/faturas">Voltar</Link>
        </div>

        <div v-if="loading" class="rounded-md border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-600">
            Carregando fatura...
        </div>

        <div v-else-if="isNew" class="rounded-md border border-slate-200 bg-white px-4 py-6 text-sm text-slate-600">
            Selecione um contrato na listagem para gerar faturas pelo comando ou pela API.
        </div>

        <div v-else>
            <div v-if="errorMessage" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ errorMessage }}
            </div>
            <div v-if="successMessage" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ successMessage }}
            </div>

            <section class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-sm uppercase tracking-wide text-slate-500">Contrato</p>
                        <p class="text-lg font-semibold text-slate-900">{{ fatura?.contrato?.codigo_contrato ?? '-' }} (ID {{ fatura?.contrato_id }})</p>
                        <p class="text-sm text-slate-500">
                            Imovel: {{ fatura?.contrato?.imovel?.codigo ?? '-' }} / {{ fatura?.contrato?.imovel?.cidade ?? '-' }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm uppercase tracking-wide text-slate-500">Status</p>
                        <span
                            :class="[
                                'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold',
                                fatura?.status === 'Aberta'
                                    ? 'bg-amber-100 text-amber-700'
                                    : fatura?.status === 'Paga'
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-rose-100 text-rose-700',
                            ]"
                        >
                            {{ fatura?.status }}
                        </span>
                    </div>
                </div>
                <div class="mt-4 grid gap-4 md:grid-cols-4 text-sm text-slate-700">
                    <div>
                        <p class="text-xs uppercase text-slate-500">Competencia</p>
                        <p class="font-medium">{{ fatura?.competencia }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-500">Vencimento</p>
                        <p class="font-medium">{{ fatura?.vencimento }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-500">Valor total</p>
                        <p class="font-medium">R$ {{ Number(fatura?.valor_total ?? 0).toFixed(2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-500">Valor pago</p>
                        <p class="font-medium">{{ fatura?.valor_pago ? `R$ ${Number(fatura.valor_pago).toFixed(2)}` : '-' }}</p>
                    </div>
                </div>
            </section>

            <section class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Itens</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Categoria</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Descricao</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Qtd</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Valor unitario</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Valor total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
                            <tr v-if="!fatura?.itens?.length">
                                <td colspan="5" class="px-4 py-6 text-center text-slate-500">Nenhum lancamento.</td>
                            </tr>
                            <tr v-for="item in fatura?.itens ?? []" :key="item.id">
                                <td class="px-4 py-3">{{ item.categoria }}</td>
                                <td class="px-4 py-3">{{ item.descricao ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">{{ Number(item.quantidade).toFixed(2) }}</td>
                                <td class="px-4 py-3 text-right">R$ {{ Number(item.valor_unitario).toFixed(2) }}</td>
                                <td class="px-4 py-3 text-right">R$ {{ Number(item.valor_total).toFixed(2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="fatura?.status === 'Aberta'" class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Quitar fatura</h3>
                <form @submit.prevent="settle" class="mt-4 grid gap-4 md:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Valor pago</label>
                        <input v-model="baixaForm.valor_pago" type="text" inputmode="decimal" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="0.00" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Pago em</label>
                        <input v-model="baixaForm.pago_em" type="date" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Metodo</label>
                        <select v-model="baixaForm.metodo_pagamento" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                            <option v-for="option in metodoOptions" :key="option" :value="option">{{ option }}</option>
                        </select>
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-sm font-medium text-slate-700">Observacoes</label>
                        <textarea v-model="baixaForm.observacoes" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"></textarea>
                    </div>
                    <div class="md:col-span-4 flex items-center gap-3">
                        <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500" :disabled="loading">
                            Confirmar baixa
                        </button>
                        <button type="button" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-500" @click="cancel" :disabled="loading">
                            Cancelar fatura
                        </button>
                    </div>
                </form>
            </section>

            <section v-else-if="fatura?.status !== 'Cancelada'" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Acoes</h3>
                <button type="button" class="mt-3 rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-500" @click="cancel" :disabled="loading">
                    Cancelar fatura
                </button>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
