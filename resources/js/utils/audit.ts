import type { AuditLogEntry } from '@/composables/useAuditTimeline';

const defaultActionLabels: Record<string, string> = {
  'imovel.created': 'Imóvel criado',
  'imovel.updated': 'Imóvel atualizado',
  'imovel.deleted': 'Imóvel excluído',
  'contrato.created': 'Contrato criado',
  'contrato.updated': 'Contrato atualizado',
  'contrato.deleted': 'Contrato excluído',
};

const defaultFieldLabels: Record<string, string> = {
  valor_locacao: 'Valor locação',
  valor_condominio: 'Valor condomínio',
  valor_iptu: 'Valor IPTU',
  outros_valores: 'Outros valores',
  disponibilidade: 'Disponibilidade',
  status: 'Status',
  data_inicio: 'Início',
  data_fim: 'Fim',
};

const booleanLabels: Record<string, string> = {
  true: 'Sim',
  false: 'Não',
};

export function getActionLabel(action: string, customLabels: Record<string, string> = {}): string {
  return customLabels[action] ?? defaultActionLabels[action] ?? action;
}

export function extractChanges(
  entry: AuditLogEntry,
  overrides: Record<string, string> = {}
): string[] {
  const payload = entry.payload ?? {};
  const before = (payload as Record<string, any>).before ?? {};
  const after = (payload as Record<string, any>).after ?? {};

  const keys = Array.from(new Set([...Object.keys(before), ...Object.keys(after)]));
  const labels = { ...defaultFieldLabels, ...overrides };

  const changes: string[] = [];

  for (const key of keys) {
    const beforeValue = normalizeValue(before[key]);
    const afterValue = normalizeValue(after[key]);

    if (beforeValue === afterValue || key === 'updated_at') {
      continue;
    }

    const label = labels[key] ?? key;
    changes.push(`${label}: ${beforeValue} → ${afterValue}`);
  }

  return changes;
}

function normalizeValue(value: unknown): string {
  if (value === null || value === undefined || value === '') {
    return '—';
  }

  if (typeof value === 'boolean') {
    return booleanLabels[String(value)] ?? (value ? 'Sim' : 'Não');
  }

  if (typeof value === 'number') {
    return String(value);
  }

  if (Array.isArray(value)) {
    return value.map((item) => normalizeValue(item)).join(', ');
  }

  if (typeof value === 'object') {
    try {
      return JSON.stringify(value, null, 0);
    } catch {
      return '[dados]';
    }
  }

  const stringValue = String(value);

  try {
    const parsed = JSON.parse(stringValue);
    if (Array.isArray(parsed)) {
      return parsed.join(', ');
    }
    if (typeof parsed === 'object') {
      return JSON.stringify(parsed);
    }
  } catch {
    // ignore parse errors
  }

  return stringValue;
}

export function formatDateTime(value: string | null): string {
  if (!value) return '-';
  try {
    return new Date(value).toLocaleString('pt-BR');
  } catch {
    return value;
  }
}
