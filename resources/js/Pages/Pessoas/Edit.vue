<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { computed, onMounted, reactive, ref } from 'vue';

type Nullable<T> = T | null;

interface PessoaForm {
    nome_razao_social: string;
    tipo_pessoa: string;
    cpf_cnpj: string;
    email: string;
    telefone: string;
    papeis: string[];
}

const props = defineProps<{ pessoaId?: number | null }>();

const isEditing = computed(() => Boolean(props.pessoaId));
const loading = ref(false);
const saving = ref(false);
const errorMessage = ref('');

const tipoOptions = ['Fisica', 'Juridica'];
const papelOptions = ['Proprietario', 'Inquilino', 'Fiador', 'Corretor', 'Fornecedor', 'Funcionario'];

const form = reactive<PessoaForm>({
    nome_razao_social: '',
    tipo_pessoa: 'Fisica',
    cpf_cnpj: '',
    email: '',
    telefone: '',
    papeis: [],
});

async function loadPessoa() {
    if (!props.pessoaId) {
        return;
    }

    loading.value = true;
    errorMessage.value = '';

    try {
        const { data } = await axios.get(`/api/pessoas/${props.pessoaId}`);
        const payload = data.data;

        form.nome_razao_social = payload.nome_razao_social ?? '';
        form.tipo_pessoa = payload.tipo_pessoa ?? 'Fisica';
        form.cpf_cnpj = payload.cpf_cnpj ?? '';
        form.email = payload.email ?? '';
        form.telefone = payload.telefone ?? '';
        form.papeis = Array.isArray(payload.papeis) ? payload.papeis : [];
    } catch (error) {
        console.error(error);
        errorMessage.value = 'Nao foi possivel carregar a pessoa.';
    } finally {
        loading.value = false;
    }
}

function togglePapel(papel: string) {
    if (form.papeis.includes(papel)) {
        form.papeis = form.papeis.filter((item) => item !== papel);
    } else {
        form.papeis.push(papel);
    }
}

async function submit() {
    saving.value = true;
    errorMessage.value = '';

    const payload = { ...form, papeis: form.papeis };

    try {
        if (isEditing.value && props.pessoaId) {
            await axios.put(`/api/pessoas/${props.pessoaId}`, payload);
        } else {
            await axios.post('/api/pessoas', payload);
        }
        router.visit('/pessoas');
    } catch (error: any) {
        console.error(error);
        if (error?.response?.status === 422) {
            const messages = error.response.data?.errors ?? {};
            errorMessage.value = Object.values(messages).flat().join(' ');
        } else {
            errorMessage.value = 'Nao foi possivel salvar a pessoa.';
        }
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    if (isEditing.value) {
        loadPessoa();
    }
});
</script>

<template>
    <AuthenticatedLayout :title="isEditing ? 'Editar pessoa' : 'Nova pessoa'">
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-2xl font-semibold text-slate-900">
                {{ isEditing ? 'Editar pessoa' : 'Nova pessoa' }}
            </h2>
            <Link class="text-sm font-semibold text-indigo-600 hover:text-indigo-500" href="/pessoas">Voltar</Link>
        </div>

        <div v-if="errorMessage" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ errorMessage }}
        </div>

        <form @submit.prevent="submit" class="grid gap-6 md:grid-cols-2">
            <section class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Nome / Razao social</label>
                    <input v-model="form.nome_razao_social" type="text" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Tipo</label>
                    <select v-model="form.tipo_pessoa" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                        <option v-for="option in tipoOptions" :key="option" :value="option">{{ option }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">CPF / CNPJ</label>
                    <input v-model="form.cpf_cnpj" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="Somente numeros" />
                </div>
            </section>

            <section class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Email</label>
                    <input v-model="form.email" type="email" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Telefone</label>
                    <input v-model="form.telefone" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Papeis</label>
                    <div class="mt-1 grid gap-2 sm:grid-cols-2">
                        <label v-for="option in papelOptions" :key="option" class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" :value="option" :checked="form.papeis.includes(option)" @change="togglePapel(option)" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            {{ option }}
                        </label>
                    </div>
                </div>
            </section>

            <div class="md:col-span-2 flex items-center justify-end gap-3">
                <Link href="/pessoas" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Cancelar
                </Link>
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500" :disabled="saving || loading">
                    {{ saving ? 'Salvando...' : 'Salvar' }}
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
