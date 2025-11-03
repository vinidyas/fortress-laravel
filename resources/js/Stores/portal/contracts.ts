import { defineStore } from 'pinia';
import { ref } from 'vue';

export type PortalContract = {
  id: number;
  codigo: string | null;
  status: string | null;
  valor_aluguel: number | null;
  dia_vencimento: number | null;
  data_inicio: string | null;
  data_fim: string | null;
  imovel: {
    codigo: string | null;
    tipo: string | null;
    endereco: string | null;
  } | null;
  locador: {
    nome: string | null;
  } | null;
};

export const usePortalContractsStore = defineStore('portalContracts', () => {
  const contracts = ref<PortalContract[]>([]);
  const loading = ref(false);
  const error = ref<string | null>(null);

  function setContracts(data: PortalContract[]) {
    contracts.value = data;
  }

  function setLoading(value: boolean) {
    loading.value = value;
  }

  function setError(message: string | null) {
    error.value = message;
  }

  return {
    contracts,
    loading,
    error,
    setContracts,
    setLoading,
    setError,
  };
});
