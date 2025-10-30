<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CostCenterFormModal from '@/Components/Financeiro/CostCenterFormModal.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import type { AxiosError } from 'axios';
import { computed, ref } from 'vue';
import { useToast } from '@/composables/useToast';

type TipoOption = 'fixo' | 'variavel' | 'investimento';

type CostCenterNode = {
  id: number;
  nome: string;
  descricao: string | null;
  codigo: string;
  parent_id: number | null;
  tipo: TipoOption | string;
  ativo: boolean;
  orcamento_anual: string | number | null;
  children?: CostCenterNode[];
};

type ParentOption = {
  id: number;
  nome: string;
  codigo: string;
  parent_id: number | null;
  depth: number;
};

type CanFlags = {
  create: boolean;
  update: boolean;
  delete: boolean;
  export: boolean;
  import: boolean;
};

const props = defineProps<{
  centers: CostCenterNode[];
  parentOptions: ParentOption[];
  can: CanFlags;
}>();

const toast = useToast();
const showFormModal = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const currentCenter = ref<CostCenterNode | null>(null);
const deletingId = ref<number | null>(null);
const uploading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const flattenTree = (nodes: CostCenterNode[], depth = 0, parent: CostCenterNode | null = null) => {
  const rows: Array<{ node: CostCenterNode; depth: number; parentName: string | null }> = [];
  nodes.forEach((node) => {
    rows.push({ node, depth, parentName: parent?.nome ?? null });
    if (node.children?.length) {
      rows.push(...flattenTree(node.children, depth + 1, node));
    }
  });

  return rows;
};

const flattenedCenters = computed(() => flattenTree(props.centers ?? []));
const parentOptions = computed(() => props.parentOptions ?? []);

const normalizeTipo = (tipo: string | null | undefined): TipoOption => {
  const normalized = String(tipo ?? '').toLowerCase();
  if (normalized === 'fixo' || normalized === 'variavel' || normalized === 'investimento') {
    return normalized;
  }

  return 'variavel';
};

const currentCenterForModal = computed(() => {
  if (!currentCenter.value) {
    return null;
  }

  return {
    ...currentCenter.value,
    tipo: normalizeTipo(currentCenter.value.tipo),
  };
});

const openCreateModal = () => {
  if (!props.can.create) {
    return;
  }

  formMode.value = 'create';
  currentCenter.value = null;
  showFormModal.value = true;
};

const openEditModal = (center: CostCenterNode) => {
  if (!props.can.update) {
    return;
  }

  formMode.value = 'edit';
  currentCenter.value = { ...center };
  showFormModal.value = true;
};

const closeModal = () => {
  showFormModal.value = false;
};

const refreshList = () => {
  router.visit('/financeiro/centros', {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const handleSaved = () => {
  showFormModal.value = false;
  refreshList();
};

const handleDelete = async (center: CostCenterNode) => {
  if (!props.can.delete) {
    return;
  }

  const confirmed = window.confirm(`Deseja realmente remover o centro "${center.nome}"?`);
  
  if (!confirmed) {
    return;
  }

  deletingId.value = center.id;

  try {
    // A rota da API deve incluir o ID do centro a ser deletado
    const response = await axios.delete(`/api/financeiro/cost-centers/${center.id}`);
    toast.success(response.data?.message ?? 'Centro de custo removido com sucesso.');
    refreshList();
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string }>;
    const message =
      axiosError.response?.data?.message ?? 'Não foi possível remover o centro de custo.';
    toast.error(message);
  } finally {
    deletingId.value = null;
  }
};

const triggerImport = () => {
  if (!props.can.import) {
    return;
  }

  fileInput.value?.click();
};

const handleFileChange = async (event: Event) => {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) {
    return;
  }

  uploading.value = true;

  try {
    const formData = new FormData();
    formData.append('file', file);

    const response = await axios.post('/api/financeiro/cost-centers/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    toast.success(response.data?.message ?? 'Importacao realizada com sucesso.');
    refreshList();
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string }>;
    const message =
      axiosError.response?.data?.message ?? 'Falha ao importar centros de custo.';
    toast.error(message);
  } finally {
    uploading.value = false;
    if (fileInput.value) {
      fileInput.value.value = '';
    }
  }
};

const downloadCenters = async () => {
  if (!props.can.export) {
    return;
  }

  try {
    const response = await axios.get('/api/financeiro/cost-centers/export', {
      responseType: 'blob',
    });

    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'centros-de-custo.xlsx';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string }>;
    const message =
      axiosError.response?.data?.message ?? 'Falha ao exportar centros de custo.';
    toast.error(message);
  }
};

