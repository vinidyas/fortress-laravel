# Modulo de Contratos - Campos

> TODO: validar manualmente contra o PDF docs/cadastro contrato.pdf; esta lista reflete a implementacao atual.

| Campo | Tipo base | Regras de validacao principais | Observacoes / Condicionais |
| --- | --- | --- | --- |
| codigo_contrato | string(30) | required, unique | Identificador humano do contrato |
| imovel_id | fk imoveis.id | required, exists | Exclusividade de contrato ativo por imovel garantida no controller |
| locador_id | fk pessoas.id | required, exists | |
| locatario_id | fk pessoas.id | required, exists | |
| fiadores[] | array<int> | optional, distinct, exists | Persistido em pivot contrato_fiadores |
| data_inicio | date | required | Base para calculo de reajuste |
| data_fim | date | nullable, >= data_inicio | Obrigatorio manter coerencia temporal |
| dia_vencimento | tinyint | required, between 1-28 | Reutilizado por faturas |
| prazo_meses | smallint | nullable, 0-600 | Informacao adicional de vigencia |
| carencia_meses | smallint | nullable, 0-120 | |
| data_entrega_chaves | date | nullable | |
| valor_aluguel | decimal(12,2) | required, min 0 | Monetario principal |
| reajuste_indice | enum | required, in {IGPM, IGPDI, IPCA, IPCA15, INPC, TR, SELIC, OUTRO, SEM_REAJUSTE} | Define lógica de reajuste |
| reajuste_indice_outro | string(60) | required se indice = OUTRO | Nome do índice personalizado |
| reajuste_periodicidade_meses | tinyint | nullable, 1-120 | Obrigatório quando indice != SEM_REAJUSTE |
| reajuste_teto_percentual | decimal(5,2) | nullable, min 0 | Limita o reajuste anual |
| data_proximo_reajuste | date | nullable | Calculada automaticamente se nao enviada |
| garantia_tipo | enum | required, in {Fiador, Seguro, Caucao, SemGarantia} | |
| caucao_valor | decimal(12,2) | required_if garantia_tipo=Caucao | Zera automaticamente quando tipo difere |
| multa_atraso_percentual | decimal(5,2) | nullable, min 0, max 100 | |
| juros_mora_percentual_mes | decimal(5,2) | nullable, min 0, max 100 | |
| multa_rescisao_alugueis | decimal(5,2) | required, min 0 | Representa quantidade de aluguéis para multa |
| repasse_automatico | boolean | default false | Checkbox no front |
| conta_cobranca_id | fk financial_accounts.id | nullable, exists | Opcional para integracao financeira |
| forma_pagamento_preferida | enum | nullable, in {Boleto, Pix, Deposito, Transferencia, CartaoCredito, Dinheiro} | |
| tipo_contrato | enum | nullable, in {Residencial, Comercial, Temporada, Outros} | |
| status | enum | optional, in {Ativo, EmAnalise, Suspenso, Encerrado, Rescindido} | Default Ativo |
| observacoes | text | nullable | Campo livre |
| anexos[] | files | nullable, mimes pdf/jpg/jpeg/png, max 5MB cada | Salvos em storage/app/contratos/{id} + tabela contrato_anexos |
| anexos_remover[] | array<int> | somente update | Remove anexos existentes marcados |

Campos derivados na resposta API incluem imovel, locador, locatario, iadores, conta_cobranca e nexos com URLs publicas via Storage.
