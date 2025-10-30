export type CepAddress = {
  cep: string;
  uf: string;
  cidade: string;
  bairro?: string | null;
  logradouro?: string | null;
  complemento?: string | null;
};

function onlyDigits(value: string): string {
  return String(value || '').replace(/\D+/g, '');
}

export function normalizeCep(cep: string): string {
  return onlyDigits(cep).slice(0, 8);
}

async function fetchViaCep(cep: string): Promise<CepAddress | null> {
  try {
    const res = await fetch(`https://viacep.com.br/ws/${cep}/json/`, { mode: 'cors' });
    if (!res.ok) return null;
    const data: any = await res.json();
    if (data?.erro) return null;
    return {
      cep: data?.cep || cep,
      uf: data?.uf || '',
      cidade: data?.localidade || '',
      bairro: data?.bairro ?? null,
      logradouro: data?.logradouro ?? null,
      complemento: data?.complemento ?? null,
    };
  } catch {
    return null;
  }
}

async function fetchBrasilApi(cep: string): Promise<CepAddress | null> {
  try {
    const res = await fetch(`https://brasilapi.com.br/api/cep/v2/${cep}`, { mode: 'cors' });
    if (!res.ok) return null;
    const data: any = await res.json();
    return {
      cep: data?.cep || cep,
      uf: data?.state || '',
      cidade: data?.city || '',
      bairro: data?.neighborhood ?? null,
      logradouro: data?.street ?? null,
      complemento: data?.complement ?? null,
    };
  } catch {
    return null;
  }
}

export async function lookupCep(rawCep: string): Promise<CepAddress | null> {
  const cep = normalizeCep(rawCep);
  if (cep.length !== 8) return null;

  const via = await fetchViaCep(cep);
  if (via) return via;

  const brasil = await fetchBrasilApi(cep);
  if (brasil) return brasil;

  return null;
}

