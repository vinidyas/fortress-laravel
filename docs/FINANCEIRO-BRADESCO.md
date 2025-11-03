# Integração de Boletos Bradesco

> Status: especificação aguardando documentação oficial do banco.

## Visão Geral

- **Gateway e client**: `App\Services\Banking\Bradesco\BradescoBoletoGateway` usa `BradescoApiClient` (ou `FakeBradescoApiClient` em ambientes que definirem `BRADESCO_USE_FAKE=true`).
- **Persistência**: os boletos são armazenados em `fatura_boletos` e relacionados a `Fatura` via `fatura->boletos()`.
- **Eventos importantes**:
  - `BoletoRegistered`: disparado ao emitir um novo boleto.
  - `BoletoPaid`: disparado em confirmações de pagamento via webhook/polling.
  - `BoletoCanceled`: disparado em cancelamentos.
  - Listener `LogBoletoEvent` grava tudo no canal `bradesco` (`storage/logs/bradesco.log`) com dados mascarados.
- **Jobs**:
  - `ProcessBradescoWebhookPayload` processa notificações assíncronas.
  - `SyncPendingBradescoBoletos` faz polling de contingência (agendado a cada 15 minutos).

## Variáveis de ambiente

| Variável | Descrição |
| --- | --- |
| `BRADESCO_BASE_URL` | URL base da API (homologação/produção) |
| `BRADESCO_CLIENT_ID` / `BRADESCO_CLIENT_SECRET` | Credenciais OAuth |
| `BRADESCO_CERT_PATH` / `BRADESCO_CERT_PASSWORD` | Caminho e senha do certificado | 
| `BRADESCO_WEBHOOK_SECRET` | Token compartilhado do webhook |
| `BRADESCO_ENV` | `sandbox` ou `production` |
| `BRADESCO_TIMEOUT` | Timeout das requisições (segundos) |
| `BRADESCO_USE_FAKE` | Quando `true`, usa o client fake para desenvolvimento/teste |
| `LOG_BRADESCO_LEVEL` | Nível de log específico para o canal `bradesco` |

## Mock / Ambiente fake

- Em desenvolvimento local, defina `BRADESCO_USE_FAKE=true` (já configurado em `.env.example`).
- O `FakeBradescoApiClient` guarda registros em memória, emite IDs incrementais e simula retorno de cancelamento/pagamento.
- Para testes, é possível combinar com `Http::fake()` caso seja necessário validar serialização dos payloads antes da documentação oficial.
- Para converter dados históricos, execute `php artisan bradesco:sanitize-boleto-payloads` após deploys que envolvam ajustes de mascaramento (use `--dry-run` para validar antes).

### Payloads esperados (provisórios)

```jsonc
// Emissão de boleto (payload enviado)
{
  "pagador": {
    "nome": "John Doe",
    "documento": "12345678901",
    "endereco": { /* ... */ }
  },
  "valor": 1250.50,
  "vencimento": "2025-11-05",
  "numeroDocumento": "FAT-12345",
  "multa": { /* opcional */ },
  "juros": { /* opcional */ },
  "instrucoes": ["Texto livre"]
}
```

```jsonc
// Resposta esperada (simplificada)
{
  "id": "ABC123",
  "nossoNumero": "1234567890",
  "linhaDigitavel": "23790...",
  "codigoBarras": "2379...",
  "valor": 1250.50,
  "vencimento": "2025-11-05",
  "status": "registered",
  "urlPdf": "https://.../boleto.pdf"
}
```

> O schema definitivo deve ser atualizado assim que o Bradesco disponibilizar a documentação.

## Observabilidade & Auditoria

- Todos os eventos relevantes são registrados em `storage/logs/bradesco.log` com dados sensíveis mascarados.
- As tabelas `fatura_boletos` e `audit_logs` (quando configurado) guardam o histórico completo das alterações dos boletos.
- Os webhooks ficam em `/api/webhooks/bradesco` e exigem o header `X-Webhook-Token` (mesmo valor de `BRADESCO_WEBHOOK_SECRET`).
- Sempre que o formato de mascaramento for ajustado, rode `php artisan bradesco:sanitize-boleto-payloads` para higienizar boletos já existentes no banco.
- Caso seja necessário armazenar payloads completos para auditoria, avaliar uso de criptografia em repouso (por exemplo via casts encriptados ou coluna separada com `Crypt::encrypt`).

## Preparação para Produção

- **Certificados e chaves mTLS**: provisionar os arquivos `.cer` e `.key` no servidor aplicando permissões restritivas e atualizar as variáveis `BRADESCO_TLS_CERT_PATH`, `BRADESCO_TLS_KEY_PATH` e `BRADESCO_TLS_KEY_PASS`. Ideal manter o material sensível fora do repositório, usando secret manager ou volume dedicado.
- **Workers dedicados**: garantir `queue:work` com a fila `boletos` separada (vide serviço `fortress-queue` no `docker-compose`) e monitorar consumo/latência via Horizon ou stack equivalente.
- **Alertas operacionais**: configurar monitoramento para falhas de token (`bradesco.log`), exceções de webhook (status != 200) e para o job `sync-pending-bradesco-boletos`. Integrar esses alertas ao Slack/Observability do time financeiro.
- **Fluxo fim a fim no sandbox**: antes do go-live, executar o roteiro emissão ➝ recebimento do webhook ➝ sincronização via job (`php artisan bradesco:create-dummy-invoice` ajuda a montar o cenário). Validar que o webhook atualiza fatura/transactions e que não restam boletos pendentes após o `sync`.
- **Checklist pós-deploy**: rodar `php artisan bradesco:sanitize-boleto-payloads --dry-run` para monitorar dados legados, validar conectividade com o endpoint de token (`php artisan bradesco:test-auth`) e garantir que o webhook (`/api/webhooks/bradesco`) está acessível apenas para o Bradesco via rede e token.

## Próximos passos (aguardando doc oficial)

1. Mapear endpoints definitivos (autenticação, emissão, consulta, cancelamento).
2. Ajustar a montagem dos payloads no `BradescoBoletoGateway` conforme a especificação real.
3. Validar políticas de juros/multa, liquidação parcial e reemissão.
4. Implementar download real do PDF caso o banco forneça link autenticado/tokenizado.
5. Escrever testes end-to-end com a API real do Bradesco na fase de homologação.
