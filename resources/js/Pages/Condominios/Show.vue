<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from '@/bootstrap';
import { onMounted, ref } from 'vue';

const props = defineProps<{ condominioId: number }>();

const loading = ref(true);
const error = ref('');
const item = ref<Record<string, any> | null>(null);

onMounted(async () => {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await axios.get(`/api/condominios/${props.condominioId}`);
    item.value = data.data ?? null;
  } catch (e) {
    console.error(e);
    error.value = 'Não foi possível carregar o condomínio.';
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <AuthenticatedLayout title="Condomínio">
    <div class="space-y-6 text-slate-100">
      <header class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold">Condomínio</h2>
        <a :href="`/condominios/${props.condominioId}`" class="rounded-lg border border-indigo-500/40 bg-indigo-500/20 px-3 py-2 text-sm font-semibold text-indigo-200 transition hover:border-indigo-400 hover:bg-indigo-500/30 hover:text-white">Editar</a>
      </header>

      <div v-if="error" class="rounded-xl border border-rose-500/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-200">{{ error }}</div>
      <div v-else-if="loading" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-6">Carregando...</div>
      <div v-else-if="item" class="grid gap-6 md:grid-cols-2">
        <section class="space-y-2 rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
          <h3 class="text-sm font-semibold text-slate-300">Identificação</h3>
          <div><span class="text-slate-400">Nome:</span> <span class="text-white">{{ item.nome || '-' }}</span></div>
          <div><span class="text-slate-400">CNPJ:</span> <span class="text-white">{{ item.cnpj || '-' }}</span></div>
          <div><span class="text-slate-400">Telefone:</span> <span class="text-white">{{ item.telefone || '-' }}</span></div>
          <div><span class="text-slate-400">Email:</span> <span class="text-white">{{ item.email || '-' }}</span></div>
        </section>
        <section class="space-y-2 rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
          <h3 class="text-sm font-semibold text-slate-300">Endereço</h3>
          <div><span class="text-slate-400">CEP:</span> <span class="text-white">{{ item.cep || '-' }}</span></div>
          <div><span class="text-slate-400">Cidade/UF:</span> <span class="text-white">{{ item.cidade || '-' }}/{{ item.estado || '-' }}</span></div>
          <div><span class="text-slate-400">Bairro:</span> <span class="text-white">{{ item.bairro || '-' }}</span></div>
          <div><span class="text-slate-400">Rua:</span> <span class="text-white">{{ item.rua || '-' }}</span></div>
          <div><span class="text-slate-400">Número:</span> <span class="text-white">{{ item.numero || '-' }}</span></div>
          <div><span class="text-slate-400">Complemento:</span> <span class="text-white">{{ item.complemento || '-' }}</span></div>
        </section>
        <section class="md:col-span-2 space-y-2 rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
          <h3 class="text-sm font-semibold text-slate-300">Observações</h3>
          <div class="text-slate-200">{{ item.observacoes || '-' }}</div>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

