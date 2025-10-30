Este diretório contém os artefatos necessários para rodar o `fortress-laravel` na mesma VPS que já executa Traefik, Pangolin e WordPress.

## Visão geral

- **Traefik** continua como proxy reverso central. O serviço `fortress-web` recebe as requisições HTTP/HTTPS através da rede externa `pangolin`.
- **Laravel (PHP-FPM)** roda no container `fortress-app` (imagem `fortress-laravel:php`).
- **Nginx** serve os assets estáticos e encaminha PHP para `fortress-app`.
- **MySQL 8** roda no serviço `fortress-db`. Ele é independente do MySQL 5.7 usado pelo WordPress.
- **Redis** (container `fortress-redis`) é usado para cache, filas e sessões.
- **Workers**: `fortress-queue` (queue:work) e `fortress-scheduler` (schedule:run) usam a mesma imagem PHP.

## Arquivos

| Arquivo | Função |
| --- | --- |
| `docker-compose.yml` | Define serviços (php-fpm, nginx, mysql, redis, queue, scheduler). |
| `Dockerfile` | Build multi-stage (Composer, Vite e imagens finais para PHP e Nginx). |
| `nginx.conf` | Configuração do Nginx apontando para `fortress_app:9000`. |
| `php.ini` | Ajustes de PHP/opcache/limites. |
| `.env.example` | Variáveis de substituição do **Compose** (domínio e credenciais MySQL). |
| `fortress.env.example` | Variáveis de ambiente para o **Laravel** dentro dos containers. |

## Passo a passo de configuração

1. **Copie os exemplos de ambiente**
   ```bash
   cd docs/docker-vps/fortress
   cp .env.example .env
   cp fortress.env.example fortress.env
   ```
   - Edite `.env` e ajuste:
     - `FORTRESS_DOMAIN` → domínio público do Fortress (ex.: `sistema.fortressempreendimentos.com.br`).
     - `TRAEFIK_CERT_RESOLVER` → normalmente `letsencrypt` (já configurado no Traefik).
     - `FORTRESS_DB_*` → credenciais que serão criadas para o MySQL 8.
   - Edite `fortress.env` com os dados corretos (APP_URL, mail, storage S3 se necessário etc). Deixe `DB_HOST=fortress-db` e `REDIS_HOST=fortress-redis`.

2. **Build das imagens**
   ```bash
   docker compose build
   ```
   > Execute esse comando a partir de `docs/docker-vps/fortress`. Ele compila vendor (Composer) e assets (Vite) dentro da imagem.

3. **Suba a stack**
   ```bash
   docker compose up -d
   ```
   Isso cria os volumes (`fortress_storage`, `fortress_db_data`, `fortress_redis_data`) e inicializa todos os serviços.

4. **Rodar comandos iniciais do Laravel**
   ```bash
   # Gera APP_KEY
   docker compose run --rm fortress-app php artisan key:generate --force

   # Cria link storage/public
   docker compose run --rm fortress-app php artisan storage:link

   # Migrações e seed
   docker compose run --rm fortress-app php artisan migrate --force --seed

   # Opcional: gerar caches de config/rotas/views antes do primeiro deploy
   docker compose run --rm fortress-app php artisan config:cache
   docker compose run --rm fortress-app php artisan route:cache
   docker compose run --rm fortress-app php artisan view:cache
   ```

5. **Traefik**
   - As labels em `fortress-web` expõem o serviço automaticamente.
   - Certifique-se de que o Traefik enxergue a rede externa `pangolin` (já usada pelo WordPress). Nada muda para Pangolin/Gerbil.
   - O roteador HTTPS responde por `https://$FORTRESS_DOMAIN`. O HTTP faz redirect automático para HTTPS.

6. **Banco e backup**
   - O volume `fortress_db_data` guarda os dados do MySQL 8. Programe os backups (mysqldump + backup de volumes).
   - Caso prefira reutilizar outro banco (Aurora, RDS etc.), remova `fortress-db` do compose e ajuste `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` no `fortress.env`.

7. **Redis**
   - Container dedicado `fortress-redis`.
   - `fortress.env` já aponta `CACHE_STORE`, `QUEUE_CONNECTION` e `SESSION_DRIVER` para Redis usando o cliente `phpredis`.

8. **Rotina de deploy**
   1. Atualize o código e rode `docker compose build`.
   2. `docker compose up -d`.
   3. `docker compose run --rm fortress-app php artisan migrate --force` (se houver migrations novas).
   4. Limpe caches se necessário (`php artisan config:clear`, etc.).

## Observações importantes

- **Não altere** os arquivos do Pangolin. O compose atual apenas se junta à rede `pangolin` já existente; nenhuma mudança é feita nos containers existentes.
- Mantemos o WordPress isolado: usa MySQL 5.7 próprio. Fortress tem MySQL 8 dedicado.
- Workers (queue/scheduler) rodam como usuário `www-data` e compartilham o volume `fortress_storage`, garantindo acesso a `storage/logs` e uploads.
- O Traefik dashboard (`http://<host>:8080`) deve listar os novos roteadores `fortress-http` (redirect) e `fortress-https`.
- Para ambientes de staging, troque domínio/credenciais nos `.env`.
- Se precisar rodar comandos Artisan rapidamente:
  ```bash
  docker compose run --rm fortress-app php artisan tinker
  docker compose run --rm fortress-app php artisan queue:retry all
  ```

## Próximos passos sugeridos

- Configurar backups automáticos de `fortress_db_data` e `fortress_storage`.
- Adicionar monitoramento (Healthcheck no Traefik ou Uptime Kuma) para `https://$FORTRESS_DOMAIN`.
- Revisar políticas de segurança (headers adicionais, rate limit via Traefik se necessário).
