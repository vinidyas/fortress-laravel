# Fortress Gestão Imobiliária — Code Review Snapshot

Este documento resume pontos de atenção identificados durante uma revisão estática do repositório `vinidyas/fortress-laravel`.

## Bugs / Riscos
- `PaymentScheduleController::update` atualiza registros sem validar autorização, abrindo espaço para alterações indevidas caso a `FormRequest` não seja aplicada na rota.
- Exports de auditoria e lançamentos financeiros carregam tudo em memória (`get()`), o que pode falhar em bases maiores.

## Melhorias sugeridas
- Centralizar normalização de números decimais usada em múltiplas FormRequests (faturas, financeiro) para evitar divergências.
- Adicionar ação ao botão "Novo agendamento" na tela de agendamentos e paginação visível.

## Funcionalidades futuras
- Painel de alerta de agendamentos vencendo, com envio opcional de notificações e integração com o módulo financeiro para geração automática de lançamentos.

Consulte a resposta ao usuário para detalhes completos.
