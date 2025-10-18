<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UserFormModal from './UserFormModal.vue';
import axios from '@/bootstrap';
import { useToast } from '@/composables/useToast';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import type { PageProps } from '@/types/page';

type RoleOption = {
  id: number;
  name: string;
  slug: string | null;
  description?: string | null;
  is_system?: boolean;
  permissions: string[];
};

type PermissionOption = {
  name: string;
  description?: string | null;
};

type UserRow = {
  id: number;
  nome: string;
  username: string;
  email: string | null;
  ativo: boolean;
  role_id: number | null;
  roles: Array<{ id: number; name: string; slug: string | null }>;
  direct_permissions: string[];
  custom_permissions: string[];
  all_permissions: string[];
  last_login_at: string | null;
  created_at: string | null;
  avatar_url: string | null;
};

type Meta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

const props = defineProps<{
  available_roles: RoleOption[];
  available_permissions: PermissionOption[];
}>();

const toast = useToast();
const page = usePage<PageProps>();
const emailRegex = /[^\s@]+@[^\s@]+\.[^\s@]+/;
const currentUserId = computed<number | null>(() => {
  const authUser = page.props.auth?.user;
  return typeof authUser?.id === 'number' ? authUser.id : null;
});

const filters = reactive({
  search: '',
  status: 'all',
  role: '',
  per_page: 15,
});

const users = ref<UserRow[]>([]);
const meta = ref<Meta | null>(null);
const loading = ref(false);
const currentPage = ref(1);

const showFormModal = ref(false);
const modalMode = ref<'create' | 'edit'>('create');
const selectedUser = ref<UserRow | null>(null);

async function fetchUsers(pageNumber = 1): Promise<void> {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/admin/users', {
      params: {
        page: pageNumber,
        search: filters.search || undefined,
        status: filters.status !== 'all' ? filters.status : undefined,
        role: filters.role || undefined,
        per_page: filters.per_page,
      },
    });

    users.value = Array.isArray(data?.data) ? data.data : [];
    meta.value = data?.meta ?? null;
    currentPage.value = pageNumber;
  } catch (error) {
    console.error(error);
    toast.error('Não foi possível carregar a lista de usuários.');
  } finally {
    loading.value = false;
  }
}

watch(
  () => [filters.search, filters.status, filters.role, filters.per_page],
  () => {
    currentPage.value = 1;
    void fetchUsers(1);
  },
  { deep: true }
);

onMounted(() => {
  void fetchUsers();
});

function handleOpenCreate(): void {
  modalMode.value = 'create';
  selectedUser.value = null;
  showFormModal.value = true;
}

function handleOpenEdit(user: UserRow): void {
  modalMode.value = 'edit';
  selectedUser.value = user;
  showFormModal.value = true;
}

function handleSaved(): void {
  showFormModal.value = false;
  void fetchUsers(currentPage.value);
}

async function toggleActive(user: UserRow): Promise<void> {
  if (currentUserId.value === user.id) {
    toast.error('Você não pode alterar o status do próprio usuário.');
    return;
  }

  try {
    const { data } = await axios.patch(`/api/admin/users/${user.id}/toggle-active`);
    const updated: UserRow = data?.data ?? data;
    users.value = users.value.map((item) => (item.id === user.id ? updated : item));
    toast.success(`Usuário ${updated.ativo ? 'ativado' : 'inativado'} com sucesso.`);
  } catch (error) {
    console.error(error);
    const message = error?.response?.data?.message ?? 'Não foi possível alterar o status do usuário.';
    toast.error(message);
  }
}

function canSendResetLink(user: UserRow): boolean {
  const candidate = (user.email ?? '').trim() || user.username.trim();
  return candidate !== '' && emailRegex.test(candidate);
}

async function sendResetLink(user: UserRow): Promise<void> {
  if (!canSendResetLink(user)) {
    toast.error('Configure um e-mail válido antes de enviar o link.');
    return;
  }

  try {
    await axios.post(`/api/admin/users/${user.id}/send-reset-link`);
    toast.success('Solicitação de redefinição de senha enviada.');
  } catch (error) {
    console.error(error);
    const message = error?.response?.data?.errors?.user?.[0] ?? error?.response?.data?.message ?? 'Não foi possível enviar o link.';
    toast.error(message);
  }
}

function gotoPage(pageNumber: number): void {
  if (!meta.value) return;
  if (pageNumber < 1 || pageNumber > meta.value.last_page) return;
  void fetchUsers(pageNumber);
}

const statusOptions = [
  { value: 'all', label: 'Todos' },
  { value: 'active', label: 'Ativos' },
  { value: 'inactive', label: 'Inativos' },
];

const perPageOptions = [10, 15, 25, 50];

const hasPagination = computed(() => (meta.value?.last_page ?? 1) > 1);
</script>

