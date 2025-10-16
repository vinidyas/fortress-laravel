# Modulo de Contratos - Arquitetura Geral

## Backend

- Rotas REST expostas em outes/api.php via Route::apiResource('contratos', ContratoController::class).
- pp/Http/Controllers/Api/ContratoController.php centraliza CRUD, filtros (Spatie QueryBuilder) e regras de negocio como exclusividade de contrato ativo por imovel e calculo automatico de data_proximo_reajuste.
- pp/Http/Requests/Contrato/ContratoStoreRequest.php e ContratoUpdateRequest.php concentram validacao de campos, normalizacao de decimais e condicoes (ex.: equired_if para caucao).
- pp/Http/Resources/ContratoResource.php padroniza resposta Inertia/API com campos primitivos, enums normalizados e relacionamentos (imovel, locador, locatario, iadores, contaCobranca, nexos).
- pp/Models/Contrato.php define fillable/casts, enums PHP 8.1, relacionamento elongsToMany com fiadores e hasMany de anexos.
- Politicas: pp/Policies/ContratoPolicy.php limita acesso por permissao (contratos.*).
- Validacoes adicionais ficam encapsuladas em metodos privados do controller (ensureUniqueActiveContrato, extractContratoData, storeAnexos).

## Persistencia

- Tabela principal contratos (migracoes 2025_10_08_101542_create_contratos_table.php + 2025_10_12_213403_update_contratos_table_add_extended_fields.php).
- Pivot N:N contrato_fiadores substitui coluna antiga iador_id e garante unique por par.
- Relacao de anexos em contrato_anexos com path, original_name, mime_type e uploaded_by.
- Indices cobrindo status, data_inicio e (imovel_id, status).
- FKs adicionais conectam-se a imoveis, pessoas e inancial_accounts.

## Frontend (Inertia + Vue 3)

- Listagem e filtros em esources/js/Pages/Contratos/Index.vue com paginacao, filtros dinamicos e acionamento do modal de criacao.
- Edicao direta em esources/js/Pages/Contratos/Edit.vue reaproveitando ContratoForm.vue.
- Formulario unificado esources/js/Components/Contratos/ContratoForm.vue cobre toda a especificacao funcional, suporta multiplos fiadores, anexos (upload/remocao) e condicionais (caucao, sem reajuste, etc.).
- Modal de criacao ContratoFormModal.vue apenas injeta o formulario em modo create.
- Inputs monetarios deveriam usar MoneyInput.vue; campos de data usam input nativo. *TODO revisar PDF para confirmar mascaras/formatos exigidos.*

## Testes

- 	ests/Feature/Api/ContratosTest.php cobre cenarios chave: criacao completa (fiadores + anexos), unicidade de codigo, bloqueio de contrato ativo duplicado, caucao obrigatorio e update com conflitos de imovel.
- Factories: database/factories/ContratoFactory.php produz dados consistentes com enums/casts.

## Integracoes e comandos

- Geracao de faturas depende de dia_vencimento, alor_aluguel e campos de reajuste (mantidos).
- Comando invoices:generate permanece funcional considerando contratos ativos e data_proximo_reajuste calculada.

## TODO

- Revisar manualmente o PDF docs/cadastro contrato.pdf para confirmar opcoes de selects/rotulos nao extraidas programaticamente. Ajustar labels no front (ex.: garantir uso concreto de MoneyInput.vue) caso o documento traga requisitos diferentes.
