# Arquitetura Financeira (Nova)

Documento de referência para o redesenho do módulo financeiro com foco em
controles de contas bancárias, lançamentos completos, parcelamentos, recibos e
conciliação bancária. Serve como base para validação com stakeholders antes da
implementação das migrations e serviços.

## Visão Geral

- **Módulo isolado**: camada de domínio em `App\Domain\Financeiro` com services
  transacionais e validações consistentes.
- **Fluxo contábil**: lançamentos (`journal_entries`) podem possuir parcelas,
  rateios entre centros de custo e vínculo com imóveis, pessoas e categorias.
- **Conciliação**: importação de extratos (OFX/CSV), sugestão de matches com
  parcelas e fechamento mensal por conta.
- **Recibos e anexos**: geração de recibos PDF por lançamento/parcela e
  anexação de documentos de comprovação.

## Entidades e Tabelas

### 1. Contas Financeiras (`financial_accounts`)

| Campo                 | Tipo                | Observações                                                      |
| --------------------- | ------------------- | ---------------------------------------------------------------- |
| `id`                  | PK                  |                                                                  |
| `nome`                | string(120)         | Ex.: "Banco XP - PJ"                                             |
| `apelido`             | string(60) nullable | Identificador curto para telas                                   |
| `tipo`                | enum                | `conta_corrente`, `poupanca`, `investimento`, `caixa`, `outro`   |
| `instituicao`         | string(120)         | Nome do banco/operadora                                          |
| `agencia`             | string(20)          |                                                                  |
| `numero`              | string(40)          | Inclui dígito                                                    |
| `carteira`            | string(20) nullable | Carteira/variação quando aplicável                               |
| `moeda`               | char(3)             | Default `BRL`                                                    |
| `saldo_inicial`       | decimal(15,2)       | Saldo na data base                                               |
| `data_saldo_inicial`  | date                |                                                                  |
| `saldo_atual`         | decimal(15,2)       | Atualizado via lançamentos e conciliações                        |
| `limite_credito`      | decimal(15,2) null  |                                                                  |
| `categoria`           | enum                | `operacional`, `reserva`, `investimento`                         |
| `permite_transf`      | boolean             | Define se pode receber transferências                            |
| `padrao_recebimento`  | boolean             | Marca conta padrão                                               |
| `padrao_pagamento`    | boolean             |                                                                  |
| `integra_config`      | json nullable       | Credenciais de integração bancária                               |
| `observacoes`         | text nullable       |                                                                  |
| `ativo`               | boolean             | Default `true`                                                   |
| timestamps & soft delete |                  |                                                                  |

> **Relacionamentos**: `hasMany journal_entries`, `hasMany reconciliations`,
> `hasMany bank_statements`.

### 2. Categorias Financeiras (`financial_categories`)

| Campo         | Tipo          | Observações                                                    |
| ------------- | ------------- | -------------------------------------------------------------- |
| `id`          | PK            |                                                                |
| `parent_id`   | FK nullable   | Hierarquia (self reference)                                    |
| `codigo`      | string(20)    | Caminho tipo `1.2.3`                                           |
| `nome`        | string(120)   |                                                                |
| `tipo`        | enum          | `receita`, `despesa`, `transferencia`                          |
| `is_investment` | boolean     | Marca despesas de investimento                                 |
| `is_renovation` | boolean     | Marca despesas de reforma                                      |
| `ativo`       | boolean       |                                                                |
| timestamps    |               |                                                                |

### 3. Histórico de Descrições (`journal_entry_descriptions`)

| Campo           | Tipo          | Observações                                           |
| --------------- | ------------- | ----------------------------------------------------- |
| `id`            | PK            |                                                       |
| `texto`         | string(255)   | Descrição normalizada unique                          |
| `uso_total`     | unsigned int  | Contador atualizado a cada reutilização               |
| `ultima_utilizacao` | datetime  |                                                       |

### 4. Lançamentos (`journal_entries`)

