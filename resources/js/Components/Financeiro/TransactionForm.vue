<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import axios from '@/bootstrap';
import { computed, reactive, ref } from 'vue';
import MoneyInput from '@/Components/Form/MoneyInput.vue';
import DatePicker from '@/Components/Form/DatePicker.vue';
import TransactionStatusBadge from '@/Components/Financeiro/TransactionStatusBadge.vue';
import { useToast } from '@/composables/useToast';

interface Option {
  id: number;
  nome: string;
}

type Status = 'pendente' | 'conciliado' | 'cancelado';
type Tipo = 'credito' | 'debito';

interface TransactionPayload {
  id?: number;
  account?: Option | null;
  cost_center?: Option | null;
  contrato?: { id: number; codigo_contrato?: string } | null;
  fatura?: { id: number; competencia?: string } | null;
  data_ocorrencia: string | null;
  descricao: string | null;
  tipo: Tipo;
  valor: string | number;
  status: Status;
}

export type { TransactionPayload, Option as TransactionOption };

const props = defineProps<{
  mode: 'create' | 'edit';
  transaction: TransactionPayload | null;
  accounts: Option[];
  costCenters: Option[];
  permissions: { update: boolean; delete: boolean; reconcile: boolean };
}>();

const isCreate = computed(() => props.mode === 'create');
const canEdit = computed(() => isCreate.value || props.permissions.update);

const form = reactive({
  account_id: props.transaction?.account?.id ?? null,
  cost_center_id: props.transaction?.cost_center?.id ?? null,
  tipo: (props.transaction?.tipo ?? 'credito') as Tipo,
  valor: props.transaction?.valor ? String(props.transaction.valor) : '',
  data_ocorrencia:
    props.transaction?.data_ocorrencia ?? new Date().toISOString().slice(0, 10),
  descricao: props.transaction?.descricao ?? '',
  contrato_id: props.transaction?.contrato?.id ?? null,
  fatura_id: props.transaction?.fatura?.id ?? null,
});

const errors = reactive<Record<string, string>>({});
const submitting = ref(false);
const toast = useToast();

const accountOptions = computed(() => props.accounts);
const costCenterOptions = computed(() => props.costCenters);

const resetErrors = () => {
  Object.keys(errors).forEach((k) => (errors[k] = ''));
};

const payloadFromForm = () => {
  const payload: Record<string, unknown> = {
    account_id: form.account_id,
    cost_center_id: form.cost_center_id,
    tipo: form.tipo,
    data_ocorrencia: form.data_ocorrencia,
    descricao: form.descricao || null,
    contrato_id: form.contrato_id || null,
    fatura_id: form.fatura_id || null,
  };

  const v = (form.valor ?? '').toString().trim();
  if (v !== '') {
    payload.valor = Number.parseFloat(v);
  }

  return payload;
};

const handleSubmit = async () => {
  if (!canEdit.value) return;

  resetErrors();
  submitting.value = true;

  try {
    const payload = payloadFromForm();
    if (isCreate.value) {
      await axios.post('/api/financeiro/transactions', payload);
      toast.success('Lançamento criado com sucesso.');
    } else if (props.transaction?.id) {
      await axios.put(`/api/financeiro/transactions/${props.transaction.id}`, payload);
      toast.success('Lançamento atualizado com sucesso.');
    }

    router.visit('/financeiro', { preserveState: false, preserveScroll: true });
  } catch (error: any) {
    if (error?.response?.status === 422) {
      const validation = error.response.data?.errors ?? {};
      Object.entries(validation).forEach(([field, messages]) => {
        errors[field] = Array.isArray(messages) ? String(messages[0]) : String(messages);
      });
      toast.error('Corrija os campos destacados e tente novamente.');
    } else {
      const message = error?.response?.data?.message ?? 'Não foi possível salvar o lançamento.';
      toast.error(message);
    }
  } finally {
    submitting.value = false;
  }
};

const currentStatus = computed<Status>(() => props.transaction?.status ?? 'pendente');
</script>

