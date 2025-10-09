<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { computed, onMounted, reactive, ref } from 'vue';

type Nullable<T> = T | null;

interface ContratoForm {
    codigo_contrato: string;
    imovel_id: Nullable<number>;
    locador_id: Nullable<number>;
    locatario_id: Nullable<number>;
    fiador_id: Nullable<number>;
    data_inicio: string;
    data_fim: string;
    dia_vencimento: Nullable<number>;
    valor_aluguel: string;
    reajuste_indice: string;
    data_proximo_reajuste: string;
    garantia_tipo: string;
    caucao_valor: string;
    taxa_adm_percentual: string;
    status: string;
    observacoes: string;
}

const props = defineProps<{ contratoId?: number | null }>();

const isEditing = computed(() => Boolean(props.contratoId));
const loading = ref(false);
const saving = ref(false);
const errorMessage = ref('');

const garantiaOptions = ['Fiador', 'Seguro', 'Caucao', 'SemGarantia'];
const statusOptions = ['Ativo', 'Suspenso', 'Encerrado'];
const reajusteOptions = ['IGPM', 'IPCA', 'INPC'];

const form = reactive<ContratoForm>({
    codigo_contrato: '',
    imovel_id: null,
    locador_id: null,
    locatario_id: null,
    fiador_id: null,
    data_inicio: '',
    data_fim: '',
    dia_vencimento: null,
    valor_aluguel: '',
    reajuste_indice: 'IGPM',
    data_proximo_reajuste: '',
    garantia_tipo: 'SemGarantia',
    caucao_valor: '',
    taxa_adm_percentual: '',
    status: 'Ativo',
    observacoes: '',
});

async function loadContrato() {
    if (!props.contratoId) {
        return;
    }

    loading.value = true;
    errorMessage.value = '';

    try {
        const { data } = await axios.get(`/api/contratos/${props.contratoId}`);
        const payload = data.data;

        form.codigo_contrato = payload.codigo_contrato ?? '';
        form.imovel_id = payload.imovel?.id ?? null;
        form.locador_id = payload.locador?.id ?? null;
        form.locatario_id = payload.locatario?.id ?? null;
        form.fiador_id = payload.fiador?.id ?? null;
        form.data_inicio = payload.data_inicio ?? '';
        form.data_fim = payload.data_fim ?? '';
        form.dia_vencimento = payload.dia_vencimento ?? null;
        form.valor_aluguel = payload.valor_aluguel ?? '';
        form.reajuste_indice = payload.reajuste_indice ?? 'IGPM';
        form.data_proximo_reajuste = payload.data_proximo_reajuste ?? '';
        form.garantia_tipo = payload.garantia_tipo ?? 'SemGarantia';
        form.caucao_valor = payload.caucao_valor ?? '';
        form.taxa_adm_percentual = payload.taxa_adm_percentual ?? '';
        form.status = payload.status ?? 'Ativo';
        form.observacoes = payload.observacoes ?? '';
    } catch (error) {
        console.error(error);
        errorMessage.value = 'Nao foi possivel carregar o contrato.';
    } finally {
        loading.value = false;
    }
}

function buildPayload(): Record<string, unknown> {
    return {
        ...form,
        imovel_id: form.imovel_id ?? null,
        locador_id: form.locador_id ?? null,
        locatario_id: form.locatario_id ?? null,
        fiador_id: form.fiador_id ?? null,
        dia_vencimento: form.dia_vencimento ?? null,
    };
}

async function submit() {
    saving.value = true;
    errorMessage.value = '';

    const payload = buildPayload();

    try {
        if (isEditing.value && props.contratoId) {
            await axios.put(`/api/contratos/${props.contratoId}`, payload);
        } else {
            await axios.post('/api/contratos', payload);
        }
        router.visit('/contratos');
    } catch (error: any) {
        console.error(error);
        if (error?.response?.status === 422) {
            const messages = error.response.data?.errors ?? {};
            errorMessage.value = Object.values(messages).flat().join(' ');
        } else {
            errorMessage.value = 'Nao foi possivel salvar o contrato.';
        }
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    if (isEditing.value) {
        loadContrato();
    }
});
</script>

<template>
    <AuthenticatedLayout :title="isEditing ? 'Editar contrato' : 'Novo contrato'">
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-2xl font-semibold text-slate-900">
                {{ isEditing ? 'Editar contrato' : 'Novo contrato' }}
            </h2>
            <Link class="text-sm font-semibold text-indigo-600 hover:text-indigo-500" href="/contratos">Voltar</Link>
        </div>

        <div v-if="errorMessage" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ errorMessage }}
        </div>

        <form @submit.prevent="submit" class="grid gap-6 md:grid-cols-2">
            <section class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Codigo</label>
                    <input v-model="form.codigo_contrato" type="text" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Imovel ID</label>
                    <input v-model.number="form.imovel_id" type="number" min="1" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Locador ID</label>
                        <input v-model.number="form.locador_id" type="number" min="1" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Locatario ID</label>
                        <input v-model.number="form.locatario_id" type="number" min="1" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Fiador ID</label>
                    <input v-model.number="form.fiador_id" type="number" min="1" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                </div>
            </section>

            <section class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Data inicio</label>
                        <input v-model="form.data_inicio" type="date" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Data fim</label>
                        <input v-model="form.data_fim" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Dia de vencimento</label>
                    <input v-model.number="form.dia_vencimento" type="number" min="1" max="28" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Valor aluguel</label>
                    <input v-model="form.valor_aluguel" type="text" inputmode="decimal" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="0.00" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Indice de reajuste</label>
                    <select v-model="form.reajuste_indice" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                        <option v-for="option in reajusteOptions" :key="option" :value="option">{{ option }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Data proximo reajuste</label>
                    <input v-model="form.data_proximo_reajuste" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                </div>
            </section>

            <section class="space-y-4 md:col-span-2">
                <div class="grid gap-4 md:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Garantia</label>
                        <select v-model="form.garantia_tipo" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                            <option v-for="option in garantiaOptions" :key="option" :value="option">{{ option }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Valor caucao</label>
                        <input v-model="form.caucao_valor" type="text" inputmode="decimal" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="0.00" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Taxa adm (%)</label>
                        <input v-model="form.taxa_adm_percentual" type="text" inputmode="decimal" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="0.00" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Status</label>
                        <select v-model="form.status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                            <option v-for="option in statusOptions" :key="option" :value="option">{{ option }}</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Observacoes</label>
                    <textarea v-model="form.observacoes" rows="4" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"></textarea>
                </div>
            </section>

            <div class="md:col-span-2 flex items-center justify-end gap-3">
                <Link href="/contratos" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Cancelar
                </Link>
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500" :disabled="saving || loading">
                    {{ saving ? 'Salvando...' : 'Salvar' }}
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
