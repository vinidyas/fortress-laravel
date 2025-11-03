# Portal do Locatário (Roadmap)

## Objetivo
Oferecer aos locatários um ambiente autenticado onde possam:
- Visualizar contratos ativos.
- Emitir 2ª via de boletos, com acesso ao PDF e linha digitável.
- Acompanhar status de pagamento (pago, aberto, cancelado) com histórico.
- Receber notificações sobre vencimentos e baixas confirmadas.

## Fase 1 — Área autenticada dentro do Fortress (Laravel + Inertia)

### Escopo inicial
- Guard/permissão `tenant` atrelada ao modelo `users` ↔ `pessoas`.
- Rotas e layout separados (`/portal/…`) reaproveitando stack Inertia existente.
- Páginas principais:
  - Dashboard com resumo (contratos ativos, faturas abertas, próximos vencimentos).
  - Listagem de faturas/boletos com status e ação “baixar 2ª via”.
  - Detalhe do contrato (datas, valores, histórico de reajustes).
- Autenticação local (e-mail/senha) ou convite enviado pelo administrativo.
- Integração direta com as tabelas atuais:
  - `faturas`/`fatura_boletos` para status e PDFs.
  - `contratos` para dados gerais do aluguel.
  - Eventos de webhook/polling já atualizam os dados que serão exibidos.

### Domínio dedicado (`portal.*`)
- Definir `PORTAL_DOMAIN` e `PORTAL_URL` no `.env` (ex.: `portal.fortressempreendimentos.com.br`).
- Incluir o domínio nas variáveis de sessão/cookies: `SANCTUM_STATEFUL_DOMAINS` e `SESSION_DOMAIN`.
- Garantir que o proxy (Traefik/nginx) encaminhe `portal.*` para o mesmo container Laravel.
- Após ajustar rotas, regenere o Ziggy: `php artisan ziggy:generate resources/js/ziggy.js`.
- Testar o fluxo acessando `https://portal....` para confirmar redirecionamentos e autenticação dos locatários.

### Implementado até agora
- Guard middleware `tenant` + APIs `/portal/contratos`, `/portal/faturas`, `/portal/faturas/{id}`.
- Layout e páginas Inertia (`Portal/Contracts`, `Portal/Invoices`, `Portal/Dashboard`).
- Compartilhamento automático do perfil do locatário (`portalTenant`) via `HandleInertiaRequests`.
- Endpoint administrativo `POST /api/admin/portal/tenant-users` que cria/atualiza o usuário portal e dispara reset de senha (`Password::sendResetLink`).

### Próximos itens da fase 1
- Tela administrativa para disparar convites (UI em cima do endpoint).
- Customizar e-mail/template do convite com instruções do portal.
- Ajustar notificações (ex.: banners de fatura paga via webhook).
- Instrumentar métricas de uso (quantos locatários ativos, último acesso).

### Itens técnicos
- Criar relação `users.pessoa_id` para vincular locatário ao usuário do portal.
- Endpoint administrativo para criar usuário (`POST /api/admin/portal/tenant-users`).
- Policy/guard: restringir acesso a recursos ligados à `pessoa_id`.
- Componentes Vue/Layouts específicos para o portal (tema leve/mobile).
- Ajustes em `BradescoWebhook`/`SyncPendingBradescoBoletos` para disparar eventos de notificação (e-mail opcional).

### Entregáveis
- Documentação de onboarding (como o locatário recebe acesso).
- Scripts/artisan para criar usuários de portal a partir de locatários existentes.
- Testes Feature (rotas portal) garantindo isolamento de dados.

## Fase 2 — SPA externa (opcional)
> Evoluir para um front independente consumindo a API REST.

- Novo app (Vue/React) hospedado em `portal.fortressempreendimentos.com.br`.
- Endpoints dedicados (`/api/portal/...`) com autenticação via Sanctum/token.
- Permite evolução do portal sem impactar o back-office; suporta branding diferente.
- Pode ser a base para um futuro app mobile/PWA (fase 3).

## Fase 3 — PWA / App híbrido (opcional)
> Estender experiência para mobile-first com notificações push.

- Reuso da API criada na fase 2.
- Implementação de PWA (cache offline, push para vencimentos/baixas).
- Alternativa: wrapper híbrido (Capacitor/React Native) usando as mesmas rotas.

## Dependências gerais
- Workers e scheduler operacionais (`queue:work boletos,...`, `schedule:run`) para manter status de boletos e notificações em dia.
- Webhook Bradesco configurado em produção.
- Gestão de comunicação (e-mail/SMS/future push) centralizada.

## Próximos passos imediatos
1. Modelar `users` ↔ `pessoas` e permissões `tenant`.
2. Definir wireframes das telas do portal (dashboard, faturas, contratos).
3. Criar rotas Inertia e components base.
4. Documentar processo de convite/onboarding:
   - `POST /api/admin/portal/tenant-users` com `pessoa_id` + `email` cria/atualiza usuário e dispara reset de senha.

Manter este documento atualizado conforme avançarmos pelas fases.
