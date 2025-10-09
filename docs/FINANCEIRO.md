# Financeiro

O módulo de Finanças cobre contas, centros de custo, agendamentos e o fluxo completo de lançamentos (registro, conciliação, cancelamento e exportação). Ele é consumido pelo SPA via Inertia/Vue e pelas APIs REST descritas abaixo.

## Permissões

| Permissão | Descrição |
| --- | --- |
| `financeiro.view` | Visualizar listagens, relatórios e lançamentos |
| `financeiro.create` | Criar contas, centros de custo, lançamentos e agendamentos |
| `financeiro.update` | Editar registros pendentes |
| `financeiro.delete` | Remover registros não conciliados |
| `financeiro.reconcile` | Concluir conciliações de lançamentos |
| `financeiro.export` | Exportar listagens (CSV/JSON) |

> Dica: use o seeder `PermissionsSeeder` para criar papéis pré-configurados (`admin`, `operador`, `financeiro`, `auditor`).

## Entidades

- **Contas Financeiras (`financial_accounts`)** – origem dos valores (bancos, caixa, etc.).
- **Centros de Custo (`cost_centers`)** – agrupadores para rateio.
- **Lançamentos (`financial_transactions`)** – créditos/débitos opcionais vinculados a contratos/faturas.
- **Agendamentos (`payment_schedules`)** – agenda de parcelas futuras com status automático (`aberto`, `em_atraso`, `quitado`, `cancelado`).

## APIs

| Método | Endpoint | Descrição |
| --- | --- | --- |
| GET | `/api/financeiro/accounts` | Lista contas com filtros `ativos`, `tipo`, `per_page` |
| POST | `/api/financeiro/accounts` | Cria conta (permite `tipo`, `saldo_inicial`, `ativo`) |
| PUT | `/api/financeiro/accounts/{id}` | Atualiza conta |
| DELETE | `/api/financeiro/accounts/{id}` | Remove conta sem lançamentos vinculados |
| GET | `/api/financeiro/cost-centers` | Lista centros com `search`, `per_page` |
| POST | `/api/financeiro/cost-centers` | Cria centro de custo |
| PUT | `/api/financeiro/cost-centers/{id}` | Atualiza centro de custo |
| DELETE | `/api/financeiro/cost-centers/{id}` | Remove centro sem lançamentos |
| GET | `/api/financeiro/transactions` | Lista lançamentos (vide filtros abaixo) |
| POST | `/api/financeiro/transactions` | Cria lançamento (com opção de vincular `contrato_id`/`fatura_id`) |
| GET | `/api/financeiro/transactions/{id}` | Detalha lançamento |
| PUT | `/api/financeiro/transactions/{id}` | Atualiza lançamento pendente |
| DELETE | `/api/financeiro/transactions/{id}` | Remove lançamento não conciliado |
| POST | `/api/financeiro/transactions/{id}/reconcile` | Concilia lançamento (status → `conciliado`) |
| POST | `/api/financeiro/transactions/{id}/cancel` | Cancela lançamento pendente |
| GET | `/api/financeiro/transactions/export` | Exporta lançamentos (CSV) |
| GET | `/api/financeiro/payment-schedules` | Lista agendamentos (`status`, `vencimento_de/ate`) |

### Filtros suportados em `/api/financeiro/transactions`

- `filter[search]` – busca texto em `descricao` / observação de conciliação.
- `filter[tipo]` – `credito` ou `debito`.
- `filter[status]` – `pendente`, `conciliado`, `cancelado`.
- `filter[account_id]`, `filter[cost_center_id]`, `filter[contrato_id]`, `filter[fatura_id]`.
- `filter[data_de]`, `filter[data_ate]` – intervalo de data de ocorrência.
- `per_page` – paginação (default 15, limite 100).

### Fluxo de Conciliação

1. Registrar lançamento com `status` padrão `pendente`.
2. Opcionalmente corrigir dados (`PUT /api/financeiro/transactions/{id}`).
3. Executar conciliação via `POST /api/financeiro/transactions/{id}/reconcile` informando `valor_conciliado` e observação.
4. Para lançamentos incorretos, use `POST /api/financeiro/transactions/{id}/cancel` (bloqueado se já conciliado).

### Exportação

- `GET /api/financeiro/transactions/export` gera CSV com filtros aplicados. Headers: `ID, Data, Conta, Tipo, Valor, Status, Descricao`.
- O front utiliza os mesmos filtros do Pinia store (`resources/js/Stores/financeiro.ts`).

## Interface (Inertia)

- Página principal: `Financeiro/Index.vue` – filtros por busca, conta, centro, tipo, status, período, tamanho da página.
- Ações dependem das permissões retornadas em `can.create`, `can.reconcile`, `can.export`.
- Formulário de lançamento (`Financeiro/Transactions/Form.vue`) respeita travas de permissão e mostra vínculos opcionais com contrato/fatura.

## Importação de Legado

O comando migra dados do sistema anterior para as tabelas atuais.

```bash
php artisan legacy:import --financeiro --dry-run   # valida conexão e exibe estatísticas
php artisan legacy:import --financeiro             # importa contas, centros, lançamentos e agendas
```

Use `--truncate` para limpar as tabelas de destino antes da importação (requer atenção, pois faz `TRUNCATE`).

## Auditoria

Todas as entidades do módulo utilizam `App\Observers\AuditableObserver`, logo operações de criação/edição/cancelamento geram registros em `audit_logs` e ficam disponíveis na tela de Auditoria.
