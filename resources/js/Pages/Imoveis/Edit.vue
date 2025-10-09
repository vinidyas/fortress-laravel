<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { computed, onMounted, reactive, ref } from 'vue';

type Nullable<T> = T | null;

interface ImovelPayload {
    codigo: string;
    proprietario_id: Nullable<number>;
    agenciador_id: Nullable<number>;
    responsavel_id: Nullable<number>;
    tipo_imovel: string;
    finalidade: string[];
    disponibilidade: string;
    cep: string;
    estado: string;
    cidade: string;
    bairro: string;
    rua: string;
    condominio_id: Nullable<number>;
    logradouro: string;
    numero: string;
    complemento: string;
    valor_locacao: string;
    valor_condominio: string;
    condominio_isento: boolean;
    valor_iptu: string;
    iptu_isento: boolean;
    outros_valores: string;
    outros_isento: boolean;
    periodo_iptu: string;
    dormitorios: Nullable<number>;
    suites: Nullable<number>;
    banheiros: Nullable<number>;
    vagas_garagem: Nullable<number>;
    area_total: string;
    area_construida: string;
    comodidades: string[];
}

const props = defineProps<{ imovelId?: number | null }>();

const isEditing = computed(() => Boolean(props.imovelId));
const loading = ref(false);
const saving = ref(false);
const errorMessage = ref('');

const finalidadeOptions = ['Locacao', 'Venda'];
const disponibilidadeOptions = ['Disponivel', 'Indisponivel'];
const periodoOptions = ['Mensal', 'Anual'];

const form = reactive<ImovelPayload>({
    codigo: '',
    proprietario_id: null,
    agenciador_id: null,
    responsavel_id: null,
    tipo_imovel: '',
    finalidade: [],
    disponibilidade: 'Disponivel',
    cep: '',
    estado: '',
    cidade: '',
    bairro: '',
    rua: '',
    condominio_id: null,
    logradouro: '',
    numero: '',
    complemento: '',
    valor_locacao: '',
    valor_condominio: '',
    condominio_isento: false,
    valor_iptu: '',
    iptu_isento: false,
    outros_valores: '',
    outros_isento: false,
    periodo_iptu: 'Mensal',
    dormitorios: null,
    suites: null,
    banheiros: null,
    vagas_garagem: null,
    area_total: '',
    area_construida: '',
    comodidades: [],
});

const comodidadesText = ref('');

function normalizeDecimal(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    if (typeof value === 'number') {
        return value.toString();
    }

    return String(value)
        .replace(/[^0-9,.-]/g, '')
        .replace(/\./g, '')
        .replace(/,/g, '.');
}

async function loadImovel() {
    if (!props.imovelId) {
        return;
    }

    loading.value = true;
    errorMessage.value = '';

    try {
        const { data } = await axios.get(`/api/imoveis/${props.imovelId}`);
        const payload = data.data;

        form.codigo = payload.codigo ?? '';
        form.proprietario_id = payload.proprietario?.id ?? null;
        form.agenciador_id = payload.agenciador?.id ?? null;
        form.responsavel_id = payload.responsavel?.id ?? null;
        form.tipo_imovel = payload.tipo_imovel ?? '';
        form.finalidade = Array.isArray(payload.finalidade) ? payload.finalidade : [];
        form.disponibilidade = payload.disponibilidade ?? 'Disponivel';
        form.cep = payload.enderecos?.cep ?? '';
        form.estado = payload.enderecos?.estado ?? '';
        form.cidade = payload.enderecos?.cidade ?? '';
        form.bairro = payload.enderecos?.bairro ?? '';
        form.rua = payload.enderecos?.rua ?? '';
        form.condominio_id = payload.condominio?.id ?? null;
        form.logradouro = payload.enderecos?.logradouro ?? '';
        form.numero = payload.enderecos?.numero ?? '';
        form.complemento = payload.enderecos?.complemento ?? '';
        form.valor_locacao = normalizeDecimal(payload.valores?.valor_locacao);
        form.valor_condominio = normalizeDecimal(payload.valores?.valor_condominio);
        form.condominio_isento = Boolean(payload.valores?.condominio_isento);
        form.valor_iptu = normalizeDecimal(payload.valores?.valor_iptu);
        form.iptu_isento = Boolean(payload.valores?.iptu_isento);
        form.outros_valores = normalizeDecimal(payload.valores?.outros_valores);
        form.outros_isento = Boolean(payload.valores?.outros_isento);
        form.periodo_iptu = payload.valores?.periodo_iptu ?? 'Mensal';
        form.dormitorios = payload.caracteristicas?.dormitorios ?? null;
        form.suites = payload.caracteristicas?.suites ?? null;
        form.banheiros = payload.caracteristicas?.banheiros ?? null;
        form.vagas_garagem = payload.caracteristicas?.vagas_garagem ?? null;
        form.area_total = normalizeDecimal(payload.caracteristicas?.area_total);
        form.area_construida = normalizeDecimal(payload.caracteristicas?.area_construida);
        form.comodidades = Array.isArray(payload.caracteristicas?.comodidades) ? payload.caracteristicas.comodidades : [];
        comodidadesText.value = form.comodidades.join(', ');
    } catch (error) {
        console.error(error);
        errorMessage.value = 'Nao foi possivel carregar o imovel.';
    } finally {
        loading.value = false;
    }
}

