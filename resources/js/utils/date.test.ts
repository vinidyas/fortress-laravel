import { describe, expect, it } from 'vitest';

import { formatDate } from './date';

describe('formatDate', () => {
  it('returns dash when value is null', () => {
    expect(formatDate(null)).toBe('-');
  });

  it('formats iso date without timezone shift', () => {
    expect(formatDate('2025-02-10')).toBe('10/02/2025');
  });

  it('falls back to native formatting for unexpected input', () => {
    const fallback = new Date('invalid').toLocaleDateString('pt-BR');
    expect(formatDate('invalid')).toBe(fallback);
  });
});