<template>
  <AuthenticatedLayout title="Administração">
    <div class="space-y-6 text-slate-100">
      <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-white">Usuários</h1>
          <p class="text-sm text-slate-400">Gerencie usuários, papéis e permissões individuais.</p>
        </div>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:opacity-60"
          @click="handleOpenCreate"
        >
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          Novo usuário
        </button>
      </header>

      <section class="rounded-2xl border border-slate-800 bg-slate-950/70 p-5 shadow-inner shadow-black/20">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div class="flex flex-col gap-2">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Buscar</label>
            <input
              v-model="filters.search"
              type="search"
              placeholder="Nome ou usuário"
              class="w-full rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Status</label>
            <select v-model="filters.status" class="w-full rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
              <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Papel</label>
            <select v-model="filters.role" class="w-full rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
              <option value="">Todos</option>
              <option v-for="role in props.available_roles" :key="role.id" :value="role.id">
                {{ role.name }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Itens por página</label>
            <select v-model.number="filters.per_page" class="w-full rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
              <option v-for="option in perPageOptions" :key="option" :value="option">{{ option }}</option>
            </select>
          </div>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-800 bg-slate-950/70 p-5 shadow-inner shadow-black/20">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-800 text-sm">
            <thead class="bg-slate-900/70 text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th class="px-4 py-3 text-left font-semibold">Nome</th>
                <th class="px-4 py-3 text-left font-semibold">Usuário</th>
                <th class="px-4 py-3 text-left font-semibold">Papéis</th>
                <th class="px-4 py-3 text-left font-semibold">Status</th>
                <th class="px-4 py-3 text-left font-semibold">Último acesso</th>
                <th class="px-4 py-3 text-right font-semibold">Ações</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
              <tr v-if="loading">
                <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">Carregando usuários...</td>
              </tr>
              <tr v-else-if="users.length === 0">
                <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">Nenhum usuário encontrado para os filtros selecionados.</td>
              </tr>
              <tr v-for="user in users" :key="user.id" class="transition hover:bg-slate-900/60">
                <td class="px-4 py-3 font-medium text-white">{{ user.nome }}</td>
                <td class="px-4 py-3 text-slate-300">{{ user.username }}</td>
                <td class="px-4 py-3">
                  <div class="flex flex-wrap gap-2">
                    <span
                      v-for="role in user.roles"
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
                      user.ativo ? 'bg-emerald-500/15 text-emerald-300' : 'bg-rose-500/15 text-rose-300',
                    ]"
                  >
                    {{ user.ativo ? 'Ativo' : 'Inativo' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-slate-300">{{ user.last_login_at ? new Date(user.last_login_at).toLocaleString('pt-BR') : '—' }}</td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      type="button"
                      class="rounded-lg border border-slate-700 px-3 py-1 text-xs text-slate-200 transition hover:bg-slate-800/70 disabled:opacity-60"
                      :disabled="!canSendResetLink(user)"
                      @click="sendResetLink(user)"
                    >
                      Enviar link
                    </button>
                    <button
                      type="button"
                      class="rounded-lg border border-slate-700 px-3 py-1 text-xs text-slate-200 transition hover:bg-slate-800/70"
                      @click="handleOpenEdit(user)"
                    >
                      Editar
                    </button>
                    <button
                      type="button"
                      class="rounded-lg border border-slate-700 px-3 py-1 text-xs text-slate-200 transition hover:bg-slate-800/70 disabled:opacity-60"
                      :disabled="currentUserId === user.id"
                      @click="toggleActive(user)"
                    >
                      {{ user.ativo ? 'Desativar' : 'Ativar' }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="hasPagination" class="mt-4 flex flex-col gap-3 border-t border-slate-800 pt-4 text-sm text-slate-300 sm:flex-row sm:items-center sm:justify-between">
          <div>
            Página {{ meta?.current_page }} de {{ meta?.last_page }} — {{ meta?.total }} usuários
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded-lg border border-slate-700 px-3 py-1 text-xs text-slate-200 transition hover:bg-slate-800/70 disabled:opacity-60"
              :disabled="meta?.current_page === 1"
              @click="gotoPage((meta?.current_page ?? 2) - 1)"
            >
              Anterior
            </button>
            <button
              type="button"
              class="rounded-lg border border-slate-700 px-3 py-1 text-xs text-slate-200 transition hover:bg-slate-800/70 disabled:opacity-60"
              :disabled="meta?.current_page === meta?.last_page"
              @click="gotoPage((meta?.current_page ?? 0) + 1)"
            >
              Próxima
            </button>
          </div>
        </div>
      </section>

      <UserFormModal
        :show="showFormModal"
        :mode="modalMode"
        :user="selectedUser"
        :roles="props.available_roles"
        :permissions="props.available_permissions"
        :current-user-id="currentUserId"
        @close="showFormModal = false"
        @saved="handleSaved"
      />
    </div>
  </AuthenticatedLayout>
</template>
