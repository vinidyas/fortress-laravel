# Relatórios

Relatórios consolidados para visualização e exportação.

## Relatórios Disponíveis

- Financeiro: totais por tipo/conta/período, filtros por conta/centro/status.
- Operacional: ocupação de imóveis e contratos vencendo.
- Pessoas: totais por tipo e papel.

## API

| Método | Endpoint                          | Descrição                               |
| ------ | --------------------------------- | --------------------------------------- |
| GET    | `/api/reports/financeiro`         | Dados consolidados do módulo financeiro |
| GET    | `/api/reports/financeiro/export`  | Exporta CSV (permite filtros)           |
| GET    | `/api/reports/operacional`        | Dados operacionais                      |
| GET    | `/api/reports/operacional/export` | Exporta CSV                             |
| GET    | `/api/reports/pessoas`            | Dados consolidados de pessoas           |
| GET    | `/api/reports/pessoas/export`     | Exporta CSV                             |

Permissões:

- `reports.view.financeiro`, `reports.view.operacional`, `reports.view.pessoas`
- `reports.export` para exportação
