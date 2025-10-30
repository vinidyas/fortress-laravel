<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CondominioForm, {
  type CondominioSavedPayload,
} from '@/Components/Condominios/CondominioForm.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ condominioId?: number | null }>();

const isEditing = computed(() => Boolean(props.condominioId));
const title = computed(() => (isEditing.value ? 'Editar condomínio' : 'Novo condomínio'));

function handleSaved(_payload: CondominioSavedPayload): void {
  router.visit('/condominios');
}

function handleCancel(): void {
  router.visit('/condominios');
}
</script>

<template>
  <AuthenticatedLayout :title="title">
    <div class="mb-6 flex items-center justify-between">
      <h2 class="text-2xl font-semibold text-slate-100">{{ title }}</h2>
      <Link class="text-sm font-semibold text-indigo-300 hover:text-indigo-200" href="/condominios">
        Voltar
      </Link>
    </div>

    <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40">
      <CondominioForm
        :mode="isEditing ? 'edit' : 'create'"
        :condominio-id="props.condominioId ?? null"
        @saved="handleSaved"
        @cancel="handleCancel"
      />
    </section>
  </AuthenticatedLayout>
</template>
