# Financeiro

O módulo de Finanças cobre contas, centros de custo, agendamentos e o fluxo
completo de lançamentos (registro, conciliação, cancelamento e exportação). Ele
é consumido pelo SPA via Inertia/Vue e pelas APIs REST descritas abaixo.

## Permissões

| Permissão              | Descrição                                                  |
| ---------------------- | ---------------------------------------------------------- |
| `financeiro.view`      | Visualizar listagens, relatórios e lançamentos             |
| `financeiro.create`    | Criar contas, centros de custo, lançamentos e agendamentos |
| `financeiro.update`    | Editar registros pendentes                                 |
| `financeiro.delete`    | Remover lançamentos não consolidados/pagos                 |
| `financeiro.reconcile` | Concluir conciliações de lançamentos                       |
| `financeiro.export`    | Exportar listagens (CSV/JSON)                              |

> Dica: use o seeder `PermissionsSeeder` para criar papéis pré-configurados
> (`admin`, `operador`, `financeiro`, `auditor`).

## Entidades

- Contas Financeiras (`financial_accounts`): origem dos valores (bancos, caixa,
  etc.).
- Centros de Custo (`cost_centers`): agrupadores para rateio.
- Lançamentos (`journal_entries`): representam receitas, despesas ou
  transferências. Cada lançamento possui parcelas opcionais
  (`journal_entry_installments`), anexos e recibos.
- Agendamentos (`payment_schedules`): agenda de parcelas futuras com status
  automático (`aberto`, `em_atraso`, `quitado`, `cancelado`).

## APIs

| Método | Endpoint                                                        | Descrição                                                           |
| ------ | ---------------------------------------------------------------- | ------------------------------------------------------------------- |
| GET    | `/api/financeiro/accounts`                                      | Lista contas com filtros `ativos`, `tipo`, `per_page`               |
| POST   | `/api/financeiro/accounts`                                      | Cria conta (permite `tipo`, `saldo_inicial`, `ativo`)               |
| PUT    | `/api/financeiro/accounts/{id}`                                 | Atualiza conta                                                      |
| DELETE | `/api/financeiro/accounts/{id}`                                 | Remove conta sem lançamentos vinculados                             |
| GET    | `/api/financeiro/cost-centers`                                  | Lista centros com `search`, `per_page`                              |
| POST   | `/api/financeiro/cost-centers`                                  | Cria centro de custo                                                |
| PUT    | `/api/financeiro/cost-centers/{id}`                             | Atualiza centro de custo                                            |
| DELETE | `/api/financeiro/cost-centers/{id}`                             | Remove centro sem lançamentos                                       |
| GET    | `/api/financeiro/journal-entry-descriptions`                    | Sugestões de descrições recorrentes                                 |
| GET    | `/api/financeiro/journal-entries`                               | Lista lançamentos (`filter[...]`, `per_page`)                       |
| POST   | `/api/financeiro/journal-entries`                               | Cria lançamento com parcelas/rateios/anexos                         |
| GET    | `/api/financeiro/journal-entries/{id}`                           | Detalha lançamento com parcelas, anexos e recibos                   |
| PUT    | `/api/financeiro/journal-entries/{id}`                           | Atualiza lançamento (enquanto não cancelado)                        |
| DELETE | `/api/financeiro/journal-entries/{id}`                           | Remove lançamento não cancelado                                     |
| POST   | `/api/financeiro/journal-entries/{id}/installments/{n}/pay`     | Quita parcela específica                                            |
| POST   | `/api/financeiro/journal-entries/{id}/cancel`                   | Cancela lançamento (propaga para parcelas)                          |
| POST   | `/api/financeiro/journal-entries/{id}/generate-receipt`         | Gera recibo em PDF (opcionalmente por parcela)                      |
| GET    | `/api/financeiro/journal-entries/{id}/attachments`              | Lista anexos do lançamento                                          |
| POST   | `/api/financeiro/journal-entries/{id}/attachments`              | Faz upload de anexos                                                |
| GET    | `/api/financeiro/journal-entries/{id}/attachments/{attachment}` | Download do anexo                                                   |
| DELETE | `/api/financeiro/journal-entries/{id}/attachments/{attachment}` | Remove anexo                                                        |
| GET    | `/api/financeiro/journal-entries/{id}/receipts`                 | Lista recibos gerados                                               |
| GET    | `/api/financeiro/journal-entries/{id}/receipts/{receipt}`       | Download do recibo em PDF                                           |
| GET    | `/api/financeiro/bank-statements`                               | Extratos importados                                                 |
| POST   | `/api/financeiro/bank-statements`                               | Importa extrato OFX/CSV                                             |
| POST   | `/api/financeiro/bank-statements/{id}/suggest-matches`          | Recalcula sugestões de conciliação                                  |
| POST   | `/api/financeiro/bank-statements/{id}/lines/{line}/confirm`     | Confirma conciliação de linha                                       |
| POST   | `/api/financeiro/bank-statements/{id}/lines/{line}/ignore`      | Ignora linha do extrato                                             |
| GET    | `/api/financeiro/reconciliations`                               | Fechamentos realizados                                              |
| POST   | `/api/financeiro/reconciliations`                               | Fecha conciliação de período                                        |
| GET    | `/api/financeiro/reconciliations/export`                        | Exporta fechamentos em CSV                                          |
| GET    | `/api/financeiro/payment-schedules`                             | Lista agendamentos (`status`, `vencimento_de/ate`)                  |
| GET    | `/api/reports/financeiro`                                       | Totais (receitas, despesas, saldo, planejado, pendente, atrasado)   |
| GET    | `/api/reports/financeiro/export`                                | Exporta lançamentos filtrados em CSV                                |