| Campo                | Tipo             | Observações                                                                |
| -------------------- | ---------------- | -------------------------------------------------------------------------- |
| `id`                 | PK               |                                                                            |
| `type`               | enum             | `receita`, `despesa`, `transferencia`                                      |
| `bank_account_id`    | FK               | -> `financial_accounts`                                                    |
| `counter_bank_account_id` | FK nullable | Conta destino em transferências                                            |
| `financial_category_id` | FK nullable  | -> `financial_categories`                                                  |
| `cost_center_id`     | FK nullable      | Centro principal (rateios vão para `journal_entry_allocations`)            |
| `property_id`        | FK nullable      | -> `imoveis`                                                               |
| `person_id`          | FK nullable      | -> `pessoas` (cliente/fornecedor/investidor)                               |
| `description_id`     | FK nullable      | -> `journal_entry_descriptions`                                            |
| `description_custom` | string(255)      | Texto quando não usa histórico                                             |
| `notes`              | text nullable    | Observações internas                                                       |
| `reference_code`     | string(40)       | Código manual ou importado                                                |
| `origin`             | enum             | `manual`, `importado`, `recorrente`, `parcelado`, `clonado`, `integracao`  |
| `clone_of_id`        | FK nullable      | -> `journal_entries`                                                       |
| `movement_date`      | date             | Data do movimento                                                         |
| `due_date`           | date nullable    | Data de vencimento                                                        |
| `payment_date`       | date nullable    | Data efetiva de pagamento/recebimento                                     |
| `amount`             | decimal(15,2)    | Valor total do lançamento                                                 |
| `currency`           | char(3)          | Default herdado da conta                                                   |
| `status`             | enum             | `planejado`, `pendente`, `atrasado`, `pago`, `cancelado`                   |
| `installments_count` | unsigned smallint| Nº total de parcelas (>=1)                                                |
| `paid_installments`  | unsigned smallint| Atualizado via parcelas                                                    |
| `attachments_count`  | unsigned smallint|                                                                            |
| `created_by`         | FK -> users      |                                                                            |
| `updated_by`         | FK -> users      |                                                                            |
| timestamps & soft delete |             |                                                                            |

> **Regras**:
>
> - Se `type = transferencia`: exigir `counter_bank_account_id`, gerar débito e
>   crédito automaticamente (duas parcelas espelhadas) e ajustar saldo das duas
>   contas ao cair em `pago`.
> - Status muda para `pago` quando todas as parcelas estiverem quitadas.

### 5. Parcelas (`journal_entry_installments`)

| Campo                   | Tipo            | Observações                                              |
| ----------------------- | --------------- | -------------------------------------------------------- |
| `id`                    | PK              |                                                          |
| `journal_entry_id`      | FK              | -> `journal_entries`                                     |
| `numero_parcela`        | unsigned smallint | Sequencial a partir de 1                                |
| `movement_date`         | date            |                                                          |
| `due_date`              | date            |                                                          |
| `payment_date`          | date nullable   |                                                          |
| `valor_principal`       | decimal(15,2)   |                                                          |
| `valor_juros`           | decimal(15,2)   |                                                          |
| `valor_multa`           | decimal(15,2)   |                                                          |
| `valor_desconto`        | decimal(15,2)   |                                                          |
| `valor_total`           | decimal(15,2)   | `valor_principal + juros + multa - desconto`             |
| `status`                | enum            | `planejado`, `pendente`, `pago`, `cancelado`, `atrasado` |
| `paid_by_installment_id` | FK nullable    | Para estornar/substituir parcela                         |
| `meta`                  | json nullable   | Campos flexíveis (ex.: comprovante bancário)             |
| timestamps              |                 |                                                          |

### 6. Rateios (`journal_entry_allocations`)

| Campo              | Tipo          | Observações                                       |
| ------------------ | ------------- | ------------------------------------------------- |
| `id`               | PK            |                                                   |
| `journal_entry_id` | FK            |                                                   |
| `cost_center_id`   | FK            | Centro de custo específico                        |
| `property_id`      | FK nullable   | Rateio por imóvel                                 |
| `percentage`       | decimal(6,3)  | Percentual (0–100)                                |
| `amount`           | decimal(15,2) | Valor correspondente (mantido para auditoria)     |

### 7. Anexos (`journal_entry_attachments`)

| Campo              | Tipo        | Observações                                          |
| ------------------ | ----------- | ---------------------------------------------------- |
| `id`               | PK          |                                                      |
| `journal_entry_id` | FK          |                                                      |
| `installment_id`   | FK nullable | Relaciona anexo a parcela específica                 |
| `file_path`        | string      | Storage path (S3/local)                              |
| `file_name`        | string      | Nome original                                        |
| `file_size`        | unsigned int| Em bytes                                             |
| `mime_type`        | string(120) |                                                      |
| `uploaded_by`      | FK -> users |                                                      |
| timestamps         |             |                                                      |

### 8. Recibos (`financial_receipts`)