const tipoLabel = (tipo: string) =>
  ({
    fixo: 'Fixo',
    variavel: 'Variável',
    investimento: 'Investimento',
  }[tipo] ?? tipo);

const formatCurrency = (value: string | number | null | undefined) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(
    Number(value ?? 0)
  );
</script>

<template>
  <AuthenticatedLayout title="Centros de custo">
    <Head title="Centros de custo" />

    <section
      class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40"
    >
      <header class="mb-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-lg font-semibold text-white">Centros de custo</h1>
          <p class="text-sm text-slate-400">Organize seus centros principais e subcentros.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button
            v-if="props.can.import"
            type="button"
            class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-800 disabled:opacity-60"
            :disabled="uploading"
            @click="triggerImport"
          >
            {{ uploading ? 'Importando...' : 'Importar XLSX' }}
          </button>
          <button
            v-if="props.can.export"
            type="button"
            class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-800"
            @click="downloadCenters"
          >
            Exportar XLSX
          </button>
          <button
            v-if="props.can.create"
            type="button"
            class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500"
            @click="openCreateModal"
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
            Novo centro
          </button>
        </div>
      </header>

      <input
        ref="fileInput"
        type="file"
        accept=".xlsx"
        class="hidden"
        @change="handleFileChange"
      />

      <div class="overflow-hidden rounded-xl border border-slate-800">
        <table class="min-w-full divide-y divide-slate-800 text-sm text-slate-100">
          <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-400">
            <tr>
              <th class="px-4 py-3 text-left">Código</th>
              <th class="px-4 py-3 text-left">Centro</th>
              <th class="px-4 py-3 text-left">Tipo / Orçamento</th>
              <th class="px-4 py-3 text-left">Descrição</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th
                v-if="props.can.update || props.can.delete"
                class="px-4 py-3 text-right"
              >
                Ações
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-if="flattenedCenters.length === 0">
              <td
                :colspan="(props.can.update || props.can.delete) ? 6 : 5"
                class="px-4 py-6 text-center text-slate-400"
              >
                Nenhum centro cadastrado.
              </td>
            </tr>
            <tr v-for="row in flattenedCenters" :key="row.node.id" class="hover:bg-slate-900/60">
              <td class="px-4 py-3 text-slate-200">{{ row.node.codigo }}</td>
              <td class="px-4 py-3 text-white">
                <div :style="{ paddingLeft: (row.depth * 20) + 'px' }">
                  {{ row.node.nome }}
                </div>
                <div v-if="row.parentName" class="pl-5 text-xs text-slate-500">
                  Pai: {{ row.parentName }}
                </div>
              </td>
              <td class="px-4 py-3 text-slate-300">
                <div>{{ tipoLabel(row.node.tipo) }}</div>
    <div class="text-xs text-slate-500">
                  Orçamento: {{ formatCurrency(row.node.orcamento_anual) }}
                </div>
              </td>
              <td class="px-4 py-3 text-slate-300">{{ row.node.descricao ?? '-' }}</td>
              <td class="px-4 py-3">
                <span
                  :class="[
                    'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                    row.node.ativo
                      ? 'bg-emerald-500/10 border border-emerald-500/40 text-emerald-200'
                      : 'bg-slate-700/40 border border-slate-700 text-slate-300',
                  ]"
                >
                  {{ row.node.ativo ? 'Ativo' : 'Inativo' }}
                </span>
              </td>
              <td
                v-if="props.can.update || props.can.delete"
                class="px-4 py-3 text-right"
              >
                <div class="flex justify-end gap-2">
                  <button
                    v-if="props.can.update"
                    type="button"
                    class="rounded-lg border border-slate-700 px-3 py-1 text-xs text-slate-200 hover:bg-slate-800"
                    @click="openEditModal(row.node)"
                  >
                    Editar
                  </button>
                  <button
                    v-if="props.can.delete"
                    type="button"
                    class="rounded-lg border border-rose-500/60 px-3 py-1 text-xs text-rose-200 hover:bg-rose-500/10 disabled:opacity-60"
                    :disabled="deletingId === row.node.id"
                    @click="handleDelete(row.node)"
                  >
                    {{ deletingId === row.node.id ? 'Removendo...' : 'Excluir' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <CostCenterFormModal
      :show="showFormModal"
      :mode="formMode"
      :center="currentCenterForModal"
      :parents="parentOptions"
      @close="closeModal"
      @saved="handleSaved"
    />
  </AuthenticatedLayout>
</template>
