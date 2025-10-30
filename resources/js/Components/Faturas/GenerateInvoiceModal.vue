<script setup lang="ts">
import axios from '@/bootstrap';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

type ContractOption = {
  id: number;
  codigo_contrato?: string | null;
  imovel?: {
    codigo?: string | null;
    cidade?: string | null;
    bairro?: string | null;
    condominio_nome?: string | null;
    complemento?: string | null;
  } | null;
  imovel_label?: string | null;
  imovel_sub_label?: string | null;
  has_invoice_in_month: boolean;
};

type Props = {
  show: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'generated', payload: { message: string }): void;
}>();

const search = ref('');
const contracts = ref<ContractOption[]>([]);
const loading = ref(false);
const errorMessage = ref('');
const selectedContractId = ref<number | null>(null);
const isSubmitting = ref(false);

async function fetchContracts() {
  loading.value = true;
  errorMessage.value = '';

  try {
    const { data } = await axios.get('/api/faturas/eligible-contracts', {
      params: {
        search: search.value || undefined,
      },
    });

    contracts.value = Array.isArray(data.data) ? data.data : [];

    if (!contracts.value.some((contract) => contract.id === selectedContractId.value)) {
      selectedContractId.value = null;
    }
  } catch (error) {
    console.error(error);
    errorMessage.value = 'Não foi possível carregar os contratos.';
    contracts.value = [];
  } finally {
    loading.value = false;
  }
}

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      selectedContractId.value = null;
      void fetchContracts();
    } else {
      search.value = '';
      contracts.value = [];
      errorMessage.value = '';
    }
  }
);

let searchDebounce: ReturnType<typeof setTimeout> | null = null;

watch(search, () => {
  if (!props.show) {
    return;
  }

  if (searchDebounce) {
    clearTimeout(searchDebounce);
  }

  searchDebounce = setTimeout(() => {
    void fetchContracts();
  }, 400);
});

onBeforeUnmount(() => {
  if (searchDebounce) {
    clearTimeout(searchDebounce);
  }
});

const selectedContract = computed(() =>
  contracts.value.find((contract) => contract.id === selectedContractId.value) ?? null
);

async function generateInvoice() {
  if (!selectedContractId.value || isSubmitting.value) {
    return;
  }

  isSubmitting.value = true;
  errorMessage.value = '';

  try {
    const { data } = await axios.post('/api/faturas/generate-month', {
      contrato_id: selectedContractId.value,
    });

    emit('generated', { message: data.message ?? 'Processo concluído.' });
  } catch (error) {
    console.error(error);
    if (axios.isAxiosError(error) && error.response?.data?.message) {
      errorMessage.value = error.response.data.message;
    } else {
      errorMessage.value = 'Não foi possível gerar a fatura.';
    }
  } finally {
    isSubmitting.value = false;
  }
}

function closeModal() {
  if (isSubmitting.value) {
    return;
  }

  emit('close');
}
</script>

