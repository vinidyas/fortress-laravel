# Checklist de Validação em Staging

Use os cenários abaixo para validar a experiência de uso dos módulos Financeiro, Auditoria e Relatórios antes de liberar uma versão.

## Preparação do Ambiente

1. Execute `php artisan migrate:fresh --seed` para garantir migrações atualizadas.
2. Opcional: importe dados do legado com `php artisan legacy:import --financeiro --auditoria --dry-run` seguido de import completo.
3. Configure usuários de teste com perfis distintos (Financeiro, Auditor, Relatórios, Admin).
4. Gere assets de produção (`npm run build`) se estiver auditando uma release.

## Perfis a Exercitar

| Perfil | Permissões principais | Objetivo |
| --- | --- | --- |
| Financeiro | `financeiro.*`, `faturas.*`, `contratos.*` | Fluxos de lançamentos, conciliação e export |
| Auditor | `auditoria.*`, `financeiro.view` | Revisão de trilhas e filtros avançados |
| Relatórios | `reports.view.*`, `reports.export` | Dashboards, agregações e exportações |
| Admin | Todas | Cross-check completo |

## Financeiro

- **Menu**: confirme que o item "Financeiro" mostra os submenus Contas, Centros de Custo, Lançamentos e Agendamentos.
- **Listagem de Lançamentos**: teste filtros por conta, centro, status, tipo, período e busca textual.
- **Conciliação/Cancelamento**: crie um lançamento, concilie com observação e em seguida cancele outro para validar mensagens.
- **Vínculos**: relacione lançamentos a contratos/faturas e verifique exibição no formulário.
- **Exportação**: gere CSV aplicando filtros e valide cabeçalhos/valores.

## Auditoria

- **Filtros**: combine busca por `payload`, faixa de data, usuário e recurso.
- **Diff**: abra eventos `financial_transaction.updated` e confira painel before/after.
- **Export**: teste CSV e JSON, garantindo que downloads reflitam filtros.
- **Permissões**: entre com usuário sem `auditoria.view` e confirme ausência do menu/rota.

## Relatórios

- **Financeiro**: valide totais, inadimplência e filtro por conta/período; gere CSV.
- **Operacional**: confira cálculo de ocupação e lista de contratos vencendo (`ate`); exporte CSV.
- **Pessoas**: filtre por papel/tipo e valide contagem + “amostra”; exporte CSV.

## Acessibilidade & UX

- Teste navegação mobile (menu sandwich) e responsividade das tabelas.
- Verifique estados vazios (sem registros/encontrados).
- Observe tempo de carregamento com devtools (ideal < 200 ms em filtros comuns).

## Pós-Validação

- Registre feedbacks/bugs encontrados em issues do repositório.
- Anexe exportações e screenshots relevantes ao relatório de QA.
- Execute `composer ci` antes de aprovar o merge final.