| Campo               | Tipo            | Observações                                 |
| ------------------- | --------------- | ------------------------------------------- |
| `id`                | PK              |                                             |
| `journal_entry_id`  | FK              |                                             |
| `installment_id`    | FK nullable     | Recibo por parcela                          |
| `number`            | string(40)      | Sequencial configurável                     |
| `issue_date`        | date            |                                             |
| `issued_by`         | FK -> users     |                                             |
| `pdf_path`          | string          |                                             |
| `metadata`          | json nullable   | Campos adicionais (assinaturas, etc.)       |
| timestamps          |                 |                                             |

### 9. Conciliações (`financial_reconciliations`)

| Campo              | Tipo           | Observações                                     |
| ------------------ | -------------- | ----------------------------------------------- |
| `id`               | PK             |                                                 |
| `financial_account_id` | FK        |                                                 |
| `period_start`     | date           |                                                 |
| `period_end`       | date           |                                                 |
| `opening_balance`  | decimal(15,2)  |                                                 |
| `closing_balance`  | decimal(15,2)  |                                                 |
| `status`           | enum           | `aberto`, `em_conferencia`, `fechado`          |
| `notes`            | text nullable  |                                                 |
| `locked_by`        | FK nullable    | Usuário que iniciou fechamento                  |
| timestamps         |                |                                                 |

### 10. Extratos (`bank_statements`)

| Campo                 | Tipo            | Observações                                         |
| --------------------- | --------------- | --------------------------------------------------- |
| `id`                  | PK              |                                                     |
| `financial_account_id`| FK              |                                                     |
| `reference`           | string(60)      | Nome do arquivo/transação                           |
| `original_name`       | string(120)     |                                                     |
| `imported_at`         | datetime        |                                                     |
| `imported_by`         | FK -> users     |                                                     |
| `hash`                | string(64)      | Evita duplicidades                                  |
| `status`              | enum            | `processando`, `importado`, `conciliado`, `erro`    |
| `meta`                | json nullable   | Informações do parser (OFX/CSV)                     |
| timestamps            |                 |                                                     |

### 11. Linhas de Extrato (`bank_statement_lines`)

| Campo                   | Tipo            | Observações                                        |
| ----------------------- | --------------- | -------------------------------------------------- |
| `id`                    | PK              |                                                    |
| `bank_statement_id`     | FK              |                                                    |
| `linha`                 | unsigned int    | Ordem original                                    |
| `transaction_date`      | date            |                                                    |
| `description`           | string(255)     |                                                    |
| `amount`                | decimal(15,2)   | Crédito positivo, débito negativo                  |
| `balance`               | decimal(15,2)   | Saldo após movimento (quando disponível)           |
| `document_number`       | string(60) null |                                                    |
| `fit_id`                | string(80) null | Identificador único de OFX                         |
| `match_status`          | enum            | `nao_casado`, `sugerido`, `confirmado`, `ignorado` |
| `matched_installment_id`| FK nullable     | -> `journal_entry_installments`                     |
| `matched_by`            | FK nullable     | Usuário que confirmou                              |
| `match_meta`            | json nullable   | Critérios usados no match                          |
| timestamps              |                 |                                                    |

### 12. Logs de Match (`bank_statement_matches`)

Tabela associativa para histórico de conciliações automáticas/manuais.

| Campo                   | Tipo         | Observações                                         |
| ----------------------- | ------------ | --------------------------------------------------- |
| `id`                    | PK           |                                                     |
| `bank_statement_line_id`| FK           |                                                     |
| `installment_id`        | FK nullable  |                                                     |
| `journal_entry_id`      | FK nullable  | Quando match direto sem parcela                     |
| `matched_at`            | datetime     |                                                     |
| `matched_by`            | FK nullable  |                                                     |
| `confidence`            | decimal(5,2) | Percentual 0–100                                    |
| `notes`                 | string       |                                                     |

## Ajustes em Tabelas Existentes

- `cost_centers`: adicionar `tipo` (`fixo`, `variavel`, `investimento`), `ativo`
  e `orcamento_anual` (decimal) para relatórios de orçamento.
- `pessoas`: garantir flag `eh_fornecedor`/`eh_cliente` via papéis para
  alimentar combobox de lançamentos.
- `imoveis`: expor `codigo_externo`/`apelido` para vínculos rápidos.
- `users`: sem alterações além de relacionamento com recibos/conciliações.

## Regras e Fluxos

- **Criação de lançamento**:
  1. Usuário escolhe conta, tipo, categoria, imóvel, pessoa e descrição.
  2. Define valor total, datas e se terá parcelamento (gera registros em
     `journal_entry_installments`).
  3. Pode ratear valor entre imóveis/centros de custo pela tela de alocações.

- **Parcelamento automático**:
  - Serviço `GenerateInstallmentsService` calcula parcelas iguais ou customizadas.
  - Ao pagar parcela, atualizar `payment_date`, `status` e propagar para
    `journal_entries.paid_installments`.

