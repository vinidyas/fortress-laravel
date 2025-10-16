<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ImovelForm from '@/Components/Imoveis/ImovelForm.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ imovelId?: number | null }>();

const isEditing = computed(() => Boolean(props.imovelId));
const title = computed(() => (isEditing.value ? 'Editar imóvel' : 'Novo imóvel'));

function handleSaved(): void {
  router.visit('/imoveis');
}

function handleCancel(): void {
  router.visit('/imoveis');
}

function handleRequestCreateCondominio(): void {
  router.visit('/condominios/novo');
}
</script>

<template>
  <AuthenticatedLayout :title="title">
    <div class="space-y-6 text-slate-100">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-2xl font-semibold text-white">{{ title }}</h2>
          <p class="text-sm text-slate-400">
            {{ isEditing ? 'Atualize as informações do imóvel.' : 'Informe os dados para cadastrar um novo imóvel.' }}
          </p>
        </div>
        <Link
          href="/imoveis"
          class="inline-flex items-center justify-center rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800/60"
        >
          Voltar
        </Link>
      </div>

      <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
        <ImovelForm
          :mode="isEditing ? 'edit' : 'create'"
          :imovel-id="props.imovelId ?? null"
          @saved="handleSaved"
          @cancel="handleCancel"
          @request-create-condominio="handleRequestCreateCondominio"
        />
      </section>
    </div>
  </AuthenticatedLayout>
</template>
