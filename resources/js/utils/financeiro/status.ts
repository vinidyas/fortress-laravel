export type TransactionType = 'receita' | 'despesa' | 'transferencia';
export type TransactionStatusCode = 'planejado' | 'pendente' | 'pago' | 'cancelado' | 'atrasado';
export type TransactionStatusCategory = 'open' | 'overdue' | 'settled' | 'cancelled';
export type TransactionStatusGroup = 'open' | 'settled' | 'cancelled';

export const OPEN_STATUS_CODES: ReadonlyArray<TransactionStatusCode> = [
  'planejado',
  'pendente',
  'atrasado',
];
export const SETTLED_STATUS_CODES: ReadonlyArray<TransactionStatusCode> = ['pago'];
export const CANCELLED_STATUS_CODES: ReadonlyArray<TransactionStatusCode> = ['cancelado'];

export const isTransactionType = (value: unknown): value is TransactionType =>
  value === 'receita' || value === 'despesa' || value === 'transferencia';

export const resolveStatusCategory = (
  status?: string | null
): TransactionStatusCategory => {
  switch (status) {
    case 'pago':
      return 'settled';
    case 'cancelado':
      return 'cancelled';
    case 'atrasado':
      return 'overdue';
    default:
      return 'open';
  }
};

export const resolveStatusGroupForStatus = (
  status?: string | null
): TransactionStatusGroup => {
  switch (status) {
    case 'pago':
      return 'settled';
    case 'cancelado':
      return 'cancelled';
    default:
      return 'open';
  }
};

export const resolveStatusGroupLabel = (
  group: TransactionStatusGroup,
  type?: TransactionType | string | null
): string => {
  const transactionType = isTransactionType(type) ? type : undefined;

  if (group === 'settled') {
    switch (transactionType) {
      case 'receita':
        return 'Recebido';
      case 'despesa':
        return 'Pago';
      case 'transferencia':
        return 'Efetivada';
      default:
        return 'Quitado';
    }
  }

  if (group === 'cancelled') {
    return transactionType === 'transferencia' ? 'Cancelada' : 'Cancelado';
  }

  switch (transactionType) {
    case 'receita':
      return 'A receber';
    case 'despesa':
      return 'A pagar';
    case 'transferencia':
      return 'Pendente';
    default:
      return 'Em aberto';
  }
};

export const resolveStatusLabel = (
  status?: string | null,
  type?: TransactionType | string | null
): string => {
  const category = resolveStatusCategory(status);

  if (category === 'cancelled') {
    return resolveStatusGroupLabel('cancelled', type);
  }

  if (category === 'settled') {
    return resolveStatusGroupLabel('settled', type);
  }

  return resolveStatusGroupLabel('open', type);
};

export const resolveStatusCodesForGroup = (
  group: TransactionStatusGroup
): TransactionStatusCode[] => {
  switch (group) {
    case 'settled':
      return [...SETTLED_STATUS_CODES];
    case 'cancelled':
      return [...CANCELLED_STATUS_CODES];
    case 'open':
    default:
      return [...OPEN_STATUS_CODES];
  }
};
