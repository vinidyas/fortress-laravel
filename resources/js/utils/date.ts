export const formatDate = (value: string | null): string => {
  if (!value) {
    return '-';
  }

  const segments = value.split('-');
  if (segments.length !== 3) {
    return new Date(value).toLocaleDateString('pt-BR');
  }

  const [year, month, day] = segments.map((segment) => Number.parseInt(segment, 10));

  if ([year, month, day].some((part) => Number.isNaN(part))) {
    return new Date(value).toLocaleDateString('pt-BR');
  }

  const dayString = String(day).padStart(2, '0');
  const monthString = String(month).padStart(2, '0');

  return `${dayString}/${monthString}/${year}`;
};
