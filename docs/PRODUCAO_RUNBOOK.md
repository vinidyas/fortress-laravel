# Runbook de Deploy em Produção – Fortress

## 1. Visão geral

Este guia consolida o passo a passo para preparar um novo ambiente de produção do Fortress, publicar atualizações e validar a operação contínua (portal do locatário + back-office). Todas as etapas consideram a stack Docker fornecida no repositório.

## 2. Pré-requisitos do servidor

- Ubuntu 22.04 LTS (ou distro compatível) com acesso root/SSH.
- Docker Engine ≥ 24 e Docker Compose Plugin ≥ 2.20 instalados.
- DNS apontando os domínios:
  - `sistema.fortressempreendimentos.com.br` → IP do servidor.
  - `portal.fortressempreendimentos.com.br` → IP do servidor.
- Certificados (Let’s Encrypt via Traefik já suportado na pasta `docker/config/traefik`).
- Acesso às credenciais definitivas (Bradesco, SMTP, etc.).

## 3. Checklist de variáveis sensíveis

Edite o `.env` tomando como base `.env.example`. Revise principalmente:

| Categoria | Chaves |
|-----------|--------|
| App | `APP_ENV=production`, `APP_URL=https://sistema...`, `PORTAL_URL=https://portal...`, `PORTAL_DOMAIN=portal.fortressempreendimentos.com.br`, `SESSION_DOMAIN=.fortressempreendimentos.com.br`, `SANCTUM_STATEFUL_DOMAINS=sistema...,portal...` |
| Segurança | `APP_KEY` (gerar com `php artisan key:generate`), `APP_DEBUG=false`, `LOG_CHANNEL=stack`, `LOG_LEVEL=info` |
| Banco | `FORTRESS_DB_*` (usuário, senha, nome do schema, root) |
| Redis | `REDIS_HOST=fortress-redis`, `REDIS_PASSWORD` (opcional) |
| Filas/Mail | SMTP real (`MAIL_MAILER`, `MAIL_HOST`, etc.), `QUEUE_CONNECTION=redis` |
| Bradesco | `BRADESCO_BASE_URL`, `BRADESCO_CLIENT_ID`, `BRADESCO_CLIENT_SECRET`, certificados/códigos de produção |
| Portal | `PORTAL_INVITE_EXPIRATION_DAYS`, `PORTAL_SUPPORT_EMAIL`, etc. |
| Seeds | `SEED_SAMPLE_DATA=false`, opcional `SEED_ADMIN_PASSWORD=` para definir a senha inicial do admin público. |

> **Dica:** mantenha o `.env` fora do versionamento e armazene as credenciais sigilosas em cofre/gerenciador seguro.

## 4. Preparação inicial

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y ca-certificates curl gnupg lsb-release
# Instale Docker e Compose seguindo https://docs.docker.com/engine/install/ubuntu/

