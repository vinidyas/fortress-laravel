<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from '@/bootstrap';
import { useToast } from '@/composables/useToast';
import { computed, onMounted, reactive, ref } from 'vue';

type StatSummary = {
  users_total: number;
  users_active: number;
  users_inactive: number;
  roles_total: number;
  permissions_total: number;
};

type RecentUser = {
  id: number;
  nome: string;
  username: string;
  ativo: boolean;
  roles: Array<{ id: number; name: string; slug: string | null }>;
  created_at: string | null;
};

const props = defineProps<{
  stats?: StatSummary;
  recent_users?: RecentUser[];
}>();

const toast = useToast();
const loading = ref(false);
const stats = reactive<StatSummary>({
  users_total: props.stats?.users_total ?? 0,
  users_active: props.stats?.users_active ?? 0,
  users_inactive: props.stats?.users_inactive ?? 0,
  roles_total: props.stats?.roles_total ?? 0,
  permissions_total: props.stats?.permissions_total ?? 0,
});
const recentUsers = ref<RecentUser[]>(props.recent_users ?? []);

const inactiveRatio = computed(() => {
  if (stats.users_total === 0) return 0;
  return Math.round((stats.users_inactive / stats.users_total) * 100);
});

async function refreshDashboard(): Promise<void> {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/admin/dashboard');
    const counters = data?.counters ?? {};
    stats.users_total = counters.users_total ?? 0;
    stats.users_active = counters.users_active ?? 0;
    stats.users_inactive = counters.users_inactive ?? 0;
    stats.roles_total = counters.roles_total ?? 0;
    stats.permissions_total = counters.permissions_total ?? 0;
    recentUsers.value = Array.isArray(data?.recent_users?.data ?? data?.recent_users)
      ? (data.recent_users.data ?? data.recent_users)
      : [];
  } catch (error) {
    console.error(error);
    toast.error('Não foi possível atualizar os dados do painel.');
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  if (!props.stats) {
    void refreshDashboard();
  }
});
</script>

<template>
  <AuthenticatedLayout title="Administração">
    <div class="space-y-6 text-slate-100">
      <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-white">Painel administrativo</h1>
          <p class="text-sm text-slate-400">Visão geral do ambiente administrativo e dos usuários.</p>
        </div>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:opacity-60"
          :disabled="loading"
          @click="refreshDashboard"
        >
          <svg
            v-if="loading"
            class="h-4 w-4 animate-spin"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8 8 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8 8 0 01-15.357-2m15.357 2H15" />
          </svg>
          <span>{{ loading ? 'Atualizando...' : 'Atualizar' }}</span>
        </button>
      </header>

      <section class="grid gap-4 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4 shadow-inner shadow-black/20">
          <p class="text-xs uppercase tracking-wide text-slate-400">Usuários ativos</p>
          <div class="mt-2 flex items-baseline gap-2">
            <strong class="text-3xl font-semibold text-emerald-400">{{ stats.users_active }}</strong>
            <span class="text-sm text-slate-400">de {{ stats.users_total }}</span>
          </div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4 shadow-inner shadow-black/20">
          <p class="text-xs uppercase tracking-wide text-slate-400">Usuários inativos</p>
          <div class="mt-2 flex items-baseline gap-2">
            <strong class="text-3xl font-semibold text-rose-400">{{ stats.users_inactive }}</strong>
            <span class="text-sm text-slate-400">({{ inactiveRatio }}%)</span>
          </div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4 shadow-inner shadow-black/20">
          <p class="text-xs uppercase tracking-wide text-slate-400">Papéis cadastrados</p>
          <div class="mt-2 text-3xl font-semibold text-white">{{ stats.roles_total }}</div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4 shadow-inner shadow-black/20">
          <p class="text-xs uppercase tracking-wide text-slate-400">Permissões disponíveis</p>
          <div class="mt-2 text-3xl font-semibold text-white">{{ stats.permissions_total }}</div>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-800 bg-slate-950/70 p-5 shadow-inner shadow-black/20">
        <header class="mb-4 flex items-center justify-between">
          <div>
            <h2 class="text-lg font-semibold text-white">Últimos usuários cadastrados</h2>
            <p class="text-sm text-slate-400">Acompanhamento rápido dos últimos acessos criados.</p>
          </div>
        </header>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-800 text-sm">
            <thead class="bg-slate-900/70 text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th class="px-4 py-2 text-left font-medium">Nome</th>
                <th class="px-4 py-2 text-left font-medium">Usuário</th>
                <th class="px-4 py-2 text-left font-medium">Papéis</th>
                <th class="px-4 py-2 text-left font-medium">Status</th>
                <th class="px-4 py-2 text-left font-medium">Criado em</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
              <tr v-if="recentUsers.length === 0">
                <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">Nenhum usuário recente encontrado.</td>
              </tr>
              <tr v-for="item in recentUsers" :key="item.id" class="transition hover:bg-slate-900/60">
                <td class="px-4 py-3 font-medium text-white">{{ item.nome }}</td>
                <td class="px-4 py-3 text-slate-300">{{ item.username }}</td>
                <td class="px-4 py-3">
                  <div class="flex flex-wrap gap-2">
                    <span
                      v-for="role in item.roles"
                      :key="role.id"
                      class="inline-flex items-center rounded-full bg-slate-800/70 px-2.5 py-0.5 text-xs text-slate-200"
                    >
                      {{ role.name }}
                    </span>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <span
                    :class="[
                      'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                      item.ativo ? 'bg-emerald-500/15 text-emerald-300' : 'bg-rose-500/15 text-rose-300',
                    ]"
                  >
                    {{ item.ativo ? 'Ativo' : 'Inativo' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-slate-300">
                  {{ item.created_at ? new Date(item.created_at).toLocaleString('pt-BR') : '-' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </AuthenticatedLayout>
</template>
