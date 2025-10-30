# Fortress Gestão Imobiliária

Aplicação Laravel 11 + Vue 3 (Inertia, Vite, Pinia) e Tailwind para administração de imóveis, contratos, faturamento e auditoria.

## Requisitos

- PHP 8.2+
- Composer
- Node.js 18+ (recomendado 20) com npm
- MySQL 8 (produção) — nos testes é usado SQLite in‑memory
- Redis opcional para sessões/filas
- Extensões PHP essenciais: `mbstring`, `pdo_mysql`, `openssl`, `xml`, `json`, `bcmath`, `ctype`, `fileinfo`, `curl`, `zip`, `gd`

## O que foi adicionado/revisado recentemente

- Menu do usuário no topo direito (persistente): avatar, nome, username, Configurações da conta, Alterar senha e Desconectar. O menu foi refeito com Teleport para garantir cliques confiáveis e sobreposição correta.
- Upload de avatar com redimensionamento automático para 256px via Intervention Image v3; arquivo servido pelo `storage/public`.
- Fluxo de “Esqueci minha senha”/“Redefinir senha” com e‑mail, incluindo páginas Inertia (Forgot/Reset), controllers e validações.
- Tela “Configurações da conta” (perfil): alterar nome, e‑mail e foto; seção para alterar senha autenticado.
- Administração de usuários: suportes a avatar, e‑mail e envio de link de redefinição direto da listagem/formulário.
- Contratos: campos de data com padrão brasileiro. Datas `dd/mm/aaaa` (início, fim, entrega de chaves) e mês/ano `mm/aaaa` (próximo reajuste), com máscara e conversão automática BR ⇄ ISO ao salvar/carregar.
- Relatórios financeiros: tela unificada “Despesas e Receitas” com assistente em modal (tipo, conta, período, preview) e exportação em CSV/XLSX/PDF; limite de 25 itens no preview para manter desempenho e PDF restrito a intervalos de até 31 dias.
- Exportação do extrato detalhado aceita CSV/XLSX/PDF; XLSX gerado via PhpSpreadsheet e PDFs continuam com DomPDF. Para intervalos longos, prefira XLSX/CSV.
- Lançamentos parcelados passaram a gerar automaticamente clones por parcela (um por mês/valor) com sincronização bidirecional de status e notas “Parcela X/Y”; lançamentos consolidados mantêm `origin=parcelado` e não movimentam saldo diretamente.
- Navegação financeira agora lista apenas lançamentos operacionais (sem duplicar clones + pai) usando o escopo `JournalEntry::operational()`.

## Setup rápido

```bash
# Backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link

# Frontend
npm install
npm run dev            # compila assets em modo de desenvolvimento

# Ziggy (rotas para o front, se mudar rotas)
php artisan ziggy:generate resources/js/ziggy.js
```

Scripts úteis:

| Comando             | Descrição                                        |
| ------------------- | ------------------------------------------------ |
| `composer lint`     | Executa Pint (somente arquivos modificados)      |
| `composer stan`     | Executa Larastan (PHPStan)                       |
| `composer test`     | Roda `php artisan test --parallel`               |
| `composer ci`       | Alias para lint + stan + tests                   |
| `npm run typecheck` | Type‑check do front (`vue-tsc`)                  |
| `npm run lint`      | ESLint em JS/TS/Vue                              |
| `npm run format`    | Verifica formatação via Prettier                 |
| `npm run build`     | Build de produção (Vite)                         |
| `npm run check`     | Type‑check + ESLint + testes + build + Prettier  |

## Como rodar em outro computador

1) Instale dependências do sistema (PHP 8.2+, Node, MySQL). No Linux, inclua `php8.x-gd` (usado por avatar e planilhas).  
2) Clone o repositório e execute o “Setup rápido”.  
3) Configure o `.env` (DB, `APP_URL`, e e‑mail `MAIL_*` se for testar envio).  
4) Rode `php artisan migrate --seed`.  
5) Inicie `npm run dev` e o PHP (`php artisan serve`) ou seu servidor web.  

Se aparecer a tela em branco após mudar rotas, gere o Ziggy novamente: `php artisan ziggy:generate resources/js/ziggy.js`.

## E-mail e recuperação de senha

