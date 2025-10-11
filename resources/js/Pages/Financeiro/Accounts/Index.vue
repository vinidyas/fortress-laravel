<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AccountFormModal from '@/Components/Financeiro/AccountFormModal.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
  accounts: {
    data: Array<{
      id: number;
      nome: string;
      tipo: string;
      saldo_inicial: string | number;
      ativo: boolean;
    }>;
    links: Array<{ url: string | null; label: string; active: boolean }>;
  };
  can: { create: boolean; update: boolean; delete: boolean };
}>();

const showCreateModal = ref(false);

const tipoLabel = (tipo: string) =>
  ({
    conta_corrente: 'Conta corrente',
    caixa: 'Caixa',
    outro: 'Outro',
  })[tipo] ?? tipo;

const openModal = () => {
  showCreateModal.value = true;
};

const closeModal = () => {
  showCreateModal.value = false;
};

const handleCreated = () => {
  showCreateModal.value = false;
  router.visit('/financeiro/contas', {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};
</script>

<template>
  <AuthenticatedLayout title="Contas financeiras">
    <Head title="Contas financeiras" />

    <section
      class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
    >
      <header class="mb-4 flex items-center justify-between">
        <div>
          <h1 class="text-lg font-semibold text-white">Contas financeiras</h1>
          <p class="text-sm text-slate-400">Gerencie as contas utilizadas nos lancamentos.</p>
        </div>
        <button
          v-if="props.can.create"
          type="button"
          class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500"
          @click="openModal"
        >
          <svg
            class="h-4 w-4"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16M4 12h16" />
          </svg>
          Nova conta
        </button>
      </header>

      <div class="overflow-hidden rounded-xl border border-slate-800">
        <table class="min-w-full divide-y divide-slate-800 text-sm text-slate-100">
          <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
            <tr>
              <th class="px-4 py-3 text-left">Nome</th>
              <th class="px-4 py-3 text-left">Tipo</th>
              <th class="px-4 py-3 text-left">Saldo inicial</th>
              <th class="px-4 py-3 text-left">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-if="props.accounts.data.length === 0">
              <td colspan="4" class="px-4 py-6 text-center text-slate-400">
                Nenhuma conta cadastrada.
              </td>
            </tr>
            <tr v-for="account in props.accounts.data" :key="account.id">
              <td class="px-4 py-3 text-white">{{ account.nome }}</td>
              <td class="px-4 py-3 text-slate-300">{{ tipoLabel(account.tipo) }}</td>
              <td class="px-4 py-3 text-slate-300">
                {{
                  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(
                    Number(account.saldo_inicial ?? 0)
                  )
                }}
              </td>
              <td class="px-4 py-3">
                <span
                  :class="[
                    'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                    account.ativo
                      ? 'bg-emerald-500/10 border border-emerald-500/40 text-emerald-200'
                      : 'bg-slate-700/40 border border-slate-700 text-slate-300',
                  ]"
                >
                  {{ account.ativo ? 'Ativa' : 'Inativa' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <AccountFormModal :show="showCreateModal" @close="closeModal" @created="handleCreated" />
  </AuthenticatedLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