<template>
  <section class="space-y-6">
    <header class="flex flex-col gap-2 border border-slate-200 bg-white p-4 shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-base font-semibold text-slate-900">
            {{ isCreate ? 'Novo lançamento' : `Lançamento #${props.transaction?.id}` }}
          </h1>
          <p class="text-xs text-slate-500">
            {{
              isCreate
                ? 'Insira os dados do lançamento financeiro para registrar no fluxo de caixa.'
                : 'Edite as informações do lançamento financeiro selecionado.'
            }}
          </p>
        </div>
        <TransactionStatusBadge v-if="!isCreate && props.transaction" :status="currentStatus" />
      </div>
      <div
        v-if="!canEdit"
        class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700"
      >
        Você não possui permissão para alterar este lançamento. Os campos estão bloqueados.
      </div>
    </header>

    <form class="space-y-4" @submit.prevent="handleSubmit">
      <section class="rounded border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-4 md:grid-cols-2">
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-700">Conta *</label>
            <select
              v-model="form.account_id"
              :required="canEdit"
              :disabled="!canEdit"
              class="rounded border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            >
              <option :value="null" disabled>Selecione uma conta</option>
              <option v-for="account in accountOptions" :key="account.id" :value="account.id">
                {{ account.nome }}
              </option>
            </select>
            <p v-if="errors.account_id" class="text-xs text-rose-600">{{ errors.account_id }}</p>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-700">Centro de custo</label>
            <select
              v-model="form.cost_center_id"
              :disabled="!canEdit"
              class="rounded border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            >
              <option :value="null">Não vinculado</option>
              <option v-for="center in costCenterOptions" :key="center.id" :value="center.id">
                {{ center.nome }}
              </option>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-700">Tipo *</label>
            <div class="flex gap-3">
              <label class="flex items-center gap-2 text-sm text-slate-700">
                <input v-model="form.tipo" type="radio" value="credito" :disabled="!canEdit" />
                Crédito
              </label>
              <label class="flex items-center gap-2 text-sm text-slate-700">
                <input v-model="form.tipo" type="radio" value="debito" :disabled="!canEdit" />
                Débito
              </label>
            </div>
            <p v-if="errors.tipo" class="text-xs text-rose-600">{{ errors.tipo }}</p>
          </div>

          <MoneyInput
            v-model="form.valor"
            name="valor"
            label="Valor *"
            :required="canEdit"
            :disabled="!canEdit"
          />

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-700">Data de ocorrência *</label>
            <DatePicker
              v-model="form.data_ocorrencia"
              placeholder="dd/mm/aaaa"
              :required="canEdit"
              :disabled="!canEdit"
              :invalid="Boolean(errors.data_ocorrencia)"
              appearance="light"
            />
            <p v-if="errors.data_ocorrencia" class="text-xs text-rose-600">
              {{ errors.data_ocorrencia }}
            </p>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-700">Contrato (ID opcional)</label>
            <input
              v-model="form.contrato_id"
              type="number"
              min="1"
              :disabled="!canEdit"
              class="rounded border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              placeholder="Informe o ID do contrato"
            />
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-700">Fatura (ID opcional)</label>
            <input
              v-model="form.fatura_id"
              type="number"
              min="1"
              :disabled="!canEdit"
              class="rounded border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              placeholder="Informe o ID da fatura"
            />
          </div>

          <div class="md:col-span-2 flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-700">Descrição</label>
            <textarea
              v-model="form.descricao"
              rows="3"
              :disabled="!canEdit"
              class="rounded border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
            <p v-if="errors.descricao" class="text-xs text-rose-600">{{ errors.descricao }}</p>
          </div>
        </div>
      </section>

      <footer class="flex items-center justify-between gap-3">
        <Link
          href="/financeiro"
          class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100"
        >
          Cancelar
        </Link>
        <button
          v-if="canEdit"
          type="submit"
          :disabled="submitting"
          class="inline-flex items-center justify-center rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 disabled:opacity-60"
        >
          {{ submitting ? 'Salvando...' : isCreate ? 'Salvar lançamento' : 'Atualizar lançamento' }}
        </button>
      </footer>
    </form>
  </section>
</template>
