# Auditoria

O módulo registra todas as operações sensíveis do sistema (financeiro, cadastros, autenticação) na tabela `audit_logs`, permitindo rastrear quem fez o quê, quando e de onde.

## Permissões

| Permissão | Descrição |
| --- | --- |
| `auditoria.view` | Acessar listagem e pesquisar registros |
| `auditoria.export` | Exportar consultas para CSV ou JSON |

## Estrutura dos Logs

| Campo | Descrição |
| --- | --- |
| `user_id` | ID do usuário autenticado (ou `null` em eventos de sistema) |
| `action` | Nome da ação, ex.: `financial_transaction.created` |
| `auditable_type` / `auditable_id` | Classe e chave primária do recurso alterado |
| `payload` | Diff com `before`/`after` ou qualquer metadado adicional |
| `ip_address` / `user_agent` | Origem da requisição |
| `created_at` | Data/hora do registro |

> O observer `App\Observers\AuditableObserver` é registrado em `AppServiceProvider` para as principais models.

## APIs

| Método | Endpoint | Descrição |
| --- | --- | --- |
| GET | `/api/auditoria` | Lista registros conforme filtros abaixo |
| GET | `/api/auditoria/export` | Exporta resultado filtrado (`format=csv|json`) |

### Filtros aceitos

- `action`
- `user_id`
- `auditable_type`
- `ip_address`
- `date_from`, `date_to`
- `search` (busca em `payload`, `action`, `user_agent`, `ip_address`)
- `per_page` (1–100)

### Exportação

| Formato | MIME | Observações |
| --- | --- | --- |
| CSV | `text/csv; charset=UTF-8` | Colunas: `ID, Data, Usuário, Ação, Recurso, IP, Payload` |
| JSON | `application/json; charset=UTF-8` | Estrutura `generated_at` + array de objetos com os campos principais |

## Interface

A tela `Auditoria/Index.vue` oferece:

- Filtros completos (seguindo a lista acima) e paginação preservada via Inertia.
- Visualização expandida por linha com diff `before/after` destacado (quando disponível) e payload em JSON.
- Exportação CSV/JSON respeitando os filtros atuais.

## Importação de Legado

O comando lê a base antiga mapeada via `LEGACY_DB_*` e replica os registros em `audit_logs`.

```bash
php artisan legacy:import --auditoria --dry-run
php artisan legacy:import --auditoria
```

Ele pressupõe que os usuários já tenham sido importados para manter as referências `user_id`.

## Boas Práticas

- Revise periodicamente os eventos de login, conciliações e ações administrativas.
- Combine filtros por período (`date_from/date_to`) com `action` para auditorias específicas (ex.: `financial_transaction.updated`).
- Habilite a permissão `auditoria.export` apenas para perfis realmente responsáveis por compliance.