function buildPayload(): Record<string, unknown> {
    return {
        ...form,
        finalidade: form.finalidade,
        comodidades: comodidadesText.value
            .split(',')
            .map((item) => item.trim())
            .filter((item) => item.length > 0),
        proprietario_id: form.proprietario_id ?? null,
        agenciador_id: form.agenciador_id ?? null,
        responsavel_id: form.responsavel_id ?? null,
        condominio_id: form.condominio_id ?? null,
        dormitorios: form.dormitorios ?? null,
        suites: form.suites ?? null,
        banheiros: form.banheiros ?? null,
        vagas_garagem: form.vagas_garagem ?? null,
    };
}

async function submit() {
    saving.value = true;
    errorMessage.value = '';

    const payload = buildPayload();

    try {
        if (isEditing.value && props.imovelId) {
            await axios.put(`/api/imoveis/${props.imovelId}`, payload);
        } else {
            await axios.post('/api/imoveis', payload);
        }
        router.visit('/imoveis');
    } catch (error: any) {
        console.error(error);
        if (error?.response?.status === 422) {
            const messages = error.response.data?.errors ?? {};
            errorMessage.value = Object.values(messages).flat().join(' ');
        } else {
            errorMessage.value = 'Nao foi possivel salvar o imovel.';
        }
    } finally {
        saving.value = false;
    }
}

function toggleFinalidade(option: string) {
    if (form.finalidade.includes(option)) {
        form.finalidade = form.finalidade.filter((item) => item !== option);
    } else {
        form.finalidade.push(option);
    }
}

onMounted(() => {
    if (isEditing.value) {
        loadImovel();
    }
});
</script>

<template>
    <AuthenticatedLayout :title="isEditing ? 'Editar imovel' : 'Novo imovel'">
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-2xl font-semibold text-slate-900">
                {{ isEditing ? 'Editar imovel' : 'Novo imovel' }}
            </h2>
            <Link class="text-sm font-semibold text-indigo-600 hover:text-indigo-500" href="/imoveis">Voltar</Link>
        </div>

        <div v-if="errorMessage" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ errorMessage }}
        </div>

        <form @submit.prevent="submit" class="grid gap-6 md:grid-cols-2">
            <section class="space-y-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Identificacao</h3>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Codigo</label>
                    <input v-model="form.codigo" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="Gerado automaticamente" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Tipo de imovel</label>
                    <input v-model="form.tipo_imovel" type="text" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Disponibilidade</label>
                    <select v-model="form.disponibilidade" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                        <option v-for="option in disponibilidadeOptions" :key="option" :value="option">{{ option }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Finalidade</label>
                    <div class="mt-1 flex flex-wrap gap-3">
                        <label v-for="option in finalidadeOptions" :key="option" class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" :value="option" :checked="form.finalidade.includes(option)" @change="toggleFinalidade(option)" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            {{ option }}
                        </label>
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Localizacao</h3>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">CEP</label>
                        <input v-model="form.cep" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Estado</label>
                        <input v-model="form.estado" type="text" maxlength="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 uppercase" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Cidade</label>
                        <input v-model="form.cidade" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Bairro</label>
                        <input v-model="form.bairro" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Rua</label>
                        <input v-model="form.rua" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Numero</label>
                        <input v-model="form.numero" type="text" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Logradouro</label>
                    <input v-model="form.logradouro" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Complemento</label>
                    <input v-model="form.complemento" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                </div>
            </section>

            <section class="space-y-4 md:col-span-2">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Valores e caracteristicas</h3>
                <div class="grid gap-4 md:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Valor locacao</label>
                        <input v-model="form.valor_locacao" type="text" inputmode="decimal" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="0.00" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Valor condominio</label>
                        <input v-model="form.valor_condominio" type="text" inputmode="decimal" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="0.00" />
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-slate-600">
                            <input v-model="form.condominio_isento" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            Isento
                        </label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Valor IPTU</label>
                        <input v-model="form.valor_iptu" type="text" inputmode="decimal" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="0.00" />
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-slate-600">
                            <input v-model="form.iptu_isento" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            Isento
                        </label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Outros valores</label>
                        <input v-model="form.outros_valores" type="text" inputmode="decimal" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="0.00" />
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-slate-600">
                            <input v-model="form.outros_isento" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            Isento
                        </label>
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Dormitorios</label>
                        <input v-model.number="form.dormitorios" type="number" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Suites</label>
                        <input v-model.number="form.suites" type="number" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Banheiros</label>
                        <input v-model.number="form.banheiros" type="number" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Vagas</label>
                        <input v-model.number="form.vagas_garagem" type="number" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Area total (m2)</label>
                        <input v-model="form.area_total" type="text" inputmode="decimal" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="0.00" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Area construida (m2)</label>
                        <input v-model="form.area_construida" type="text" inputmode="decimal" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="0.00" />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Periodo do IPTU</label>
                    <select v-model="form.periodo_iptu" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                        <option v-for="option in periodoOptions" :key="option" :value="option">{{ option }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Comodidades (separe com virgula)</label>
                    <input v-model="comodidadesText" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="Piscina, Churrasqueira" />
                </div>
            </section>

            <div class="md:col-span-2 flex items-center justify-end gap-3">
                <Link href="/imoveis" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Cancelar
                </Link>
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500" :disabled="saving || loading">
                    {{ saving ? 'Salvando...' : 'Salvar' }}
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>

