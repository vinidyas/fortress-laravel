# Modulo de Contratos - De/Para

## Resumo

- Fluxo de criacao/edicao agora utiliza ContratoForm.vue (Inertia) com todas as secoes do PDF.
- API valida e retorna novos campos financeiros, de vigencia e de classificacao adicionados na migracao 2025_10_12.
- Fiador unico (iador_id) foi migrado para relacionamento N:N via contrato_fiadores.
- Suporte a anexos adicionado em contrato_anexos com upload/remocao no formulario.

## Banco de dados

| Antes | Depois | Observacoes |
| --- | --- | --- |
| Coluna iador_id (nullable) em contratos | Tabela contrato_fiadores (contrato_id, pessoa_id) | Permite multiplos fiadores e migra dados existentes durante up() |
| Ausencia de colunas prazo_meses, carencia_meses, data_entrega_chaves, desconto_mensal, multa_atraso_percentual, juros_mora_percentual_mes, epasse_automatico, conta_cobranca_id, orma_pagamento_preferida, 	ipo_contrato | Colunas adicionadas com casts e defaults adequados | Regras de validacao atualizadas nas FormRequests |
| Enum status com {Ativo, Suspenso, Encerrado} | Enum expandido {Ativo, Suspenso, Encerrado, Rescindido, EmAnalise} | Ajuste via DB::statement na migracao |
| Enum garantia_tipo {Fiador, Seguro, Caucao, SemGarantia} | Mesmo conjunto, mas logica condicional movida para FormRequest + controller | caucao_valor limpo quando nao aplicavel |
| Nenhuma tabela de anexos | contrato_anexos armazena metadados e path no Storage | Remocao fisica durante delete/update |

## Backend

| Antes | Depois |
| --- | --- |
| Controller com criacao basica e sem regras agregadas | ContratoController aplica transacoes, verifica contrato ativo por imovel, calcula data_proximo_reajuste e sincroniza fiadores/anexos |
| Requests com poucos campos | ContratoStoreRequest/ContratoUpdateRequest cobrem todos os campos novos, normalizam decimais e condicionais | 
| Resource retornava somente campos primarios | ContratoResource expande enums, relacionamentos e anexos |

## Frontend

| Antes | Depois |
| --- | --- |
| Formulario simplificado com subset de campos | ContratoForm.vue segmentado em Identificacao, Partes, Vigencia, Financeiro, Garantias, Anexos e Observacoes |
| Suporte apenas a um fiador | Interface permite adicionar/remover varios fiadores (via array de IDs) |
| Sem upload de documentos | Upload multiplo com marcacao para remocao em edicao |

## TODO PDF

- Realizar revisao humana do PDF docs/cadastro contrato.pdf para validar nomenclaturas de campos, opcoes de selects e ordem das secoes. Ajustes finos podem ser necessarios caso o layout oficial diferencie o que foi inferido pela especificacao textual.
