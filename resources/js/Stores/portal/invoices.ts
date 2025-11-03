import { defineStore } from 'pinia';
import { ref } from 'vue';

export type PortalInvoice = {
  id: number;
  contrato_id: number;
  competencia: string | null;
  vencimento: string | null;
  status: string | null;
  valor_total: number;
  valor_pago: number;
  pago_em: string | null;
  receipt_url: string | null;
  boleto: {
    id: number;
    status: string;
    linha_digitavel: string | null;
    codigo_barras: string | null;
    pdf_url: string | null;
    nosso_numero: string | null;
    valor: number;
  } | null;
};

export type PortalInvoiceDetail = PortalInvoice & {
  itens: Array<{ id: number; descricao: string | null; categoria: string | null; valor: number }>;
  boletos: Array<
    PortalInvoice['boleto'] & {
      status_label: string;
      valor_pago: number;
      vencimento: string | null;
      registrado_em: string | null;
      liquidado_em: string | null;
    }
  >;
};

export const usePortalInvoicesStore = defineStore('portalInvoices', () => {
  const invoices = ref<PortalInvoice[]>([]);
  const selected = ref<PortalInvoiceDetail | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  function setInvoices(data: PortalInvoice[]) {
    invoices.value = data;
  }

  function setSelected(data: PortalInvoiceDetail | null) {
    selected.value = data;
  }

  function setLoading(value: boolean) {
    loading.value = value;
  }

  function setError(message: string | null) {
    error.value = message;
  }

  return {
    invoices,
    selected,
    loading,
    error,
    setInvoices,
    setSelected,
    setLoading,
    setError,
  };
});
