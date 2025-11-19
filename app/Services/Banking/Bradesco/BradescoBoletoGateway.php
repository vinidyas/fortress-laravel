<?php

namespace App\Services\Banking\Bradesco;

use App\Models\Fatura;
use App\Models\FaturaBoleto;
use App\Services\Boleto\BoletoGateway;
use App\Services\Boleto\BoletoFaturaSyncService;
use App\Services\Banking\Bradesco\Support\BradescoPayloadSanitizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BradescoBoletoGateway implements BoletoGateway
{
    public function __construct(
        private readonly BradescoApiClient $client,
        private readonly BoletoFaturaSyncService $faturaSyncService,
    ) {}

    public function issue(Fatura $fatura, array $contexto = []): FaturaBoleto
    {
        $payload = $this->buildIssuePayload($fatura, $contexto);

        $this->log('Emissão de boleto solicitada', [
            'fatura_id' => $fatura->id,
            'payload' => $payload,
        ]);

        $response = $this->client->issueBoleto($payload);

        $this->log('Emissão de boleto concluída', [
            'fatura_id' => $fatura->id,
            'response' => $response,
        ]);

        $externalId = $this->resolveExternalId($response);

        return DB::transaction(function () use ($fatura, $payload, $response, $externalId) {
            if ($externalId && $this->shouldApplySandboxFixtures()) {
                $this->avoidSandboxExternalIdCollision($externalId, $fatura->id);
            }

            $nossoNumero = Arr::get($response, 'nossoNumero') ?? $externalId;
            $pdfUrl = Arr::get($response, 'urlPdf');

            if (! $pdfUrl && $this->isSandbox()) {
                $pdfUrl = config('services.bradesco_boleto.sandbox_pdf_url');
            }

            $sanitizedPayload = BradescoPayloadSanitizer::sanitize($payload);
            $sanitizedResponse = BradescoPayloadSanitizer::sanitize($response);

            $boleto = $fatura->boletos()->create([
                'bank_code' => BradescoApiClient::BANK_CODE,
                'external_id' => $externalId,
                'nosso_numero' => $nossoNumero,
                'document_number' => Arr::get($response, 'numeroDocumento'),
                'linha_digitavel' => Arr::get($response, 'linhaDigitavel'),
                'codigo_barras' => Arr::get($response, 'codigoBarras'),
                'valor' => Arr::get($response, 'valor', $fatura->valor_total),
                'vencimento' => Arr::get($response, 'vencimento', $fatura->vencimento),
                'status' => $this->resolveStatusFromResponse($response, FaturaBoleto::STATUS_REGISTERED),
                'registrado_em' => now(),
                'pdf_url' => $pdfUrl,
                'payload' => $sanitizedPayload,
                'response_payload' => $sanitizedResponse,
            ]);

            return $boleto;
        });
    }

    public function refreshStatus(FaturaBoleto $boleto): FaturaBoleto
    {
        if (! $boleto->external_id) {
            Log::warning('[Bradesco] Boleto sem external_id para sincronização', [
                'fatura_boleto_id' => $boleto->id,
            ]);

            return $boleto;
        }

        $this->log('Sincronização de boleto iniciada', [
            'fatura_boleto_id' => $boleto->id,
            'external_id' => $boleto->external_id,
        ]);

        $response = $this->client->getBoleto($boleto->external_id);
        $sanitizedResponse = BradescoPayloadSanitizer::sanitize($response);

        $valorPago = (float) Arr::get(
            $response,
            'titulo.vlrPagto',
            Arr::get($response, 'valorPago', $boleto->valor_pago)
        );

        $dataPagamento = $this->parseBradescoDate(
            Arr::get($response, 'titulo.dtPagto')
            ?? Arr::get($response, 'dtPagto')
            ?? Arr::get($response, 'dataPagamento')
        );

        $boleto->fill([
            'status' => $this->resolveStatusFromResponse($response, $boleto->status),
            'linha_digitavel' => Arr::get($response, 'linhaDigitavel', $boleto->linha_digitavel),
            'codigo_barras' => Arr::get($response, 'codigoBarras', $boleto->codigo_barras),
            'pdf_url' => Arr::get($response, 'urlPdf', $boleto->pdf_url),
            'response_payload' => $sanitizedResponse,
            'valor_pago' => $valorPago ?: $boleto->valor_pago,
            'liquidado_em' => $dataPagamento ?? $boleto->liquidado_em,
            'last_synced_at' => now(),
        ]);

        if ($boleto->status === FaturaBoleto::STATUS_PAID) {
            $boleto->markAsPaid(
                $valorPago ?: ($boleto->valor ?? 0),
                $dataPagamento ?? $boleto->liquidado_em
            );
        }

        $boleto->save();

        $this->faturaSyncService->sync($boleto->fresh());

        $this->log('Sincronização de boleto concluída', [
            'fatura_boleto_id' => $boleto->id,
            'status' => $boleto->status,
            'response' => $response,
        ]);

        return $boleto;
    }

    public function fetchAndStorePdf(FaturaBoleto $boleto): ?string
    {
        if ($boleto->pdf_url || ! $boleto->external_id) {
            return $boleto->pdf_url;
        }

        $response = $this->client->downloadBoletoPdf($boleto->external_id);
        $contents = $response->body();

        if ($contents === '' || $contents === null) {
            return null;
        }

        $diskName = config('services.bradesco_boleto.pdf_disk', 'public');
        $disk = Storage::disk($diskName);

        $prefix = trim((string) config('services.bradesco_boleto.pdf_path', 'boletos/bradesco'), '/');
        $filename = ($boleto->nosso_numero ?: $boleto->external_id).'.pdf';
        $relativePath = $prefix !== '' ? $prefix.'/'.$filename : $filename;

        $disk->put($relativePath, $contents);

        $url = $disk->url($relativePath);

        $boleto->forceFill([
            'pdf_url' => $url,
        ])->save();

        return $url;
    }

    public function cancel(FaturaBoleto $boleto, array $contexto = []): FaturaBoleto
    {
        if (! $boleto->external_id) {
            return $boleto;
        }

        $payload = array_filter([
            'nuTitulo' => $boleto->external_id,
            'motivo' => Arr::get($contexto, 'motivo'),
        ], fn ($value) => $value !== null && $value !== '');

        $this->log('Cancelamento de boleto solicitado', [
            'fatura_boleto_id' => $boleto->id,
            'external_id' => $boleto->external_id,
            'payload' => $payload,
        ]);

        $response = $this->client->cancelBoleto($payload);
        $sanitizedResponse = BradescoPayloadSanitizer::sanitize($response);

        $boleto->update([
            'status' => FaturaBoleto::STATUS_CANCELED,
            'response_payload' => $sanitizedResponse,
        ]);

        $this->log('Cancelamento de boleto concluído', [
            'fatura_boleto_id' => $boleto->id,
            'response' => $response,
        ]);

        return $boleto;
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @return array<string, mixed>
     */
    protected function buildIssuePayload(Fatura $fatura, array $contexto = []): array
    {
        $config = config('services.bradesco_boleto');
        $contrato = $fatura->contrato;
        $pagador = $contrato?->locatario;
        if (! $pagador) {
            throw new \RuntimeException('Contrato sem locatário vinculado, impossibilitando emissão de boleto.');
        }

        $pagadorData = $this->buildPagadorPayload($pagador);
        $valorBase = (float) Arr::get($contexto, 'valor', $fatura->valor_total);
        $valorNominal = $this->formatDecimal($valorBase);
        $emissao = $this->formatDate(Carbon::now());
        $vencimentoDate = optional($fatura->vencimento)->copy() ?? Carbon::now();
        $vencimento = $this->formatDate($vencimentoDate);
        [$cep, $cepComplemento] = $this->splitCep($pagadorData['cep']);
        $numeroDocumentoCliente = $this->formatNumeroDocumento($fatura);
        $nossoNumero = $this->formatNossoNumero($fatura);
        [$dddSacado, $foneSacado] = $this->splitTelefone($pagadorData['telefone']);

        $beneficiario = $this->buildBeneficiarioPayload();

        $jurosPercentual = (float) Arr::get($contexto, 'juros.percentual', 2);
        $jurosValor = Arr::get($contexto, 'juros.valor');
        if (! Arr::has($contexto, 'juros.valor')) {
            $jurosValor = round($valorBase * ($jurosPercentual / 100), 2);
        }

        $multaPercentual = (float) Arr::get($contexto, 'multa.percentual', 10);
        $multaValor = Arr::get($contexto, 'multa.valor');
        if (! Arr::has($contexto, 'multa.valor')) {
            $multaValor = round($valorBase * ($multaPercentual / 100), 2);
        }

        $instructions = Arr::get($contexto, 'instrucoes', $this->buildInstructions($fatura, [
            'juros_valor' => $jurosValor,
            'multa_valor' => $multaValor,
            'multa_percentual' => $multaPercentual,
        ]));

        $registraTituloConfig = Str::upper(trim((string) Arr::get($config, 'registra_titulo', '1')));
        $registraTitulo = in_array($registraTituloConfig, ['S', '1'], true) ? '1' : '2';
        $tpVencimento = trim((string) Arr::get($config, 'tipo_vencimento', '0')) ?: '0';
        $indicadorMoeda = trim((string) Arr::get($config, 'indicador_moeda', '1')) ?: '1';
        $quantidadeMoedaConfig = Arr::get($config, 'quantidade_moeda', '0');
        $qtdeMoeda = $this->formatDecimal($quantidadeMoedaConfig, 2);
        $indicadorAceite = trim((string) Arr::get($config, 'indicador_aceite_sacado', '2')) ?: '2';
        $tpProtesto = trim((string) Arr::get($config, 'tp_protesto', '0')) ?: '0';
        $prazoProtesto = $this->formatInteger((int) Arr::get($config, 'prazo_protesto', 0), 2);
        $tipoDecurso = Arr::get($config, 'tipo_decurso', '0');
        $tipoDiasDecurso = Arr::get($config, 'tipo_dias_decurso', '0');
        $tipoPrazoTres = Arr::get($config, 'tipo_prazo_tres', '000');
        $nuNegociacao = $this->formatNumericField(Arr::get($config, 'negociacao'), 18);
        $cdEspecie = $this->formatNumericField(Arr::get($config, 'cod_especie'), 2);
        $idProduto = trim((string) Arr::get($config, 'id_produto', ''));
        $idProduto = $idProduto !== '' ? $idProduto : '00';

        $payload = [
            'debitoAutomatico' => Arr::get($contexto, 'debito_automatico', 'N'),
            'nuCPFCNPJ' => $beneficiario['raiz'],
            'filialCPFCNPJ' => $beneficiario['filial'],
            'ctrlCPFCNPJ' => $beneficiario['controle'],
            'idProduto' => $idProduto,
            'nuNegociacao' => $nuNegociacao,
            'nuTitulo' => $nossoNumero,
            'nuCliente' => $numeroDocumentoCliente,
            'registraTitulo' => $registraTitulo,
            'tpVencimento' => $tpVencimento,
            'indicadorMoeda' => $indicadorMoeda,
            'qmoedaNegocTitlo' => $qtdeMoeda,
            'cdEspecieTitulo' => $cdEspecie,
            'dtEmissaoTitulo' => $emissao,
            'dtVencimentoTitulo' => $vencimento,
            'vlNominalTitulo' => $valorNominal,
            'vlIOF' => $this->formatDecimal(Arr::get($contexto, 'iof', 0)),
            'vlAbatimento' => $this->formatDecimal(Arr::get($contexto, 'abatimento.valor', 0)),
            'prazoBonificacao' => $this->formatInteger(Arr::get($contexto, 'bonificacao.prazo', 0), 2),
            'percentualBonificacao' => $this->formatPercent(Arr::get($contexto, 'bonificacao.percentual', 0)),
            'vlBonificacao' => $this->formatDecimal(Arr::get($contexto, 'bonificacao.valor', 0)),
            'dtLimiteBonificacao' => $this->formatOptionalDate(Arr::get($contexto, 'bonificacao.data_limite')),
            'percentualDesconto1' => $this->formatPercent(Arr::get($contexto, 'descontos.0.percentual', 0)),
            'vlDesconto1' => $this->formatDecimal(Arr::get($contexto, 'descontos.0.valor', 0)),
            'dataLimiteDesconto1' => $this->formatOptionalDate(Arr::get($contexto, 'descontos.0.data_limite')),
            'percentualDesconto2' => $this->formatPercent(Arr::get($contexto, 'descontos.1.percentual', 0)),
            'vlDesconto2' => $this->formatDecimal(Arr::get($contexto, 'descontos.1.valor', 0)),
            'dataLimiteDesconto2' => $this->formatOptionalDate(Arr::get($contexto, 'descontos.1.data_limite')),
            'percentualDesconto3' => $this->formatPercent(Arr::get($contexto, 'descontos.2.percentual', 0)),
            'vlDesconto3' => $this->formatDecimal(Arr::get($contexto, 'descontos.2.valor', 0)),
            'dataLimiteDesconto3' => $this->formatOptionalDate(Arr::get($contexto, 'descontos.2.data_limite')),
            'percentualJuros' => null,
            'vlJuros' => $this->formatDecimal($jurosValor),
            'qtdeDiasJuros' => $this->formatInteger(Arr::get($contexto, 'juros.dias', 1), 2),
            'percentualMulta' => null,
            'vlMulta' => $this->formatDecimal($multaValor),
            'qtdeDiasMulta' => $this->formatInteger(Arr::get($contexto, 'multa.dias', 1), 3),
            'cdPagamentoParcial' => Arr::get($contexto, 'pagamento_parcial.indicador', 'N'),
            'qtdePagamentoParcial' => $this->formatInteger(Arr::get($contexto, 'pagamento_parcial.quantidade', 0), 3),
            'tipoDecursoPrazo' => $tipoDecurso,
            'tipoDiasDecursoProt' => $tipoDiasDecurso,
            'tipoPrazoDecursoTres' => $this->formatInteger((int) $tipoPrazoTres, 3),
            'cindcdAceitSacdo' => Arr::get($contexto, 'indicador_aceite', $indicadorAceite),
            'tpProtestoAutomaticoNegativacao' => $tpProtesto,
            'prazoProtestoAutomaticoNegativacao' => $prazoProtesto,
            'codBancoDoProtesto' => Arr::get($contexto, 'protesto.banco', '000'),
            'agenciaDoProtesto' => Arr::get($contexto, 'protesto.agencia', '0000'),
            'nomePagador' => $pagadorData['nome'],
            'logradouroPagador' => $pagadorData['logradouro'],
            'nuLogradouroPagador' => $pagadorData['numero'],
            'complementoLogradouroPagador' => $pagadorData['complemento'] ?? '',
            'cepPagador' => $cep,
            'complementoCepPagador' => $cepComplemento,
            'bairroPagador' => $pagadorData['bairro'],
            'municipioPagador' => $pagadorData['cidade'],
            'ufPagador' => $pagadorData['uf'],
            'cdIndCpfcnpjPagador' => $pagadorData['tipo_documento'],
            'nuCpfcnpjPagador' => $pagadorData['documento'],
            'endEletronicoPagador' => $pagadorData['email'],
            'dddFoneSacado' => $dddSacado,
            'foneSacado' => $foneSacado,
            'bancoDoDebAutomatico' => Arr::get($contexto, 'debito.banco', '000'),
            'agenciaDoDebAutomatico' => Arr::get($contexto, 'debito.agencia', '00000'),
            'digitoAgenciaDoDebAutomat' => Arr::get($contexto, 'debito.agencia_digito', '0'),
            'contaDoDebAutomatico' => Arr::get($contexto, 'debito.conta', '0000000000000'),
            'razaoDoDebAutomatico' => Arr::get($contexto, 'debito.razao', '000000'),
            'controleParticipante' => $this->formatControleParticipante($fatura),
        ];

        $payload = array_merge($payload, $this->buildSacadorAvalistaDefaults($contexto));
        $payload = array_merge($payload, $instructions);

        $payload = $this->normalizePercentualValorFields($payload);
        $payload = $this->pruneDiscountAndBonificacaoFields($payload);

        if ($this->shouldApplySandboxFixtures()) {
            $payload = array_replace_recursive($payload, $this->getSandboxOverrides());
        }

        return array_filter($payload, fn ($value) => $value !== null);
    }

    /**
     * @return array{raiz:string,filial:string,controle:string}
     */
    protected function buildBeneficiarioPayload(): array
    {
        $config = config('services.bradesco_boleto');

        $raiz = substr(str_pad($this->digits(Arr::get($config, 'cnpj_raiz')), 8, '0', STR_PAD_LEFT), 0, 8);

        return [
            'raiz' => $raiz,
            'filial' => substr(str_pad($this->digits(Arr::get($config, 'cnpj_filial')), 4, '0', STR_PAD_LEFT), 0, 4),
            'controle' => substr(str_pad($this->digits(Arr::get($config, 'cnpj_controle')), 2, '0', STR_PAD_LEFT), 0, 2),
        ];
    }

    protected function buildPagadorPayload($pagador): array
    {
        $cpfCnpj = $this->digits($pagador->cpf_cnpj ?? '');
        $telefone = $this->digits($pagador->telefone ?? '');

        return [
            'nome' => $this->normalizeText((string) ($pagador->nome_razao_social ?? 'PAGADOR NAO INFORMADO'), 70),
            'documento' => $cpfCnpj !== '' ? $cpfCnpj : str_pad('', 11, '0'),
            'tipo_documento' => strlen($cpfCnpj) > 11 ? '2' : '1',
            'logradouro' => $this->normalizeText((string) ($pagador->rua ?? 'NAO INFORMADO'), 40),
            'numero' => $this->normalizeText((string) ($pagador->numero ?? 'S/N'), 10),
            'complemento' => $pagador->complemento ? $this->normalizeText((string) $pagador->complemento, 15) : '',
            'bairro' => $this->normalizeText((string) ($pagador->bairro ?? 'CENTRO'), 40),
            'cidade' => $this->normalizeText((string) ($pagador->cidade ?? 'SAO PAULO'), 30),
            'uf' => Str::upper(substr((string) ($pagador->estado ?? 'SP'), 0, 2)),
            'cep' => $this->digits($pagador->cep ?? ''),
            'email' => $pagador->email ? Str::limit(mb_strtolower(trim((string) $pagador->email)), 70, '') : '',
            'telefone' => $telefone,
        ];
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @return array<string, string>
     */
    protected function buildSacadorAvalistaDefaults(array $contexto = []): array
    {
        $defaults = [
            'nomeSacadorAvalista' => '',
            'cdIndCpfcnpjSacadorAvalista' => '0',
            'nuCpfcnpjSacadorAvalista' => '00000000000000',
            'logradouroSacadorAvalista' => '',
            'nuLogradouroSacadorAvalista' => '',
            'complementoLogradouroSacadorAvalista' => '',
            'cepSacadorAvalista' => '00000',
            'complementoCepSacadorAvalista' => '000',
            'bairroSacadorAvalista' => '',
            'municipioSacadorAvalista' => '',
            'ufSacadorAvalista' => '',
            'dddFoneSacadorAvalista' => '00',
            'foneSacadorAvalista' => '000000000',
            'enderecoSacadorAvalista' => '',
        ];

        $sacador = Arr::get($contexto, 'sacador', []);
        if (! is_array($sacador)) {
            return $defaults;
        }

        $documento = $this->digits(Arr::get($sacador, 'documento', ''));
        $telefone = $this->digits(Arr::get($sacador, 'telefone', ''));
        [$ddd, $fone] = $this->splitTelefone($telefone);

        return array_merge($defaults, array_filter([
            'nomeSacadorAvalista' => $this->normalizeText((string) Arr::get($sacador, 'nome', ''), 40),
            'cdIndCpfcnpjSacadorAvalista' => $documento !== '' && strlen($documento) > 11 ? '2' : ($documento !== '' ? '1' : $defaults['cdIndCpfcnpjSacadorAvalista']),
            'nuCpfcnpjSacadorAvalista' => $documento !== '' ? $documento : $defaults['nuCpfcnpjSacadorAvalista'],
            'logradouroSacadorAvalista' => $this->normalizeText((string) Arr::get($sacador, 'logradouro', ''), 40),
            'nuLogradouroSacadorAvalista' => $this->normalizeText((string) Arr::get($sacador, 'numero', ''), 10),
            'complementoLogradouroSacadorAvalista' => $this->normalizeText((string) Arr::get($sacador, 'complemento', ''), 15),
            'cepSacadorAvalista' => substr(str_pad($this->digits(Arr::get($sacador, 'cep', '')), 5, '0', STR_PAD_LEFT), 0, 5),
            'complementoCepSacadorAvalista' => substr(str_pad($this->digits(Arr::get($sacador, 'cep', '')), 8, '0', STR_PAD_LEFT), 5, 3),
            'bairroSacadorAvalista' => $this->normalizeText((string) Arr::get($sacador, 'bairro', ''), 40),
            'municipioSacadorAvalista' => $this->normalizeText((string) Arr::get($sacador, 'municipio', ''), 40),
            'ufSacadorAvalista' => Str::upper(substr((string) Arr::get($sacador, 'uf', ''), 0, 2)),
            'dddFoneSacadorAvalista' => $ddd,
            'foneSacadorAvalista' => $fone,
            'enderecoSacadorAvalista' => $this->normalizeText((string) Arr::get($sacador, 'endereco', ''), 70),
        ], fn ($value) => $value !== null));
    }

    protected function buildInstructions(Fatura $fatura, array $context = []): array
    {
        $items = $fatura->relationLoaded('itens') ? $fatura->itens : $fatura->itens()->get();

        $valorTotal = (float) ($fatura->valor_total ?? 0);
        $jurosDia = Arr::get($context, 'juros_valor');
        if ($jurosDia === null) {
            $jurosDia = $valorTotal * 0.02;
        }

        $multa = Arr::get($context, 'multa_valor');
        if ($multa === null) {
            $multa = $valorTotal * 0.10;
        }

        $vencimento = optional($fatura->vencimento)->format('d/m/Y');

        $lines = collect([
            'VALORES EXPRESSOS EM REAIS',
            sprintf('JUROS POR DIA DE ATRASO R$ %s', $this->formatBrCurrency($jurosDia)),
            $vencimento
                ? sprintf('APOS %s MULTA R$ %s', $vencimento, $this->formatBrCurrency($multa))
                : sprintf('MULTA APOS VENCIMENTO R$ %s', $this->formatBrCurrency($multa)),
        ]);

        $itemLines = $items->map(function ($item) {
            $categoria = $this->normalizeText($item->categoria ?? 'ITEM', 40) ?: 'ITEM';
            $valorUnitario = (float) ($item->valor_unitario ?? $item->valor_total ?? 0);

            return sprintf('%s R$ %s', $categoria, $this->formatBrCurrency($valorUnitario));
        })->filter();

        if ($itemLines->isNotEmpty()) {
            $lines = $lines->merge($itemLines);
        }

        $messages = $lines
            ->map(fn ($line) => Str::limit($this->normalizeText((string) $line, 70), 70, ''))
            ->unique()
            ->take(6)
            ->map(fn ($line) => ['mensagem' => $line])
            ->values()
            ->all();

        return empty($messages) ? [] : ['listaMsgs' => $messages];
    }

    protected function shouldApplySandboxFixtures(): bool
    {
        return $this->isSandbox()
            && (bool) config('services.bradesco_boleto.sandbox_use_fixtures', false);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getSandboxOverrides(): array
    {
        $overrides = config('services.bradesco_boleto.sandbox_payload_overrides', []);

        return is_array($overrides) ? $overrides : [];
    }

    protected function avoidSandboxExternalIdCollision(string $externalId, ?int $faturaId = null): void
    {
        FaturaBoleto::query()
            ->where('bank_code', BradescoApiClient::BANK_CODE)
            ->when($faturaId, fn ($query) => $query->where('fatura_id', $faturaId))
            ->where('external_id', $externalId)
            ->where('status', FaturaBoleto::STATUS_CANCELED)
            ->get()
            ->each(function (FaturaBoleto $duplicated) use ($externalId) {
                if (Str::startsWith($duplicated->external_id, $externalId.'#')) {
                    return;
                }

                $duplicated->external_id = sprintf('%s#%s', $externalId, $duplicated->id);
                $duplicated->save();
            });
    }

    protected function isSandbox(): bool
    {
        return Str::lower((string) config('services.bradesco_boleto.environment')) === 'sandbox';
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function resolveExternalId(array $response): ?string
    {
        foreach (['id', 'nuTituloGerado', 'nuTitulo', 'nuTituloOriginal'] as $candidate) {
            $value = Arr::get($response, $candidate);
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    protected function formatDecimal($value, int $precision = 2): string
    {
        $numeric = (float) $value;

        return number_format($numeric, $precision, '.', '');
    }

    protected function formatBrCurrency(float $value): string
    {
        $numeric = max($value, 0);

        return number_format($numeric, 2, ',', '.');
    }

    protected function formatDate(Carbon $date): string
    {
        return $date->format('d.m.Y');
    }

    protected function formatOptionalDate($date): string
    {
        if ($date instanceof Carbon) {
            return $this->formatDate($date);
        }

        if (is_string($date)) {
            $trimmed = trim($date);
            if ($trimmed === '' || $trimmed === '0' || $trimmed === '00.00.0000') {
                return '00.00.0000';
            }

            try {
                return $this->formatDate(Carbon::parse($trimmed));
            } catch (\Throwable) {
                return '00.00.0000';
            }
        }

        return '00.00.0000';
    }

    protected function formatPercent($percent): string
    {
        $percent = round((float) $percent, 2);

        return number_format($percent, 2, '.', '');
    }

    protected function formatInteger($value, int $length = 3): string
    {
        $int = (int) round($value);

        return str_pad((string) max($int, 0), $length, '0', STR_PAD_LEFT);
    }

    protected function formatNumericField($value, int $length): string
    {
        $digits = $this->digits((string) $value);
        if ($digits === '') {
            $digits = '0';
        }

        if (strlen($digits) > $length) {
            $digits = substr($digits, -$length);
        }

        return str_pad($digits, $length, '0', STR_PAD_LEFT);
    }

    protected function splitCep(?string $cep): array
    {
        $digits = $this->digits($cep ?? '');
        $digits = str_pad(substr($digits, 0, 8), 8, '0', STR_PAD_RIGHT);

        return [
            substr($digits, 0, 5),
            substr($digits, 5, 3),
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    protected function splitTelefone(?string $telefone): array
    {
        $digits = $this->digits($telefone ?? '');

        if ($digits === '') {
            return ['00', '000000000'];
        }

        $ddd = substr($digits, 0, 2);
        $numero = substr($digits, 2);

        if ($numero === false || $numero === '') {
            $numero = '000000000';
        }

        return [
            str_pad(substr($ddd, 0, 2), 2, '0', STR_PAD_LEFT),
            str_pad(substr($numero, 0, 9), 9, '0', STR_PAD_LEFT),
        ];
    }

    protected function digits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    protected function formatNumeroDocumento(Fatura $fatura): string
    {
        $numero = (string) $fatura->id;

        if ($contratoCodigo = $fatura->contrato?->codigo_contrato) {
            $numero = Str::slug($contratoCodigo.'-'.$fatura->id);
        }

        $numero = $this->digits($numero) ?: (string) $fatura->id;

        return Str::limit(str_pad($numero, 10, '0', STR_PAD_LEFT), 25, '');
    }

    protected function formatControleParticipante(Fatura $fatura): ?string
    {
        $base = $fatura->contrato?->codigo_contrato;

        return $base ? Str::limit($this->normalizeText($base, 25), 25, '') : '';
    }

    protected function formatNossoNumero(Fatura $fatura): string
    {
        $contratoCodigo = $fatura->contrato?->codigo_contrato;
        $base = $this->digits($contratoCodigo ?? '') ?: (string) $fatura->id;

        return Str::limit(str_pad($base, 11, '0', STR_PAD_LEFT), 11, '');
    }

    protected function resolveInternalStatus(?string $status, string $fallback): string
    {
        if (! $status) {
            return $fallback;
        }

        $normalized = Str::of($status)->lower()->replaceMatches('/[^a-z_]/', '')->value();

        return match (true) {
            in_array($normalized, ['paid', 'pago', 'paga', 'liquidado', 'liquidada', 'liquidadoem', 'liquidadaem']) => FaturaBoleto::STATUS_PAID,
            in_array($normalized, ['cancelado', 'cancelada', 'baixado', 'baixada', 'canceladoem', 'canceladaem', 'canceled']) => FaturaBoleto::STATUS_CANCELED,
            in_array($normalized, ['failed', 'erro', 'falha', 'error']) => FaturaBoleto::STATUS_FAILED,
            in_array($normalized, ['registered', 'registrado', 'registrada', 'emitido', 'emitida']) => FaturaBoleto::STATUS_REGISTERED,
            in_array($normalized, ['pending', 'pendente', 'aberto', 'emaberto']) => FaturaBoleto::STATUS_PENDING,
            default => $fallback,
        };
    }

    protected function log(string $message, array $context = []): void
    {
        Log::channel('bradesco')->info($message, $this->sanitizeContext($context));
    }

    protected function resolveStatusFromResponse(array $response, string $fallback): string
    {
        $status = Arr::get($response, 'titulo.status')
            ?? Arr::get($response, 'status')
            ?? null;

        return $this->resolveInternalStatus($status, $fallback);
    }

    protected function parseBradescoDate(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{8}$/', $string)) {
                return Carbon::createFromFormat('dmY', $string);
            }

            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $string)) {
                return Carbon::createFromFormat('d/m/Y', $string);
            }

            return Carbon::parse($string);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function sanitizeContext(array $context): array
    {
        if (isset($context['payload'])) {
            $context['payload'] = $this->sanitizePayload($context['payload']);
        }

        if (isset($context['response'])) {
            $context['response'] = $this->sanitizePayload($context['response']);
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sanitizePayload(array $payload): array
    {
        return BradescoPayloadSanitizer::sanitize($payload);
    }

    /**
     * Garante que campos de percentual/valor sigam a regra do manual (apenas um preenchido).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizePercentualValorFields(array $payload): array
    {
        $pairs = [
            ['percentualBonificacao', 'vlBonificacao'],
            ['percentualDesconto1', 'vlDesconto1'],
            ['percentualDesconto2', 'vlDesconto2'],
            ['percentualDesconto3', 'vlDesconto3'],
            ['percentualJuros', 'vlJuros'],
            ['percentualMulta', 'vlMulta'],
        ];

        foreach ($pairs as [$percentKey, $valueKey]) {
            $payload = $this->normalizePercentualValorPair($payload, $percentKey, $valueKey);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizePercentualValorPair(array $payload, string $percentKey, string $valueKey): array
    {
        $percent = $this->toFloat($payload[$percentKey] ?? 0);
        $valor = $this->toFloat($payload[$valueKey] ?? 0);

        if ($percent > 0 && $valor > 0) {
            $valor = 0.0;
        }

        if ($percent > 0) {
            $payload[$percentKey] = $this->formatPercent($percent);
        } else {
            unset($payload[$percentKey]);
        }

        if ($valor > 0) {
            $payload[$valueKey] = $this->formatDecimal($valor);
        } else {
            unset($payload[$valueKey]);
        }

        return $payload;
    }

    protected function toFloat($value): float
    {
        if (is_string($value)) {
            $value = str_replace(['.', ','], ['.', '.'], trim($value));
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    protected function normalizeText(string $value, int $limit = 70): string
    {
        $text = Str::of($value)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9\\-\\.\\/ ,\\$]+/', ' ')
            ->squish()
            ->value();

        return Str::limit($text, $limit, '');
    }

    /**
     * Remove blocos de desconto/bonificação vazios para evitar validações do Bradesco.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function pruneDiscountAndBonificacaoFields(array $payload): array
    {
        foreach ([1, 2, 3] as $index) {
            $percentKey = "percentualDesconto{$index}";
            $valueKey = "vlDesconto{$index}";
            $dateKey = "dataLimiteDesconto{$index}";

            if ($this->isZeroAmount($payload[$percentKey] ?? null) && $this->isZeroAmount($payload[$valueKey] ?? null)) {
                unset($payload[$percentKey], $payload[$valueKey]);

                if (array_key_exists($dateKey, $payload)) {
                    unset($payload[$dateKey]);
                }
            }
        }

        if ($this->isZeroAmount($payload['percentualBonificacao'] ?? null) && $this->isZeroAmount($payload['vlBonificacao'] ?? null)) {
            unset(
                $payload['percentualBonificacao'],
                $payload['vlBonificacao'],
                $payload['dtLimiteBonificacao']
            );

            if (array_key_exists('prazoBonificacao', $payload) && $this->isZeroAmount($payload['prazoBonificacao'])) {
                unset($payload['prazoBonificacao']);
            }
        }

        return $payload;
    }

    protected function isZeroAmount($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_numeric($value)) {
            return (float) $value == 0.0;
        }

        $normalized = str_replace(',', '.', (string) $value);

        return is_numeric($normalized) && (float) $normalized == 0.0;
    }
}