<template>
  <transition name="fade">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur"
      @keydown.esc.prevent.stop="closeModal"
    >
      <div class="relative w-full max-w-3xl overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40">
        <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
          <div>
            <h2 class="text-lg font-semibold text-white">Gerar fatura individual</h2>
            <p class="text-xs text-slate-400">Selecione o contrato para gerar a fatura do mês vigente.</p>
          </div>
          <button type="button" class="rounded-md p-2 text-slate-400 transition hover:text-white" @click="closeModal">
            <span class="sr-only">Fechar</span>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </header>

        <div class="max-h-[75vh] overflow-y-auto px-6 py-5 text-sm text-slate-200">
          <div class="mb-4">
            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-400">
              Buscar contrato
            </label>
            <input
              v-model="search"
              type="search"
              class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              placeholder="Informe o código do contrato, ID ou código do imóvel"
            />
            <p class="mt-2 text-xs text-slate-500">
              Apenas contratos ativos e elegíveis para geração na competência atual são listados.
            </p>
          </div>

          <div
            v-if="errorMessage"
            class="mb-4 rounded-lg border border-rose-500/40 bg-rose-500/15 px-3 py-2 text-xs text-rose-200"
          >
            {{ errorMessage }}
          </div>

          <div v-else-if="loading" class="py-6 text-center text-sm text-slate-400">
            Carregando contratos...
          </div>

          <div v-else-if="!contracts.length" class="py-6 text-center text-sm text-slate-400">
            Nenhum contrato elegível encontrado.
          </div>

          <div v-else class="space-y-3">
            <p class="text-xs text-slate-500">
              Selecionado:
              <span v-if="selectedContract" class="font-semibold text-slate-200">
                Contrato {{ selectedContract.codigo_contrato ?? `#${selectedContract.id}` }}
              </span>
              <span v-else class="text-slate-400">nenhum contrato selecionado</span>
              <template v-if="selectedContract">
                <span class="mt-1 block text-xs text-slate-400">
                  {{ selectedContract.imovel_label ?? 'Sem condomínio' }}
                  <template v-if="selectedContract.imovel_sub_label">
                    — {{ selectedContract.imovel_sub_label }}
                  </template>
                </span>
              </template>
            </p>

            <div class="overflow-hidden rounded-xl border border-slate-800">
              <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
                <thead class="bg-slate-900/60 text-xs uppercase tracking-wide text-slate-500">
                  <tr>
                    <th class="px-4 py-3">Selecionar</th>
                    <th class="px-4 py-3">Contrato</th>
                    <th class="px-4 py-3">Imóvel</th>
                    <th class="px-4 py-3 text-right">Status mês</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-200">
                  <tr
                    v-for="contract in contracts"
                    :key="contract.id"
                    :class="[
                      selectedContractId === contract.id ? 'bg-slate-800/60' : 'hover:bg-slate-800/40',
                    ]"
                    class="transition-colors"
                  >
                    <td class="px-4 py-3">
                      <input
                        :id="`contract-${contract.id}`"
                        v-model="selectedContractId"
                        :value="contract.id"
                        type="radio"
                        class="h-4 w-4 cursor-pointer border-slate-500 text-indigo-500 focus:ring-indigo-500"
                      />
                    </td>
                    <td class="px-4 py-3">
                      <label :for="`contract-${contract.id}`" class="cursor-pointer font-semibold text-slate-100">
                        {{ contract.codigo_contrato ?? `Contrato #${contract.id}` }}
                      </label>
                      <div class="text-xs text-slate-500">ID {{ contract.id }}</div>
                    </td>
                    <td class="px-4 py-3">
                      <div>{{ contract.imovel_label ?? 'Sem condomínio' }}</div>
                      <div class="text-xs text-slate-500">
                        <template v-if="contract.imovel_sub_label">
                          {{ contract.imovel_sub_label }}
                        </template>
                        <template v-else>
                          —
                        </template>
                      </div>
                    </td>
                    <td class="px-4 py-3 text-right">
                      <span
                        :class="[
                          'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                          contract.has_invoice_in_month
                            ? 'bg-amber-200/20 text-amber-300'
                            : 'bg-emerald-300/20 text-emerald-200',
                        ]"
                      >
                        {{ contract.has_invoice_in_month ? 'Já existe' : 'Disponível' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <footer class="flex items-center justify-between gap-3 border-t border-slate-800 bg-slate-900/80 px-6 py-4 text-sm">
          <div v-if="selectedContract?.has_invoice_in_month" class="text-xs text-amber-300">
            Já existe fatura para este contrato no mês atual. Ao continuar, nenhuma nova fatura será criada.
          </div>
          <div v-else class="text-xs text-slate-500">
            A fatura será gerada considerando a competência e vencimento configurados no contrato.
          </div>
          <div class="flex items-center gap-3">
            <button
              type="button"
              class="rounded-lg border border-slate-600 px-4 py-2 font-semibold text-slate-200 transition hover:bg-slate-800"
              :disabled="isSubmitting"
              @click="closeModal"
            >
              Cancelar
            </button>
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="!selectedContractId || isSubmitting"
              @click="generateInvoice"
            >
              {{ isSubmitting ? 'Processando...' : 'Gerar fatura' }}
            </button>
          </div>
        </footer>
      </div>
    </div>
  </transition>
</template>
