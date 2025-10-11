# Auditoria

O sistema registra eventos de criação, atualização e exclusão das principais
entidades, permitindo rastreabilidade e exportação.

## Entidade

- Tabela: `audit_logs`
- Campos principais: `user_id`, `action`, `auditable_type`, `auditable_id`,
  `payload` (JSON), `ip_address`, `user_agent`, timestamps.

## Como funciona

- O `App\\Observers\\AuditableObserver` observa: `User`, `Pessoa`, `Imovel`,
  `Contrato`, `Fatura`, `FinancialAccount`, `FinancialTransaction`,
  `PaymentSchedule` e `CostCenter`.
- Em `created`: registra `after` com atributos visíveis.
- Em `updated`: registra `before/after` somente das colunas alteradas.
- Em `deleted`: registra `before` com atributos visíveis.

## API

| Método | Endpoint                | Descrição                                    |
| ------ | ----------------------- | -------------------------------------------- |
| GET    | `/api/auditoria`        | Lista registros com filtros de texto e datas |
| GET    | `/api/auditoria/export` | Exporta CSV/JSON conforme parâmetros         |

Permissões necessárias:

- `auditoria.view` para listagem
- `auditoria.export` para exportar

## Integração no Login

- O `AuthenticatedSessionController` registra `auth.login`/`auth.logout` via
  `App\Services\AuditLogger`.