- Configure o `.env` com `MAIL_MAILER/MAIL_HOST/MAIL_PORT/MAIL_USERNAME/MAIL_PASSWORD/MAIL_ENCRYPTION/MAIL_FROM_*`.  
- Para validar localmente, use `MAIL_MAILER=log` e veja `storage/logs/laravel.log`.  
- Se usar filas para e-mail, mantenha `php artisan queue:work` rodando.

## Filas e geração de recibos

O job de geração de recibos utiliza a fila `receipts`. Em ambientes sem worker, o
driver `sync` garante processamento imediato, mas recomenda-se configurar uma
fila real (por exemplo `database` ou `redis`):

```bash
php artisan queue:table
php artisan migrate
php artisan queue:work --queue=receipts,default
```

Verifique também o `.env` (`QUEUE_CONNECTION`) conforme o driver escolhido.

## Solução de problemas

- “GD PHP extension must be installed…”: habilite/instale o `gd` (ex.: `sudo apt-get install php8.2-gd`) e reinicie o PHP.  
- Dropdown do usuário não abre/fecha: recompile assets (`npm run dev`), force refresh (Ctrl+Shift+R).  
- Login branco após alterar rotas: gere o Ziggy novamente (comando acima).

## Módulos principais

### Financeiro

- Conciliação/cancelamento de lançamentos com logs de auditoria.
- Nova arquitetura de lançamentos (`journal_entries`) com parcelas, clonagem, quitação via API e ações rápidas na lista.
- Parcelamento gera automaticamente lançamentos “filhos” (um por parcela) com notas “Parcela X/Y”, mantendo o lançamento original apenas como controle; status e pagamentos são sincronizados em ambos.
- Exportação CSV e dashboard Inertia com filtros completos.
- Formulário Inertia suporta seleção de categoria, pessoa, imóvel, além de geração/edição de múltiplas parcelas.
- Endpoints antigos (`financial_transactions`) foram desativados; utilize os serviços novos e os comandos de migração apenas para leitura legado.
- Documentação: `docs/FINANCEIRO.md`.

**Scripts de migração (opcionais):**

```bash
# Converter dados antigos para a nova arquitetura
php artisan finance:migrate-transactions --dry-run         # journal_entries a partir de financial_transactions
php artisan finance:migrate-payment-schedules --dry-run    # parcelas geradas a partir dos agendamentos
php artisan finance:migrate-account-balances --dry-run     # recalcula saldos das contas

# Remova --dry-run após validar relatórios/ambiente
```

### Auditoria

- Loga operações críticas (`audit_logs`) com diff antes/depois.
- Busca por texto, filtros avançados e exportação CSV/JSON.
- Tela Inertia com visualização expandida dos eventos.  
- Documentação: `docs/AUDITORIA.md`.

### Relatórios

- Visões consolidadas: financeiro (totais/inadimplência), operacional (ocupação/contratos), pessoas (totais por tipo/papel).
- Exportações CSV quando `reports.export` estiver habilitado.  
- Documentação: `docs/RELATORIOS.md`.

## Auditoria & Segurança

- O observer `App\Observers\AuditableObserver` acompanha `User`, `Pessoa`, `Imovel`, `Contrato`, `Fatura`, `FinancialAccount`, `FinancialTransaction`, `PaymentSchedule` e `CostCenter`.
- Use o seeder para popular papéis/permissões iniciais (`php artisan db:seed`).
- Revise periodicamente `audit_logs` e os filtros na tela Auditoria.

## Testes

- Testes de API cobrem CRUD de contratos, faturas, pessoas, imóveis e transações financeiras.  
- Relatórios possuem testes para filtros e exportações (financeiro, operacional, pessoas).  
- Auditoria garante que exportações e hooks de criação/edição de lançamentos registram logs.

Execute:

```bash
composer test      # PHP (paralelo)
npm run check      # Front (tsc + eslint + vitest + build + prettier)
```

## CI (GitHub Actions)

Pipeline padrão em `.github/workflows/ci.yml` executa: install + typecheck + lint + build + testes (front e back).

## Estrutura simplificada

```
app/               # Controllers, Models, Policies, Observers, Services
resources/js/      # SPA (Layouts, Pages, Stores, Components)
resources/css/     # Tailwind entrypoint
routes/            # web.php (Inertia) e api.php
docs/              # Documentação por módulo
tests/             # Suites Laravel (API, Auditoria, Relatórios, etc.)
.github/workflows// # Pipeline CI
```

## Checklist de QA

Consulte `docs/QA_CHECKLIST.md` para testes de usabilidade em ambiente de staging.
