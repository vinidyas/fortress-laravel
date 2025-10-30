# Assunções

1. Datas recebidas do legado estão em UTC e são convertidas via `Carbon::parse`.
2. IDs legados são mapeados via `crosswalk` para preservar relacionamentos.
3. Campos monetários chegam em formato brasileiro (`1.234,56`) e são normalizados para decimal.
4. Logs de auditoria não mascaram dados sensíveis; qualquer campo sigiloso deve ser filtrado em releases futuros.