mkdir -p /opt/fortress && cd /opt/fortress
git clone https://github.com/<seu-usuario>/fortress-laravel.git .
cp .env.example .env
# Edite .env conforme a seção anterior
```

Gere a APP_KEY (uma vez):

```bash
docker compose run --rm fortress-app php artisan key:generate --ansi
```

## 5. Build das imagens

O Dockerfile já compila as dependências PHP e o bundle front-end. Execute:

```bash
docker compose build fortress-app fortress-web
```

## 6. Subida inicial dos serviços

1. Inicie os serviços de base para preparar dados:
   ```bash
   docker compose up -d fortress-db fortress-redis
   ```
   Aguarde o MySQL completar a inicialização (`docker compose logs -f fortress-db`).

2. Levante os demais contêineres:
   ```bash
   docker compose up -d
   ```

3. Garanta que Traefik já esteja configurado para despachar os hosts, atualizando `/docker/config/traefik/dynamic_config.yml` com os domínios do portal e do sistema. Reinicie o stack do Traefik externo quando alterar essa configuração.

## 7. Migrações e seeds

Execute sempre dentro do contêiner `fortress-app`:

```bash
docker compose exec -T fortress-app php artisan migrate --force
docker compose exec -T fortress-app php artisan db:seed --class=DatabaseSeeder
```

- Por padrão `SEED_SAMPLE_DATA=false` garante apenas a criação do usuário `admin` com a senha definida em `SEED_ADMIN_PASSWORD` (ou `admin/admin123` caso a variável não esteja setada).
- Nunca habilite `SEED_SAMPLE_DATA=true` em produção.

## 8. Serviços assíncronos

Os contêineres já cobrem:

| Contêiner | Responsabilidade |
|-----------|------------------|
| `fortress_queue` | `php artisan queue:work` nas filas `boletos, receipts, default`. |
| `fortress_scheduler` | Executa `schedule:run` a cada minuto. |
| `fortress_web` | Nginx servindo a aplicação (estático + PHP-FPM). |

Verifique status e logs:

```bash
docker compose ps
docker compose logs -f fortress_queue
docker compose logs -f fortress_scheduler
```

> **Importante:** o worker `fortress_queue` precisa permanecer ativo para tratar a fila `boletos` (emissão, webhooks e polling do Bradesco). Após cada deploy, valide se o contêiner está “Up” e reinicie com `docker compose restart fortress_queue` caso necessário.

## 9. Validação funcional

1. Acesse `https://sistema.fortressempreendimentos.com.br` e realize login com o usuário `admin`.
2. Cadastre um locatário e emita uma fatura de teste (sem boleto, já que está desativado por padrão).
3. Para o portal, convide o locatário e valide o fluxo em `https://portal.fortressempreendimentos.com.br`.
4. Confira os logs (`storage/logs/laravel.log`) via:
   ```bash
   docker compose exec -T fortress-app tail -f storage/logs/laravel.log
   ```

## 10. Backups

Já existe um script operacional documentado em `docs/OPS_BACKUP.md`. Recomenda-se:

1. Configurar o script `run_backup.sh` via cron na VPS (ex.: diário às 02h).
2. Garantir integração com OneDrive/SharePoint para off-site.
3. Testar restauração periodicamente com `scripts/restore_from_backup.sh`.

## 11. Publicar atualizações futuras

1. Acesse o servidor e entre na pasta do projeto.
2. `git fetch --all && git checkout <tag-ou-branch>` (congele a versão).
3. Rebuild se houver mudanças em dependências/front:
   ```bash
   docker compose build fortress-app fortress-web
   ```
4. Atualize os contêineres:
   ```bash
   docker compose up -d --remove-orphans
   ```
5. Rode migrações:
   ```bash
   docker compose exec -T fortress-app php artisan migrate --force
   ```
6. Caso haja mudanças em filas/scheduler, reinicie contêineres específicos (`docker compose restart fortress_queue fortress_scheduler`).
7. Verifique novamente os logs e o front-end.

## 12. Monitoramento e observabilidade

- **Traefik**: `/docker/config/traefik/logs` armazena access/error logs das rotas.
- **Laravel**: `storage/logs/*.log` dentro do volume `fortress_storage` (especialmente `storage/logs/bradesco-response.log` para acompanhar respostas da API do banco e `storage/logs/laravel.log`).
- **Filas**: acompanhe `queue:work` via `docker compose logs -f fortress_queue`.
- Considere integrar alertas (Healthchecks/Upptime) para monitorar:
  - Status HTTP das URLs principais.
  - Fila parada (ausência de logs por >5m).
  - Falha no scheduler.

## 13. Checklist final antes do go-live

- [ ] DNS e TLS (Let's Encrypt) ativos para ambos os domínios.
- [ ] `.env` revisado com todas as chaves de produção.
- [ ] `APP_DEBUG=false`.
- [ ] Migrações executadas (`php artisan migrate --force`).
- [ ] Usuário `admin` validado e senha alterada.
- [ ] Bradesco sandbox ou produção configurado conforme etapa atual.
- [ ] Backups agendados e testados.
- [ ] Portal do locatário acessível e convite funcionando.
- [ ] Logs sem erros graves após smoke test.

Com esses passos o ambiente fica pronto para operar em produção com segurança e previsibilidade. Qualquer ajuste adicional (como chaves Bradesco de produção) pode ser aplicado repetindo a etapa 11 com as novas variáveis de ambiente.***
