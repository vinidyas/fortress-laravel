# Relatórios

O módulo de relatórios expõe visões consolidadas (financeira, operacional e de pessoas) via API e dashboards Inertia. Todos os relatórios compartilham permissões específicas de leitura e utilizam a permissão `reports.export` para exportação.

## Permissões

| Permissão | Descrição |
| --- | --- |
| `reports.view.financeiro` | Acessar relatório financeiro |
| `reports.view.operacional` | Acessar relatório operacional |
| `reports.view.pessoas` | Acessar relatório de pessoas |
| `reports.export` | Exportar qualquer relatório |

## Relatório Financeiro

- Página Inertia: `Relatorios/Financeiro.vue`
- API base: `GET /api/reports/financeiro`
- Export: `GET /api/reports/financeiro/export?format=csv`

| Filtro | Parâmetro | Exemplo |
| --- | --- | --- |
| Período | `de`, `ate` | `?de=2025-09-01&ate=2025-09-30` |
| Conta | `account_id` | `?account_id=1` |
| Status do lançamento | `status` (`pendente`, `conciliado`, `cancelado`) | `?status=conciliado` |

Resposta (JSON): totals (`receitas`, `despesas`, `saldo`) + lista `inadimplencia` com faturas em aberto.

Export CSV: colunas `Data, Tipo, Valor, Conta, Status, Descricao`.

## Relatório Operacional

- Página Inertia: `Relatorios/Operacional.vue`
- API base: `GET /api/reports/operacional`
- Export: `GET /api/reports/operacional/export?format=csv`

| Filtro | Parâmetro | Exemplo |
| --- | --- | --- |
| Cidade | `cidade` | `?cidade=Curitiba` |
| Condomínio | `condominio_id` | `?condominio_id=3` |
| Status contrato | `status_contrato` (`Ativo`, `Suspenso`, `Encerrado`) | `?status_contrato=Ativo` |
| Contratos até | `ate` (data) | `?ate=2025-12-31` |

Resposta: objeto `ocupacao` (`total`, `disponiveis`, `indisponiveis`, `ocupacao_percentual`) e lista `contratos_vencendo`.

Export CSV: `Contrato, Imovel, Cidade, Status, Fim`.

## Relatório de Pessoas

- Página Inertia: `Relatorios/Pessoas.vue`
- API base: `GET /api/reports/pessoas`
- Export: `GET /api/reports/pessoas/export?format=csv`

| Filtro | Parâmetro | Exemplo |
| --- | --- | --- |
| Papel | `papel` | `?papel=Proprietario` |
| Tipo | `tipo_pessoa` (`Fisica`, `Juridica`) | `?tipo_pessoa=Fisica` |

Resposta: `total`, `por_tipo` (mapa `Fisica`/`Juridica`) e `amostra` (até 20 registros).

Export CSV: `ID, Nome, Tipo, Papeis`.

## Considerações de Performance

- As consultas utilizam índices (datas, status, relacionamento com contas/imóveis) para manter boa performance.
- APIs retornam página única (sem paginação) por se tratarem de relatórios resumidos. Importante sempre filtrar períodos razoáveis (`de`/`ate`).
- Para volumes muito grandes prefira exportações CSV e processe offline.

## Importador de Relatórios

Ainda não há importador específico (`--relatorios` está reservado para evoluções). A alimentação ocorre automaticamente pelos módulos Financeiro/Contratos/Pessoas.

## UI

Cada dashboard oferece filtros idênticos aos parâmetros da API, indicadores resumidos e uma ação de exportação (visível apenas a quem possui `reports.export`).
