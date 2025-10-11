# Fortress Gestão Imobiliária

AplicaÃ§Ã£o Laravel 11 + Vue 3 para administraÃ§Ã£o de imÃ³veis, contratos,
faturamento e auditoria. O front utiliza Inertia/Vite, Pinia para estado e
Tailwind para estilização.

## Requisitos

- PHP 8.2+
- Composer
- Node.js 20 (com npm)
- MySQL 8 (produÃ§Ã£o) â€” SQLite in-memory Ã© utilizado nos testes
- Redis opcional para sessÃµes/filas

## Setup Rápido

```bash
# Backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# Frontend
npm install
npm run dev         # compila assets no modo desenvolvimento
```

Scripts Ãºteis:

| Comando             | DescriÃ§Ã£o                                       |
| ------------------- | ----------------------------------------------- |
| `composer lint`     | Executa Pint (somente arquivos modificados)     |
| `composer stan`     | Executa Larastan (PHPStan)                      |
| `composer test`     | Roda `php artisan test --parallel`              |
| `composer ci`       | Alias para lint + stan + tests                  |
| `npm run typecheck` | Type-check do front (`vue-tsc`)                 |
| `npm run lint`      | ESLint em JS/TS/Vue                             |
| `npm run format`    | Verifica formataÃ§Ã£o via Prettier                |
| `npm run build`     | Build de produÃ§Ã£o (Vite)                        |
| `npm run check`     | Type-check + ESLint + testes + build + Prettier |

## MÃ³dulos Principais

### Financeiro

- Gestão Imobiliária
- ConciliaÃ§Ã£o/cancelamento de lanÃ§amentos com logs automÃ¡ticos de auditoria.
- ExportaÃ§Ã£o CSV e dashboard Inertia com filtros completos.
- DocumentaÃ§Ã£o: `docs/FINANCEIRO.md`

### Auditoria

- Loga todas as operaÃ§Ãµes crÃ­ticas (`audit_logs`) com diff antes/depois.
- Busca por texto, filtros avanÃ§ados e exportaÃ§Ã£o CSV/JSON.
- Tela Inertia com visualizaÃ§Ã£o expandida dos eventos.
- DocumentaÃ§Ã£o: `docs/AUDITORIA.md`

### RelatÃ³rios

- VisÃµes consolidadas: financeiro (totais/inadimplÃªncia), operacional
  (ocupaÃ§Ã£o/contratos), pessoas (totais por tipo/papel).
- ExportaÃ§Ãµes CSV quando `reports.export` estiver habilitado.
- DocumentaÃ§Ã£o: `docs/RELATORIOS.md`

## Auditoria & SeguranÃ§a

- O observer `App\\Observers\\AuditableObserver` acompanha `User`, `Pessoa`,
  `Imovel`, `Contrato`, `Fatura`, `FinancialAccount`, `FinancialTransaction`,
  `PaymentSchedule` e `CostCenter`.
- Utilize o seeder padrÃ£o para popular papÃ©is/permissÃµes iniciais
  (`php artisan db:seed`).
- Revise periodicamente `audit_logs` e utilize os filtros disponÃ­veis na tela
  Auditoria.

## Importadores de Legado

| Comando                                  | DescriÃ§Ã£o                                             |
| ---------------------------------------- | ----------------------------------------------------- |
| `php artisan legacy:import --financeiro` | Importa contas, centros, lanÃ§amentos e agendamentos   |
| `php artisan legacy:import --auditoria`  | Importa trilhas histÃ³ricas do sistema anterior        |
| Adicione `--dry-run`                     | Apenas valida a conexÃ£o e exibe estatÃ­sticas          |
| Adicione `--truncate`                    | Limpa tabelas de destino antes de importar (cuidado!) |

## Testes

- Testes de API cobrem listagem/CRUD de contratos, faturas, pessoas, imÃ³veis e
  transaÃ§Ãµes financeiras.
- RelatÃ³rios possuem testes para filtros e exportaÃ§Ãµes (financeiro, operacional,
  pessoas).
- Auditoria garante que exportaÃ§Ãµes e hooks de criaÃ§Ã£o/ediÃ§Ã£o de lanÃ§amentos
  registram logs.

Execute tudo com:

```bash
composer test      # PHP (paralelo)
npm run check      # Front (tsc + eslint + vitest + build + prettier)
```

## CI (GitHub Actions)

Workflow padrÃ£o em `.github/workflows/ci.yml` executa:

1. Composer install + `php artisan key:generate`
2. `npm install`
3. `npm run typecheck` + `npm run lint` + `npm run format` + `npm run test:unit`
4. `npm run build`
5. `composer lint` + `composer stan` + `composer test`

## Estrutura Simplificada

```
app/               # Controllers, Models, Policies, Observers, Services
resources/js/      # SPA (Layouts, Pages, Stores, Components)
resources/css/     # Tailwind entrypoint
routes/            # web.php (Inertia) e api.php
docs/              # DocumentaÃ§Ã£o por mÃ³dulo
tests/             # Suites Laravel (API, Auditoria, RelatÃ³rios, etc.)
.github/workflows/ # Pipeline CI
```

## Ambiente de Desenvolvimento

- Para Sanctum/Inertia, certifique-se de apontar `APP_URL` e `FRONTEND_URL`
  corretamente.
- Os testes utilizam SQLite in-memory (configurado em `phpunit.xml`).
- Para builds locais de produÃ§Ã£o: `npm run build` gera assets em `public/build`.

DÃºvidas ou suGestão Imobiliária

## ValidaÃ§Ã£o em Staging

Confira o checklist em `docs/QA_CHECKLIST.md` para conduzir testes de
usabilidade dos mÃ³dulos Financeiro, Auditoria e RelatÃ³rios em um ambiente de
staging.