- **Clonagem**:
  - `CloneJournalEntryService` duplica lançamento, incluindo rateios e anexos
    opcionais. Campo `origin=clonado` e `clone_of_id` apontam para o original.

- **Recibos**:
  - `GenerateReceiptService` cria registro em `financial_receipts` e delega para
    `GenerateReceiptPdfJob` (fila `receipts`) gerar o PDF usando
    `resources/views/pdf/financial-receipt.blade.php`.
  - Arquivos ficam em `storage/app/financeiro/receipts/{journal_entry_id}` e o
    download é exposto via API para a UI.
- **Anexos**:
  - Upload em `journal_entry_attachments` com armazenamento em
    `financeiro/attachments/{journal_entry_id}`; opcionalmente vincular ao campo
    `installment_id`.
  - API expõe listagem/download/remoção; permissão depende da operação (`view`
    ou `update` do lançamento).

- **Conciliação bancária**:
  - Importar extrato -> armazenar em `bank_statements` + linhas.
  - Serviço `SuggestMatchesService` tenta casar linhas com parcelas (valor, data,
    descrição). Linhas sugeridas ficam `match_status = sugerido`.
  - Usuário confirma/ajusta via interface -> atualiza `journal_entry_installments`
    (pagamento) e marca `bank_statement_lines.match_status = confirmado`.
  - Ao final do período, `CloseReconciliationService` gera registro em
    `financial_reconciliations` e trava ajustes.

## Pendências para Validação

- Convenção de enumerações (usar `spatie/enum` ou enums nativos PHP 8.2?).
- Necessidade de multimoeda real (taxa de conversão armazenada por parcela?).
- Campos extras para obrigações fiscais (número da nota, chave DF, etc.) devem
  entrar em `journal_entry_installments.meta`.
- Regras de permissão granular por conta (ex.: operador vê apenas contas
  específicas?) — pode exigir tabela pivot `financial_account_user`.

## Próximos Passos

1. Aprovar este modelo com área financeira/contábil.
2. Definir estratégias de migração de dados legados (`financial_transactions`,
   `payment_schedules`) para a nova estrutura.
3. Iniciar implementação das migrations com feature flags para rollout
   progressivo.

## Plano de Migração de Dados (ETL)

### Objetivos

- Migrar os registros existentes de `financial_transactions` para `journal_entries`
  preservando históricos de centro de custo, contratos e faturas.
- Converter agendamentos (`payment_schedules`) em parcelas
  (`journal_entry_installments`) quando vinculados a lançamentos.
- Popular `journal_entry_descriptions`, `financial_categories` e novos campos
  das contas financeiras utilizando dados existentes ou defaults.

### Estratégia Geral

1. **Congelamento**: executa migration em janela planejada e bloqueia criação
   de novos lançamentos enquanto o script roda.
2. **ETL baseada em batches**: processar em lotes pequenos (ex.: 500 registros)
   para evitar uso excessivo de memória.
3. **Logs e auditoria**: registrar métricas (total migrado, inconsistências)
   numa tabela técnica (`finance_migration_logs`) ou arquivo de log.
4. **Rollback**: manter backup das tabelas antigas até validação final.

### Scripts Recomendados

#### 1. `php artisan finance:migrate-transactions`

Responsável por ler `financial_transactions` e gerar `journal_entries`.

Fluxo principal:

1. Agrupar transações por `id` (já são unitárias).
2. Determinar `type`:
   - `credito` → `receita`
   - `debito` → `despesa`
   - Transferências: identificar pares por `meta->transferencia` (se existir)
     para gerar lançamento `transferencia`.
3. Montar payload:
   - `bank_account_id` = `account_id`
   - `financial_category_id`: mapear por regra (`meta.categoria` ou criar
     categoria default `Outros`). Guardar mapa em cache local.
   - `cost_center_id`, `contrato_id`, `fatura_id` mantidos.
   - `movement_date` = `data_ocorrencia`; `due_date` = `meta.vencimento`
     (fallback `movement_date`).
   - `amount` = `valor`.
   - `status`: mapear `status` atual (`pendente` → `pendente`, `conciliado`
     → `pago`, `cancelado` → `cancelado`).
   - `origin` = `manual`.
4. Gerar parcela única em `journal_entry_installments`:
   - Número 1, valor = `amount`, status herdado.
   - `payment_date` = `meta.pagamento_data` (ou nulo).
5. Copiar anexos e metadados:
   - Transferir `meta->anexos` para `journal_entry_attachments`.
