<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from '@/bootstrap';
import { ref, onMounted, watch, computed } from 'vue';

type PortalTenant = {
  id: number;
  nome: string;
  email: string | null;
  telefone: string | null;
  cpf_cnpj: string | null;
  has_portal_access: boolean;
  portal_user: {
    id: number;
    email: string | null;
    username: string | null;
    last_login_at: string | null;
  } | null;
};

type PaginatedResponse = {
  data: PortalTenant[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

const tenants = ref<PortalTenant[]>([]);
const meta = ref<PaginatedResponse['meta'] | null>(null);
const loading = ref(false);
const search = ref('');
const message = ref<string | null>(null);
const error = ref<string | null>(null);
const inviteLoadingId = ref<number | null>(null);
const debounceTimer = ref<number | undefined>();

const hasPagination = computed(() => {
  if (!meta.value) return false;
  return meta.value.last_page > 1;
});

async function fetchTenants(page = 1) {
  loading.value = true;
  message.value = null;
  error.value = null;
  try {
    const { data } = await axios.get('/api/admin/portal/locatarios', {
      params: {
        search: search.value.trim() || undefined,
        page,
      },
    });

    tenants.value = (data?.data as PortalTenant[]) ?? [];
    meta.value = data?.meta ?? null;
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Não foi possível carregar os locatários.';
  } finally {
    loading.value = false;
  }
}

async function inviteTenant(tenant: PortalTenant) {
  if (!tenant.email) {
    error.value = 'Locatário sem e-mail cadastrado. Atualize o cadastro antes de enviar o convite.';
    return;
  }

  inviteLoadingId.value = tenant.id;
  message.value = null;
  error.value = null;

  try {
    await axios.post('/api/admin/portal/tenant-users', {
      pessoa_id: tenant.id,
      email: tenant.email,
    });

    message.value = tenant.has_portal_access
      ? 'Convite reenviado com sucesso.'
      : 'Convite enviado com sucesso.';

    await fetchTenants(meta.value?.current_page ?? 1);
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Não foi possível enviar o convite.';
  } finally {
    inviteLoadingId.value = null;
  }
}

function handleSearchChange() {
  if (debounceTimer.value) {
    window.clearTimeout(debounceTimer.value);
  }
  debounceTimer.value = window.setTimeout(() => fetchTenants(), 400);
}

function goToPage(page: number) {
  if (!meta.value) return;
  const bounded = Math.min(Math.max(page, 1), meta.value.last_page);
  if (bounded === meta.value.current_page) return;
  fetchTenants(bounded);
}

watch(search, handleSearchChange);

onMounted(() => {
  fetchTenants();
});
</script>

<template>
  <AuthenticatedLayout title="Locatários do Portal">
    <div class="flex flex-col gap-6">
      <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-slate-900">Locatários do Portal</h1>
          <p class="text-sm text-slate-500">
            Envie convites ou reenvie o link de definição de senha para os locatários com e-mail cadastrado.
          </p>
        </div>
        <div class="flex items-center gap-3">
          <input
            v-model="search"
            type="search"
            placeholder="Buscar por nome, e-mail ou CPF/CNPJ"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:w-80"
          />
        </div>
      </header>

      <div v-if="message" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ message }}
      </div>
      <div v-if="error" class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        {{ error }}
      </div>

      <div class="overflow-hidden rounded-xl border border-slate-200 shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50 text-left font-semibold text-slate-600">
            <tr>
              <th class="px-4 py-3">Nome</th>
              <th class="px-4 py-3">E-mail</th>
              <th class="px-4 py-3">Telefone</th>
              <th class="px-4 py-3">Acesso portal</th>
              <th class="px-4 py-3 text-right">Ação</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 bg-white">
            <tr v-if="loading">
              <td colspan="5" class="px-4 py-6 text-center text-slate-500">Carregando locatários...</td>
            </tr>
            <tr v-else-if="tenants.length === 0">
              <td colspan="5" class="px-4 py-6 text-center text-slate-500">
                Nenhum locatário encontrado com os filtros informados.
              </td>
            </tr>
            <tr v-for="tenant in tenants" :key="tenant.id">
              <td class="px-4 py-3">
                <div class="font-medium text-slate-900">{{ tenant.nome }}</div>
                <div class="text-xs text-slate-500">CPF/CNPJ: {{ tenant.cpf_cnpj || '—' }}</div>
              </td>
              <td class="px-4 py-3 text-slate-700">{{ tenant.email || '—' }}</td>
              <td class="px-4 py-3 text-slate-700">{{ tenant.telefone || '—' }}</td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold"
                  :class="tenant.has_portal_access
                    ? 'bg-emerald-100 text-emerald-700'
                    : 'bg-slate-100 text-slate-500'"
                >
                  {{ tenant.has_portal_access ? 'Convite enviado' : 'Não convidado' }}
                </span>
                <div v-if="tenant.portal_user?.last_login_at" class="mt-1 text-xs text-slate-500">
                  Último acesso: {{ new Date(tenant.portal_user.last_login_at).toLocaleString('pt-BR') }}
                </div>
              </td>
              <td class="px-4 py-3 text-right">
                <button
                  type="button"
                  class="inline-flex items-center justify-center rounded-md border border-indigo-500 bg-indigo-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:border-slate-400 disabled:bg-slate-400/70"
                  :disabled="inviteLoadingId === tenant.id || !tenant.email"
                  @click="inviteTenant(tenant)"
                >
                  <span v-if="inviteLoadingId === tenant.id" class="h-4 w-4 animate-spin rounded-full border-2 border-white/60 border-t-transparent"></span>
                  <span v-else>{{ tenant.has_portal_access ? 'Reenviar convite' : 'Convidar' }}</span>
                </button>
                <div v-if="!tenant.email" class="mt-1 text-xs text-amber-600">
                  Cadastre um e-mail para habilitar o convite.
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="hasPagination" class="flex items-center justify-between text-sm text-slate-600">
        <div>
          Página {{ meta?.current_page }} de {{ meta?.last_page }} — {{ meta?.total }} locatário(s)
        </div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="loading || (meta?.current_page ?? 1) <= 1"
            @click="goToPage((meta?.current_page ?? 1) - 1)"
          >
            Anterior
          </button>
          <button
            type="button"
            class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="loading || (meta?.current_page ?? 1) >= (meta?.last_page ?? 1)"
            @click="goToPage((meta?.current_page ?? 1) + 1)"
          >
            Próxima
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
