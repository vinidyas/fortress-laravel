<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RoleFormModal from './RoleFormModal.vue';
import axios from '@/bootstrap';
import { useToast } from '@/composables/useToast';
import { onMounted, reactive, ref } from 'vue';

type PermissionOption = {
  name: string;
  description?: string | null;
};

type RoleRow = {
  id: number;
  name: string;
  slug: string | null;
  description?: string | null;
  is_system?: boolean;
  permissions: PermissionOption[];
};

const props = defineProps<{
  available_permissions: PermissionOption[];
}>();

const toast = useToast();
const roles = ref<RoleRow[]>([]);
const permissions = ref<PermissionOption[]>(props.available_permissions ?? []);
const loading = ref(false);

const showModal = ref(false);
const modalMode = ref<'create' | 'edit'>('create');
const selectedRole = ref<RoleRow | null>(null);

async function fetchRoles(): Promise<void> {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/admin/roles');
    roles.value = Array.isArray(data?.data) ? data.data : [];
    if (Array.isArray(data?.permissions?.data)) {
      permissions.value = data.permissions.data;
    } else if (Array.isArray(data?.permissions)) {
      permissions.value = data.permissions;
    }
  } catch (error) {
    console.error(error);
    toast.error('Não foi possível carregar os papéis.');
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  void fetchRoles();
});

function openCreate(): void {
  modalMode.value = 'create';
  selectedRole.value = null;
  showModal.value = true;
}

function openEdit(role: RoleRow): void {
  modalMode.value = 'edit';
  selectedRole.value = role;
  showModal.value = true;
}

async function removeRole(role: RoleRow): Promise<void> {
  if (role.is_system) {
    toast.error('Este papel é protegido e não pode ser removido.');
    return;
  }

  const confirmed = window.confirm(`Tem certeza que deseja remover o papel "${role.name}"?`);
  if (!confirmed) return;

  try {
    await axios.delete(`/api/admin/roles/${role.id}`);
    toast.success('Papel removido com sucesso.');
    await fetchRoles();
  } catch (error: any) {
    console.error(error);
    const message = error?.response?.data?.message ?? 'Não foi possível remover o papel.';
    toast.error(message);
  }
}

function handleSaved(): void {
  showModal.value = false;
  void fetchRoles();
}

function permissionSummary(role: RoleRow): string {
  const count = role.permissions.length;
  if (count === 0) return 'Nenhuma';
  if (count <= 3) return role.permissions.map((p) => p.name).join(', ');
  const listed = role.permissions.slice(0, 3).map((p) => p.name);
  return `${listed.join(', ')} +${count - listed.length}`;
}
</script>

<template>
  <AuthenticatedLayout title="Administração">
    <div class="space-y-6 text-slate-100">
      <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-white">Papéis &amp; Permissões</h1>
          <p class="text-sm text-slate-400">Organize os papéis disponíveis e quais permissões cada um oferece.</p>
        </div>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:opacity-60"
          @click="openCreate"
        >
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          Novo papel
        </button>
      </header>

      <section class="rounded-2xl border border-slate-800 bg-slate-950/70 p-5 shadow-inner shadow-black/20">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-800 text-sm">
            <thead class="bg-slate-900/70 text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th class="px-4 py-3 text-left font-semibold">Nome</th>
                <th class="px-4 py-3 text-left font-semibold">Slug</th>
                <th class="px-4 py-3 text-left font-semibold">Descrição</th>
                <th class="px-4 py-3 text-left font-semibold">Permissões</th>
                <th class="px-4 py-3 text-left font-semibold">Tipo</th>
                <th class="px-4 py-3 text-right font-semibold">Ações</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
              <tr v-if="loading">
                <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">Carregando papéis...</td>
              </tr>
              <tr v-else-if="roles.length === 0">
                <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">Nenhum papel cadastrado até o momento.</td>
              </tr>
              <tr v-for="role in roles" :key="role.id" class="transition hover:bg-slate-900/60">
                <td class="px-4 py-3 font-medium text-white">{{ role.name }}</td>
                <td class="px-4 py-3 text-slate-300">{{ role.slug ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-300">{{ role.description ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-300">{{ permissionSummary(role) }}</td>
                <td class="px-4 py-3 text-slate-300">
                  <span
                    :class="[
                      'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                      role.is_system ? 'bg-amber-500/15 text-amber-300' : 'bg-slate-800/60 text-slate-200',
                    ]"
                  >
                    {{ role.is_system ? 'Sistema' : 'Personalizado' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      type="button"
                      class="rounded-lg border border-slate-700 px-3 py-1 text-xs text-slate-200 transition hover:bg-slate-800/70"
                      @click="openEdit(role)"
                    >
                      Editar
                    </button>
                    <button
                      type="button"
                      class="rounded-lg border border-slate-700 px-3 py-1 text-xs text-slate-200 transition hover:bg-slate-800/70 disabled:opacity-60"
                      :disabled="role.is_system"
                      @click="removeRole(role)"
                    >
                      Remover
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <RoleFormModal
        :show="showModal"
        :mode="modalMode"
        :role="selectedRole"
        :permissions="permissions"
        @close="showModal = false"
        @saved="handleSaved"
      />
    </div>
  </AuthenticatedLayout>
</template>
