<?php

namespace App\Services\Banking\Bradesco;

use App\Models\Fatura;
use App\Models\FaturaBoleto;
use App\Services\Boleto\BoletoGateway;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BradescoBoletoGateway implements BoletoGateway
{
    public function __construct(private readonly BradescoApiClient $client)
    {
    }

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

            $boleto = $fatura->boletos()->create([
                'bank_code' => BradescoApiClient::BANK_CODE,
                'external_id' => $externalId,
                'nosso_numero' => $nossoNumero,
                'document_number' => Arr::get($response, 'numeroDocumento'),
                'linha_digitavel' => Arr::get($response, 'linhaDigitavel'),
                'codigo_barras' => Arr::get($response, 'codigoBarras'),
                'valor' => Arr::get($response, 'valor', $fatura->valor_total),
                'vencimento' => Arr::get($response, 'vencimento', $fatura->vencimento),
                'status' => $this->resolveInternalStatus(
                    Arr::get($response, 'status'),
                    FaturaBoleto::STATUS_REGISTERED
                ),
                'registrado_em' => now(),
                'pdf_url' => $pdfUrl,
                'payload' => $payload,
                'response_payload' => $response,
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

        $boleto->fill([
            'status' => $this->resolveInternalStatus(Arr::get($response, 'status'), $boleto->status),
            'linha_digitavel' => Arr::get($response, 'linhaDigitavel', $boleto->linha_digitavel),
            'codigo_barras' => Arr::get($response, 'codigoBarras', $boleto->codigo_barras),
            'pdf_url' => Arr::get($response, 'urlPdf', $boleto->pdf_url),
            'response_payload' => $response,
            'last_synced_at' => now(),
        ]);

        if ($boleto->status === FaturaBoleto::STATUS_PAID) {
            $boleto->markAsPaid(
                (float) Arr::get($response, 'valorPago', $boleto->valor)
            );
        }

        $boleto->save();

        $this->log('Sincronização de boleto concluída', [
            'fatura_boleto_id' => $boleto->id,
            'status' => $boleto->status,
            'response' => $response,
        ]);

        return $boleto;
    }

    public function cancel(FaturaBoleto $boleto, array $contexto = []): FaturaBoleto
    {
        if (! $boleto->external_id) {
            return $boleto;
        }

        $payload = [
            'motivo' => Arr::get($contexto, 'motivo'),
        ];

        $this->log('Cancelamento de boleto solicitado', [
            'fatura_boleto_id' => $boleto->id,
            'external_id' => $boleto->external_id,
            'payload' => $payload,
        ]);

        $response = $this->client->cancelBoleto($boleto->external_id, $payload);

        $boleto->update([
            'status' => FaturaBoleto::STATUS_CANCELED,
            'response_payload' => $response,
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
        $valorNominal = $this->formatDecimal(Arr::get($contexto, 'valor', $fatura->valor_total));
        $emissao = $this->formatDate(Carbon::now());
        $vencimentoDate = optional($fatura->vencimento)->copy() ?? Carbon::now();
        $vencimento = $this->formatDate($vencimentoDate);
        [$cep, $cepComplemento] = $this->splitCep($pagadorData['cep']);
        $numeroDocumentoCliente = $this->formatNumeroDocumento($fatura);
        $nossoNumero = $this->formatNossoNumero($fatura);
        [$dddSacado, $foneSacado] = $this->splitTelefone($pagadorData['telefone']);

        $beneficiario = $this->buildBeneficiarioPayload();

        $instructions = Arr::get($contexto, 'instrucoes', $this->buildInstructions($fatura));

        $codigoUsuario = Arr::get($config, 'codigo_usuario', '0000000');
        $registraTitulo = Arr::get($config, 'registra_titulo', 'S');
        $tpVencimento = Arr::get($config, 'tipo_vencimento', '0');
        $indicadorMoeda = Arr::get($config, 'indicador_moeda', '1');
        $qtdeMoeda = Arr::get($config, 'quantidade_moeda', '00000000000000000');
        $indicadorAceite = Arr::get($config, 'indicador_aceite_sacado', '2');
        $tpProtesto = Arr::get($config, 'tp_protesto', '0');
        $prazoProtesto = $this->formatInteger((int) Arr::get($config, 'prazo_protesto', 0), 2);
        $tipoDecurso = Arr::get($config, 'tipo_decurso', '0');
        $tipoDiasDecurso = Arr::get($config, 'tipo_dias_decurso', '0');
        $tipoPrazoTres = Arr::get($config, 'tipo_prazo_tres', '000');

        $payload = [
            'debitoAutomatico' => Arr::get($contexto, 'debito_automatico', 'N'),
            'nuCPFCNPJ' => $beneficiario['raiz'],
            'filialCPFCNPJ' => $beneficiario['filial'],
            'ctrlCPFCNPJ' => $beneficiario['controle'],
            'idProduto' => Arr::get($config, 'id_produto'),
            'nuNegociacao' => Arr::get($config, 'negociacao'),
            'nuTitulo' => $nossoNumero,
            'nuCliente' => $numeroDocumentoCliente,
            'codigoUsuarioSolicitante' => $codigoUsuario,
            'registraTitulo' => $registraTitulo,
            'tpVencimento' => $tpVencimento,
            'indicadorMoeda' => $indicadorMoeda,
            'qmoedaNegocTitlo' => $qtdeMoeda,
            'cdEspecieTitulo' => Arr::get($config, 'cod_especie'),
            'dtEmissaoTitulo' => $emissao,
            'dtVencimentoTitulo' => $vencimento,
            'vlNominalTitulo' => $valorNominal,
            'vlIOF' => $this->formatDecimal(Arr::get($contexto, 'iof', 0)),
            'vlAbatimento' => $this->formatDecimal(Arr::get($contexto, 'abatimento.valor', 0)),
            'prazoBonificacao' => $this->formatInteger(Arr::get($contexto, 'bonificacao.prazo', 0), 3),
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
            'percentualJuros' => $this->formatPercent(Arr::get($contexto, 'juros.percentual', 2)),
            'vlJuros' => $this->formatDecimal(Arr::get($contexto, 'juros.valor', 0)),
            'qtdeDiasJuros' => $this->formatInteger(Arr::get($contexto, 'juros.dias', 1), 2),
            'percentualMulta' => $this->formatPercent(Arr::get($contexto, 'multa.percentual', 10)),
            'vlMulta' => $this->formatDecimal(Arr::get($contexto, 'multa.valor', 0)),
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

        $raiz = str_pad($this->digits(Arr::get($config, 'cnpj_raiz')), 8, '0', STR_PAD_LEFT);
        if (strlen($raiz) > 9) {
            $raiz = substr($raiz, 0, 9);
        }

        return [
            'raiz' => $raiz,
            'filial' => str_pad($this->digits(Arr::get($config, 'cnpj_filial')), 4, '0', STR_PAD_LEFT),
            'controle' => str_pad($this->digits(Arr::get($config, 'cnpj_controle')), 2, '0', STR_PAD_LEFT),
        ];
    }

    protected function buildPagadorPayload($pagador): array
    {
        $cpfCnpj = $this->digits($pagador->cpf_cnpj ?? '');
        $telefone = $this->digits($pagador->telefone ?? '');

        return [
            'nome' => Str::limit(Str::upper(trim((string) ($pagador->nome_razao_social ?? 'Pagador Não Informado'))), 70, ''),
            'documento' => $cpfCnpj !== '' ? $cpfCnpj : str_pad('', 11, '0'),
            'tipo_documento' => strlen($cpfCnpj) > 11 ? '2' : '1',
            'logradouro' => Str::limit(Str::upper(trim((string) ($pagador->rua ?? 'NAO INFORMADO'))), 40, ''),
            'numero' => Str::limit(Str::upper(trim((string) ($pagador->numero ?? 'S/N'))), 10, ''),
            'complemento' => $pagador->complemento ? Str::limit(Str::upper(trim((string) $pagador->complemento)), 15, '') : null,
            'bairro' => Str::limit(Str::upper(trim((string) ($pagador->bairro ?? 'CENTRO'))), 40, ''),
            'cidade' => Str::limit(Str::upper(trim((string) ($pagador->cidade ?? 'SAO PAULO'))), 30, ''),
            'uf' => Str::upper(substr((string) ($pagador->estado ?? 'SP'), 0, 2)),
            'cep' => $this->digits($pagador->cep ?? ''),
            'email' => $pagador->email ? Str::limit(trim((string) $pagador->email), 70, '') : null,
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
            'nomeSacadorAvalista' => Str::limit(Str::upper(trim((string) Arr::get($sacador, 'nome', ''))), 40, ''),
            'cdIndCpfcnpjSacadorAvalista' => $documento !== '' && strlen($documento) > 11 ? '2' : ($documento !== '' ? '1' : $defaults['cdIndCpfcnpjSacadorAvalista']),
            'nuCpfcnpjSacadorAvalista' => $documento !== '' ? $documento : $defaults['nuCpfcnpjSacadorAvalista'],
            'logradouroSacadorAvalista' => Str::limit(Str::upper(trim((string) Arr::get($sacador, 'logradouro', ''))), 40, ''),
            'nuLogradouroSacadorAvalista' => Str::limit(Str::upper(trim((string) Arr::get($sacador, 'numero', ''))), 10, ''),
            'complementoLogradouroSacadorAvalista' => Str::limit(Str::upper(trim((string) Arr::get($sacador, 'complemento', ''))), 15, ''),
            'cepSacadorAvalista' => substr(str_pad($this->digits(Arr::get($sacador, 'cep', '')), 5, '0', STR_PAD_LEFT), 0, 5),
            'complementoCepSacadorAvalista' => substr(str_pad($this->digits(Arr::get($sacador, 'cep', '')), 8, '0', STR_PAD_LEFT), 5, 3),
            'bairroSacadorAvalista' => Str::limit(Str::upper(trim((string) Arr::get($sacador, 'bairro', ''))), 40, ''),
            'municipioSacadorAvalista' => Str::limit(Str::upper(trim((string) Arr::get($sacador, 'municipio', ''))), 40, ''),
            'ufSacadorAvalista' => Str::upper(substr((string) Arr::get($sacador, 'uf', ''), 0, 2)),
            'dddFoneSacadorAvalista' => $ddd,
            'foneSacadorAvalista' => $fone,
            'enderecoSacadorAvalista' => Str::limit(Str::upper(trim((string) Arr::get($sacador, 'endereco', ''))), 70, ''),
        ], fn ($value) => $value !== null));
    }

    protected function buildInstructions(Fatura $fatura): array
    {
        $items = $fatura->relationLoaded('itens') ? $fatura->itens : $fatura->itens()->get();

        $lines = $items->map(function ($item) {
            $categoria = Str::upper(Str::limit((string) ($item->categoria ?? 'ITEM'), 40, ''));
            $valor = number_format((float) ($item->valor_total ?? 0), 2, ',', '.');

            return \sprintf('%s: R$ %s', $categoria, $valor);
        })->filter();

        if ($lines->isEmpty()) {
            $lines->push('Pagamento referente à fatura de locação.');
        }

        $chunks = $lines->chunk(3);

        $messages = [];
        foreach ($chunks as $index => $chunk) {
            if ($index >= 4) {
                break;
            }

            $messages[] = [
                'mensagem' => Str::limit(
                    $chunk->implode(' | '),
                    70,
                    ''
                ),
            ];
        }

        return $messages === [] ? [] : ['listaMsgs' => $messages];
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
        $percent = (float) $percent;

        return number_format($percent, 2, '.', '');
    }

    protected function formatInteger($value, int $length = 3): string
    {
        $int = (int) round($value);

        return str_pad((string) max($int, 0), $length, '0', STR_PAD_LEFT);
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

        return $base ? Str::limit(Str::upper($base), 25, '') : null;
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
        if (isset($payload['pagador'])) {
            $payload['pagador'] = $this->maskPagador($payload['pagador']);
        }

        foreach (['linhaDigitavel', 'linha_digitavel'] as $key) {
            if (isset($payload[$key])) {
                $payload[$key] = $this->maskLinhaDigitavel((string) $payload[$key]);
            }
        }

        foreach (['codigoBarras', 'codigo_barras'] as $key) {
            if (isset($payload[$key])) {
                $payload[$key] = $this->maskCodigoBarras((string) $payload[$key]);
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $pagador
     * @return array<string, mixed>
     */
    protected function maskPagador(array $pagador): array
    {
        if (isset($pagador['documento'])) {
            $pagador['documento'] = $this->maskDocumento((string) $pagador['documento']);
        }

        if (isset($pagador['nome'])) {
            $pagador['nome'] = $this->maskNome((string) $pagador['nome']);
        }

        return $pagador;
    }

    protected function maskDocumento(string $documento): string
    {
        $digits = preg_replace('/\D+/', '', $documento) ?: '';

        if ($digits === '' || strlen($digits) < 4) {
            return str_repeat('*', max(strlen($documento) - 2, 0)) . substr($documento, -2);
        }

        return substr($digits, 0, 3) . str_repeat('*', strlen($digits) - 6) . substr($digits, -3);
    }

    protected function maskNome(string $nome): string
    {
        if (mb_strlen($nome) <= 2) {
            return str_repeat('*', mb_strlen($nome));
        }

        return mb_substr($nome, 0, 1) . str_repeat('*', max(mb_strlen($nome) - 2, 1)) . mb_substr($nome, -1);
    }

    protected function maskLinhaDigitavel(string $linha): string
    {
        $digits = preg_replace('/\D+/', '', $linha) ?: '';

        if ($digits === '') {
            return '***';
        }

        if (strlen($digits) <= 5) {
            return str_repeat('*', strlen($digits));
        }

        return substr($digits, 0, 4) . str_repeat('*', strlen($digits) - 8) . substr($digits, -4);
    }

    protected function maskCodigoBarras(string $codigo): string
    {
        return $this->maskLinhaDigitavel($codigo);
    }
}
