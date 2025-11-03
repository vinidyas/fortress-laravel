# Manual do Usuário – Fortress Gestão Imobiliária

> **Objetivo**  
> Este documento orienta administradores e locatários no uso diário da plataforma Fortress, cobrindo acesso, principais módulos, fluxos operacionais e integrações bancárias. Use-o como referência rápida durante treinamentos ou operações de rotina.

---

## 1. Visão geral da plataforma

- **Back-office (https://sistema.fortressempreendimentos.com.br):** ambiente administrativo para cadastros, contratos, emissão de faturas/boletos, conciliação financeira e relatórios.  
- **Portal do locatário (https://portal.fortressempreendimentos.com.br):** área exclusiva para acompanhamento de faturas, download de boletos e emissão de recibos já pagos.  
- **Integração bancária:** emissão/consulta/baixa de boletos com o Bradesco (sandbox e produção).  
- **Perfis de acesso:** administradores, equipe financeira, equipe operacional e locatários convidados.

---

## 2. Perfis e permissões

| Perfil                | Acesso principal                                          | Observações                                                                 |
|-----------------------|-----------------------------------------------------------|------------------------------------------------------------------------------|
| **Administrador**     | Todos os módulos, gestão de usuários e permissões         | Responsável por liberar convites ao portal e parametrizar integrações       |
| **Financeiro**        | Cadastros financeiros, faturas, conciliações, relatórios  | Deve zelar pelas credenciais Bradesco e monitorar webhooks                  |
| **Operacional**       | Cadastros de pessoas, imóveis, contratos, relatórios      | Atua no dia a dia de contratos/faturas, sem acessar configurações críticas  |
| **Locatário**         | Portal com faturas, boletos, recibos                      | Recebe convite individual; login por e-mail e senha                         |

> **Dica:** mantenha a lista de usuários atualizada em **Administração → Usuários** e revogue acessos antigos.

---

## 3. Primeiros passos

1. **Credenciais e ambientes:** confirmar `.env` (APP_URL, PORTAL_URL, BRADESCO_*).  
2. **Acesso inicial:**  
   - Admin: `https://sistema.../login` (usuário + senha).  
   - Locatário: `https://portal.../login` (e-mail + senha do convite).  
3. **Reset de senha:** link “Esqueci minha senha” em cada ambiente; o locatário recebe link já apontando para o portal.  
4. **Navegadores suportados:** Chrome, Edge, Firefox; preferir versão atual.  
5. **Suporte:** criar canal único (e-mail ou helpdesk) para registros de incidentes.

---

## 4. Painel administrativo (back-office)

### 4.1 Dashboard
- Resumo de imóveis, contratos e faturas em aberto.  
- Alertas de permissões ou falhas em integrações.  
- Atalhos para relatórios recentes.

### 4.2 Cadastros

#### Pessoas
- **Obrigatórios para locatários:** nome, CPF/CNPJ, e-mail, telefones, endereço completo.  
- **Máscaras automáticas:** CPF/CNPJ, telefone e CEP são higienizados antes de salvar.  
- **Papéis:** Locatário, Proprietário, Fiador, etc. Habilite “Locatário” para uso do portal.

#### Imóveis
- Campos principais: código, proprietário, endereço, valores (locação, condomínio, IPTU).  
- Cadastro de comodidades, área e fotos/anexos (opcional).  
- Responsável e agenciador podem ser o próprio proprietário quando for apenas para teste.

#### Contratos
- Associe locador, locatário, imóvel e condições comerciais.  
- Defina vencimento, valor do aluguel, reajuste, multas.  
- Vincule documentos (ex.: contrato assinado) quando necessário.

### 4.3 Faturas e boletos
- **Gerar fatura:** em **Financeiro → Faturas**, criar nova (manual) ou usar geração automática pelo contrato.  
- **Emitir boleto Bradesco:** acessar fatura → “Gerar boleto”; aguardar status “Registrado”.  
- **Baixar** e **reenviar** boletos: opções na tela da fatura após registro.  
- **Baixa manual:** se necessário, atualizar status para “Paga” e anexar recibo/pagamento.

### 4.4 Conciliação e relatórios
- **Conciliação bancária:** importar extratos, conferir lançamentos pendentes e ajustar divergências.  
- **Relatórios:** financeiros, operacionais e personalizados por período.  
- **Exportações:** PDF/Excel onde aplicável (ex.: razão bancária).

### 4.5 Administração
- **Usuários e permissões:** criar contas, definir funções e papéis.  
- **Portal de locatários:** visualizar lista de convites, status de acesso, reenviar senhas.  
- **Auditoria:** trilha de ações para governança e compliance.

### 4.6 Relatórios disponíveis
- **Financeiro → Relatórios**: fluxo de caixa, extratos, inadimplência.  
- **Cadastros → Relatórios**: listagens de pessoas, imóveis e contratos.  
- **Download**: gerar PDF ou Excel onde disponível (indicado na interface).  
- **Agendamento** (roadmap): considere incluir em releases futuros se houver demanda.

---

## 5. Fluxos operacionais recomendados

### 5.1 Cadastro completo (novo contrato)
1. Cadastrar locatário com dados obrigatórios.  
2. Cadastrar proprietário e imóvel (se não existir).  
3. Criar contrato associando locatário, proprietário e imóvel.  
4. Gerar faturas (manual ou recorrente).  
5. Emitir boletos via Bradesco.  
6. Enviar convite ao portal para o locatário acompanhar as faturas.

### 5.2 Emissão e acompanhamento de boletos Bradesco
1. Verificar `.env` (credenciais, certificado, BRADESCO_SANDBOX_USE_FIXTURES).  
2. Gerar fatura e clicar em “Gerar boleto”.  
3. Monitorar retorno no log (`storage/logs/bradesco-response.json`).  
4. Webhook/polling atualiza status; se falhar, usar reprocessamento manual.  
5. Para baixa: confirmar pagamento (webhook, conciliação ou baixa manual) e emitir recibo.

### 5.3 Convite ao portal do locatário
1. Certificar que o locatário possui e-mail válido e papel “Locatário”.  
2. Abrir **Administração → Locatários do Portal** e enviar convite.  
3. Locatário recebe e-mail com link para definir senha.  
4. Monitorar acessos (último login) na mesma tela.

### 5.4 Relatórios e auditoria
- Gerar relatório de faturas em aberto antes de virar o mês.  
- Usar trilha de auditoria para rastrear alterações críticas (contratos, boletos, configurações).  
- Exportar conciliação mensal e arquivar junto com extratos bancários.

---

## 6. Portal do locatário

### 6.1 Acesso
- Endereço: `https://portal.fortressempreendimentos.com.br`.  
- Login: e-mail + senha definidos no convite.  
- Reset de senha envia link apontando para o mesmo domínio (vale até expirar).

### 6.2 Página “Minhas faturas”
- Tabela com: número, contrato, competência, vencimento, status, valor.  
- **Ações exibidas quando disponíveis:**  
  - `Baixar boleto`: link direto para o PDF.  
  - `Copiar código`: copia linha digitável/código de barras.  
  - `Recibo (PDF)`: aparece quando a fatura está “Paga”.  
- Caso não haja faturas cadastradas ou boleto emitido, a coluna de ações permanece vazia.

### 6.3 Suporte ao locatário
- Dúvidas sobre login ou senha: acionar administradores para reenviar convite.  
- Divergências financeiras: orientá-lo a anexar comprovante diretamente para equipe financeira.

### 6.4 Dúvidas frequentes (FAQ inicial)
- **Não recebi o convite.** Verifique caixa de spam; admin pode reenviar em *Administração → Locatários do Portal*.  
- **Boleto não aparece.** Confirme se o boleto foi emitido no back-office (status “Registrado”).  
- **Esqueci a senha.** Clique em “Esqueci minha senha” no portal e siga o link recebido por e-mail.  
- **Problema de acesso em múltiplos dispositivos.** O portal suporta login mobile; limpe cache ou use aba anônima em caso de conflito.

---

## 7. Integração Bradesco – referências rápidas

| Item                                  | Sandbox                                               | Produção                                              |
|---------------------------------------|-------------------------------------------------------|-------------------------------------------------------|
| Base URL                              | `https://openapisandbox.prebanco.com.br`              | Liberada após aprovação (formato idêntico)            |
| Certificados                          | `.pem`/`.crt` de teste                                | Certificado A1 emitido por autoridade certificadora   |
| Credenciais                           | Client ID/Secret sandbox (visibilidade 3 dias)        | Requer assinatura e aprovação do Gestor de APIs       |
| Fluxo recomendado                     | Emissão → webhook → fallback de polling               | Igual ao sandbox                                      |
| Logs úteis                            | `storage/logs/bradesco-response.json`                 | Manter rotação de logs e monitorar erros 4xx/5xx      |
| Variáveis `.env`                      | `BRADESCO_*` + `BRADESCO_SANDBOX_USE_FIXTURES`        | Ajustar `BRADESCO_BASE_URL` + certificados de prod    |

> **Checklist antes do go-live:** testar emissão e baixa com dados reais, validar webhooks, configurar alertas de falha de token e monitoria de filas.

---

## 8. Checklists operacionais

### Periodicidade diária
- Verificar alertas no dashboard.  
- Conferir filas de processamento (`queue:work`).  
- Acompanhar faturas emitidas no dia e status dos boletos.

### Semanal
- Revisar conciliação bancária.  
- Checar convites pendentes do portal.  
- Atualizar cadastros com dados incompletos.

### Mensal
- Rodar relatório de faturas vencidas e inadimplência.  
- Exportar relatórios financeiros para contabilidade.  
- Revisar configurações do Bradesco e validade de certificados.

---

## 9. Suporte e troubleshooting

- **Erros comuns:**
  - `This action is unauthorized.` → verificar permissões ou papel do locatário.  
  - Boleto não aparece no portal → confirmar registro no back-office e status “Registrado”.  
  - Webhook não recebe notificações → testar conectividade, logs do Traefik, reprocessar fila.
- **Logs principais:** `storage/logs/laravel.log`, `storage/logs/bradesco-response.json`, auditoria.  
- **Contato interno:** definir responsáveis por TI, financeiro e atendimento ao locatário.

### 9.1 Política de suporte (sugestão)
- **Horário de atendimento:** 08h–18h (dias úteis).  
- **Canais:**  
  - Suporte financeiro: financeiro@fortressempreendimentos.com.br  
  - TI/integridade do sistema: ti@fortressempreendimentos.com.br  
  - Atendimento ao locatário: atendimento@fortressempreendimentos.com.br  
- **SLA recomendado:**  
  - Incidentes críticos (boletos, login indisponível): resposta em até 2h, solução em até 8h.  
  - Dúvidas gerais/ajustes cadastrais: resposta em até 1 dia útil.  
  - Melhorias e sugestões: registrar backlog e tratar em reuniões quinzenais.

---

## 10. Próximos passos

- Manter este manual versionado (Git) e atualizar após cada release.  
- Produzir vídeos curtos ou GIFs para fluxos críticos (cadastro, geração de boleto, portal).  
- Incluir FAQ resumida no próprio portal e no e-mail de convite aos locatários.

---

*Atualizado em: {{ data do commit }}. Para sugestões ou correções, abra uma issue ou contate a equipe de desenvolvimento.*
