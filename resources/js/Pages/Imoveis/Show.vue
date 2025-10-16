<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from '@/bootstrap';
import { onMounted, ref } from 'vue';

const props = defineProps<{ imovelId: number }>();

const loading = ref(true);
const error = ref('');
const imovel = ref<Record<string, any> | null>(null);

onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/imoveis/${props.imovelId}`);
    imovel.value = data.data ?? null;
  } catch (e) {
    error.value = 'Não foi possível carregar o imóvel.';
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <AuthenticatedLayout title="Imóvel">
    <div class="space-y-6 text-slate-100">
      <header class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold">Imóvel</h2>
        <a :href="`/imoveis/${props.imovelId}`" class="rounded-lg border border-indigo-500/40 bg-indigo-500/20 px-3 py-2 text-sm font-semibold text-indigo-200 transition hover:border-indigo-400 hover:bg-indigo-500/30 hover:text-white">Editar</a>
      </header>

      <div v-if="error" class="rounded-xl border border-rose-500/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-200">{{ error }}</div>
      <div v-else-if="loading" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-6">Carregando...</div>
      <div v-else-if="imovel" class="grid gap-6 md:grid-cols-2">
        <section class="space-y-2 rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
          <h3 class="text-sm font-semibold text-slate-300">Identificação</h3>
          <div><span class="text-slate-400">Código:</span> <span class="text-white">{{ imovel.codigo || '-' }}</span></div>
          <div><span class="text-slate-400">Tipo:</span> <span class="text-white">{{ imovel.tipo_imovel || '-' }}</span></div>
          <div><span class="text-slate-400">Disponibilidade:</span> <span class="text-white">{{ imovel.disponibilidade || '-' }}</span></div>
        </section>
        <section class="space-y-2 rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
          <h3 class="text-sm font-semibold text-slate-300">Endereço</h3>
          <div><span class="text-slate-400">Cidade/Bairro:</span> <span class="text-white">{{ imovel.enderecos?.cidade || '-' }}/{{ imovel.enderecos?.bairro || '-' }}</span></div>
          <div><span class="text-slate-400">Rua:</span> <span class="text-white">{{ imovel.enderecos?.rua || imovel.enderecos?.logradouro || '-' }}</span></div>
          <div><span class="text-slate-400">Número:</span> <span class="text-white">{{ imovel.enderecos?.numero || '-' }}</span></div>
        </section>
        <section class="space-y-2 rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
          <h3 class="text-sm font-semibold text-slate-300">Valores</h3>
          <div><span class="text-slate-400">Locação:</span> <span class="text-white">{{ imovel.valores?.valor_locacao ?? '-' }}</span></div>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