6. Registrar descrição:
   - Normalizar `descricao` para popular `journal_entry_descriptions`.
   - Usar `description_id` quando repetida (consultando contexto).
7. Atualizar contadores (`installments_count`, `paid_installments`,
   `attachments_count`).
8. Persistir relação de IDs para auditoria (oldID → newID).
9. Marcar transação antiga como migrada (campo temporário `meta.migrado`).

Pseudo código:

```php
DB::transaction(function () use ($transaction) {
    $description = app(DescriptionResolver::class)->resolve($transaction->descricao);

    $entry = JournalEntry::create([
        'type' => $transaction->tipo === 'credito' ? 'receita' : 'despesa',
        'bank_account_id' => $transaction->account_id,
        'financial_category_id' => $this->categoryMapper->map($transaction),
        'cost_center_id' => $transaction->cost_center_id,
        'property_id' => $this->propertyMapper->map($transaction),
        'person_id' => $this->personMapper->map($transaction),
        'description_id' => $description?->id,
        'description_custom' => $description ? null : $transaction->descricao,
        'movement_date' => $transaction->data_ocorrencia,
        'due_date' => $transaction->meta['vencimento'] ?? $transaction->data_ocorrencia,
        'payment_date' => $transaction->meta['data_pagamento'] ?? null,
        'amount' => $transaction->valor,
        'currency' => 'BRL',
        'status' => $this->statusMapper->map($transaction->status),
        'installments_count' => 1,
        'paid_installments' => $transaction->status === 'conciliado' ? 1 : 0,
        'created_by' => $transaction->created_by,
        'updated_by' => $transaction->updated_by,
    ]);

    $entry->installments()->create([
        'numero_parcela' => 1,
        'movement_date' => $transaction->data_ocorrencia,
        'due_date' => $transaction->meta['vencimento'] ?? $transaction->data_ocorrencia,
        'payment_date' => $transaction->meta['data_pagamento'] ?? null,
        'valor_principal' => $transaction->valor,
        'valor_total' => $transaction->valor,
        'status' => $this->statusMapper->mapInstallment($transaction->status),
        'meta' => Arr::only($transaction->meta ?? [], ['observacao', 'comprovante']),
    ]);

    $this->attachmentMigrator->migrate($transaction, $entry);
    $this->logger->record($transaction->id, $entry->id);
});
```

#### 2. `php artisan finance:migrate-payment-schedules`

Objetivo: transformar registros de `payment_schedules` em lançamentos
parcelados ou agendamentos futuros.

Regras:

- Quando `meta['transaction_ids']` existir, vincular às novas parcelas criadas.
- Caso contrário, gerar lançamentos `origin = parcelado` com parcelas
  correspondentes (`total_parcelas`, `valor_total / parcelas`).
- Status `quitado` → todas parcelas `pago`; `em_atraso` ajusta `status` para `atrasado`.

Outputs:

- Novos `journal_entries` com `installments_count = total_parcelas`.
- Parcela atual = `parcela_atual`; datas a partir de `vencimento`.
- Histórico no log de migração.

#### 3. `php artisan finance:migrate-account-balances`

Após migrar lançamentos, recalcular `saldo_atual` das contas:

1. `saldo_atual = saldo_inicial`.
2. Somar receitas pagas – despesas pagas (ignorando planejados/pendentes).
3. Debitar transferências pagas da conta origem e creditar na conta destino.
4. Atualizar também `financial_reconciliations` se existir histórico anterior.

#### 4. Seed Inicial de Categorias

Migration ou Seeder para popular categorias bases:

```php
FinancialCategory::create([
    'codigo' => '1.0',
    'nome' => 'Receitas Operacionais',
    'tipo' => 'receita',
]);

FinancialCategory::create([
    'codigo' => '2.0',
    'nome' => 'Despesas Operacionais',
    'tipo' => 'despesa',
]);
```

Durante ETL, usar ou criar dinamicamente filhos (`2.1 - Manutenção`, etc.).

### Validação Pós-Migração

1. Conferir contagem de lançamentos e valores totais por conta (comparar com
   relatório anterior).
2. Validar amostras (10%) com a equipe financeira.
3. Rodar testes automatizados (`php artisan test`) garantindo integridade.
4. Revisar tabela de log para entradas com erro (ex.: categorias não mapeadas).

### Rollback

- Caso algo falhe, dropar novas tabelas (`journal_*`, `financial_*` novos) e
  restaurar backup das tabelas antigas.
- Script `finance:rollback-migration` pode ler log de IDs para remover
  inserções feitas e limpar referências cruzadas.
