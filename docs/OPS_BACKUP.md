# Procedimento de Backup do Projeto Fortress

## Visão Geral

O ambiente utiliza uma VPS (Hostinger) rodando o projeto Laravel dentro de containers Docker. O backup foi estruturado para proteger:

- Banco de dados MySQL (`fortress`).
- Arquivos persistidos do Laravel (`storage/` e `public/storage` – volume `fortress_storage`).
- Arquivo `.env` com variáveis sensíveis.

Os artefatos são mantidos localmente para restaurações rápidas e enviados diariamente ao OneDrive para cópia externa. Todo o fluxo é executado pelo script `/opt/fortress-backups/run_backup.sh`, agendado via cron às 02:00.

## Caminhos e Usuários

| Componente                     | Caminho / Recurso                                                  | Observações                                |
|-------------------------------|--------------------------------------------------------------------|--------------------------------------------|
| Diretório raiz de backups     | `/opt/fortress-backups`                                            | Pertence ao usuário de sistema `backup`.   |
| Configuração do rclone        | `/opt/fortress-backups/.config/rclone/rclone.conf`                 | Remote `fortress-sistema-onedrive`.        |
| Dump SQL local                | `/opt/fortress-backups/sql/fortress-YYYYMMDD-HHMM.sql.gz`          | Retidos por 7 dias.                        |
| Tar do volume storage         | `/opt/fortress-backups/storage/storage-YYYYMMDD-HHMM.tar.gz`       | Retidos por 7 dias.                        |
| Cópia do `.env`               | `/opt/fortress-backups/env/.env-YYYYMMDD-HHMM`                     | Permissões `640` (root:backup).            |
| Pastas no OneDrive            | `fortress-backups/sql`, `fortress-backups/storage`, `fortress-backups/env` | Retenção: 30 dias (rclone delete). |
| Log das execuções             | `/opt/fortress-backups/backup.log`                                 | Recebe stdout/stderr do cron.              |

## Script de Backup

Local: `/opt/fortress-backups/run_backup.sh`

Funções executadas:

1. Define variáveis (datas, retenção, caminhos). `RCLONE_CONFIG` aponta para o arquivo criado pelo usuário `backup`.
2. Gera dump do MySQL dentro do container `fortress-db` usando `mysqldump --no-tablespaces` e compacta com gzip.
3. Compacta o volume Docker `fortress_storage` via container `alpine` ad-hoc.
4. Copia o `.env` do projeto (`/root/docker/fortress/fortress-laravel/.env`) com restrição de permissões.
5. Remove arquivos locais com mais de 7 dias.
6. Usa `rclone copy` (executado como `backup`) para enviar cada arquivo ao OneDrive.
7. Executa rotação remota: `rclone delete --min-age 30d` em cada pasta.

### Cron

Entrada em `crontab -l` (usuário root):

```
0 2 * * * /opt/fortress-backups/run_backup.sh >> /opt/fortress-backups/backup.log 2>&1
```

## Periodicidade

- **Diária** às 02:00 (horário do servidor).  
- Retenções:
  - Local (VPS): 7 dias de histórico.
  - OneDrive: 30 dias de histórico (configurável via variáveis `RETENTION_LOCAL_DAYS` e `RETENTION_REMOTE_DAYS` no script).

## Processo de Restauração

> Sempre teste em ambiente isolado antes de restaurar em produção.

### Script automatizado (`scripts/restore_from_backup.sh`)

1. Baixe do OneDrive (ou mova da VPS) os arquivos `fortress-*.sql.gz`, `storage-*.tar.gz` e `.env-*`.
2. Coloque-os no diretório `/opt/fortress-backups/manual-restore` (crie se necessário).
3. Execute:
   ```bash
   cd /root/docker/fortress/fortress-laravel
   sudo scripts/restore_from_backup.sh [/opt/fortress-backups/manual-restore]
   ```
4. O script gera snapshots de segurança, restaura banco, volume `fortress_storage`, `.env` e reinicia os containers. Ao fim, revise o sistema e descarte os arquivos temporários caso estejam ok.

### Passos manuais (referência)

#### Banco de Dados

1. Baixe o arquivo mais recente do OneDrive (`fortress-backups/sql/fortress-YYYYMMDD-HHMM.sql.gz`) ou copie da VPS.
2. No servidor:
   ```bash
   gunzip fortress-YYYYMMDD-HHMM.sql.gz   # gera .sql
   docker compose exec -T fortress-db sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < fortress-YYYYMMDD-HHMM.sql
   ```
   Certifique-se de que não há conexões ativas ou utilize `db:wipe`/`migrate` antes da restauração se precisar de base limpa.

#### Arquivos do Storage

1. Obtenha `storage-YYYYMMDD-HHMM.tar.gz` (OneDrive ou VPS).
2. Dentro do projeto:
   ```bash
   docker compose down
   tar xzf storage-YYYYMMDD-HHMM.tar.gz -C /tmp/storage-restore
   docker run --rm -v fortress_storage:/data -v /tmp/storage-restore:/restore alpine sh -c 'rm -rf /data/* && cp -a /restore/. /data/'
   docker compose up -d
   ```
   Isso substitui completamente o volume `fortress_storage` pela cópia restaurada.

#### Variáveis de Ambiente

1. Baixe `.env-YYYYMMDD-HHMM`.
2. Substitua `/root/docker/fortress/fortress-laravel/.env` (faça backup atual antes).
3. Reinicie containers para aplicar: `docker compose down && docker compose up -d`.

## Testes e Monitoramento

- Verifique diariamente `tail -n 50 /opt/fortress-backups/backup.log` para detectar falhas.
- A cada trimestre realize exercício de restauração: use os artefatos mais recentes para validar banco, storage e .env em ambiente separado.
- Caso o comando rclone falhe (mudança de credenciais no OneDrive), refaça `rclone config` com o usuário `backup` e atualize o token.

## Ajustes e Customização

- **Retenção**: edite `RETENTION_LOCAL_DAYS` ou `RETENTION_REMOTE_DAYS` no script.
- **Horário do cron**: ajuste a linha no `crontab`.
- **Pastas extras**: basta adicionar novos blocos ao script (ex.: `logs/`).
- **Criptografia opcional**: incluir etapa `gpg` antes do upload para o OneDrive, guardando chaves com segurança.

---

Com essa rotina, o ambiente mantém cópias recentes na VPS e no OneDrive, permitindo recuperar rapidamente tanto dados quanto configurações em caso de falhas. Documente alterações futuras nesta página para referência operacional.
