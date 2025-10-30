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

## Próximos passos (aguardando doc oficial)

1. Mapear endpoints definitivos (autenticação, emissão, consulta, cancelamento).
2. Ajustar a montagem dos payloads no `BradescoBoletoGateway` conforme a especificação real.
3. Validar políticas de juros/multa, liquidação parcial e reemissão.
4. Implementar download real do PDF caso o banco forneça link autenticado/tokenizado.
5. Escrever testes end-to-end com a API real do Bradesco na fase de homologação.