### Filtros suportados em `/api/financeiro/journal-entries`

- `filter[search]`: busca texto em descrição, notas ou referência.
- `filter[tipo]`: `receita`, `despesa` ou `transferencia`.
- `filter[status]`: `planejado`, `pendente`, `atrasado`, `pago`, `cancelado`.
- `filter[account_id]`, `filter[cost_center_id]`, `filter[person_id]`,
  `filter[property_id]`.
- `filter[data_de]`, `filter[data_ate]`: intervalo de data de ocorrência.
- `per_page`: paginação (default 15, limite 100).

### Fluxo de Conciliação

1. Registrar lançamento; parcelas futuras são criadas via serviço.
2. Importar extratos (`bank-statements`) para sugerir conciliações.
3. Confirmar linhas do extrato — parcelas pagas atualizam status automaticamente.
4. Fechar o período com `/api/financeiro/reconciliations` para consolidar saldo.

### Exportação

- `GET /api/financeiro/journal-entries/export` gera CSV com filtros aplicados.
  Headers: `ID, Data, Conta, Tipo, Valor, Status, Descricao`.
- O front utiliza os mesmos filtros do Pinia store
  (`resources/js/Stores/financeiro.ts`).

## Relatórios

- `GET /api/reports/financeiro` retorna objeto `totals` com chaves `receitas`,
  `despesas`, `saldo`, `planejado`, `pendente` e `atrasado`, respeitando os
  filtros opcionais (`account_id`, `status`, `de`, `ate`).
- Use `GET /api/reports/financeiro/export` para baixar CSV com os lançamentos
  filtrados (mesmo payload do endpoint principal).

## Interface (Inertia)

- Página principal: `Financeiro/Index.vue` — filtros por busca, conta, centro,
  tipo, status, período, tamanho da página.
- Ações dependem das permissões retornadas em `can.create`, `can.reconcile`,
  `can.export`.
- Formulário de lançamento (`Financeiro/Transactions/Form.vue`) respeita travas
  de permissão e mostra vínculos opcionais com contrato/fatura.

## Importação de Legado

```bash
php artisan legacy:import --financeiro --dry-run   # valida conexão e exibe estatísticas
php artisan legacy:import --financeiro             # importa contas, centros, lançamentos e agendas
```

Use `--truncate` para limpar as tabelas de destino antes da importação (requer
atenção, pois faz `TRUNCATE`).

## Auditoria

Todas as entidades do módulo utilizam `App\Observers\AuditableObserver`, logo
operações de criação/edição/cancelamento geram registros em `audit_logs` e ficam
disponíveis na tela de Auditoria.
