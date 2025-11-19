<?php

declare(strict_types=1);

namespace App\Services\Boleto;

use App\Models\FaturaBoleto;
use App\Models\FaturaAnexo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Picqer\Barcode\BarcodeGeneratorPNG;

class BoletoPdfService
{
    public function generate(FaturaBoleto $boleto): \Barryvdh\DomPDF\PDF
    {
        $boleto->loadMissing([
            'fatura.contrato.locador',
            'fatura.contrato.locatario',
            'fatura.contrato.imovel',
            'fatura.itens',
        ]);

        $fatura = $boleto->fatura;
        $contrato = $fatura?->contrato;
        $locador = $contrato?->locador;
        $locatario = $contrato?->locatario;
        $imovel = $contrato?->imovel;

        $responsePayload = $boleto->response_payload ?? [];
        $payload = $boleto->payload ?? [];

        $linhaDigitavel = $this->sanitizeDigits(
            $boleto->linha_digitavel
            ?? Arr::get($responsePayload, 'titulo.linhaDig')
            ?? Arr::get($responsePayload, 'linhaDigitavel')
        );

        $codigoBarras = $this->sanitizeDigits(
            $boleto->codigo_barras
            ?? Arr::get($responsePayload, 'titulo.codBarras')
            ?? Arr::get($responsePayload, 'codigoBarras')
        );

        $codigoBarras = $this->resolveCodigoBarras($codigoBarras, $linhaDigitavel);

        $instructions = $this->collectInstructions($responsePayload, $payload, $fatura?->itens ?? Collection::empty());

        $barcodeImage = $this->generateBarcodeImage($codigoBarras);

        $dados = [
            'boleto' => $boleto,
            'fatura' => $fatura,
            'contrato' => $contrato,
            'beneficiario' => [
                'nome' => $locador?->nome_razao_social ?? 'Beneficiário não informado',
                'documento' => $this->formatDocumento($locador?->cpf_cnpj),
                'endereco' => $this->formatEnderecoPessoa($locador),
                'agencia' => $this->resolveAgencia($payload, $responsePayload),
                'conta' => $this->resolveConta($payload, $responsePayload),
            ],
            'pagador' => [
                'nome' => $locatario?->nome_razao_social ?? 'Pagador não informado',
                'documento' => $this->formatDocumento($locatario?->cpf_cnpj),
                'endereco' => $this->formatEnderecoPessoa($locatario),
                'email' => $locatario?->email,
            ],
            'imovel' => $imovel ? [
                'codigo' => $imovel->codigo,
                'descricao' => trim($imovel->descricao ?? ''),
                'endereco' => $this->formatEndereco([
                    $imovel->rua,
                    $imovel->numero,
                    $imovel->bairro,
                    $imovel->cidade,
                    $imovel->estado,
                    $imovel->cep,
                ]),
            ] : null,
            'valor' => $boleto->valor ?? $fatura?->valor_total ?? 0,
            'vencimento' => optional($boleto->vencimento ?? $fatura?->vencimento)?->format('d/m/Y'),
            'documento' => $boleto->document_number ?? $fatura?->id,
            'nossoNumero' => $boleto->nosso_numero,
            'contratoCodigo' => $contrato?->codigo_contrato,
            'linhaDigitavel' => $this->formatLinhaDigitavel($linhaDigitavel),
            'codigoBarras' => $codigoBarras,
            'barcodeImage' => $barcodeImage,
            'instructions' => $instructions,
            'itens' => $fatura?->itens ?? collect(),
        ];

        return Pdf::loadView('pdf.boletos.bradesco', $dados)->setPaper('a4');
    }

    public function storeAsAttachment(FaturaBoleto $boleto): ?FaturaAnexo
    {
        $fatura = $boleto->fatura ?: $boleto->load('fatura')->fatura;

        if (! $fatura) {
            return null;
        }

        $pdf = $this->generate($boleto);
        $contents = $pdf->output();
        $disk = Storage::disk('public');
        $directory = sprintf('faturas/%d/boletos', $fatura->id);
        $disk->makeDirectory($directory);

        $identifier = $boleto->nosso_numero
            ?: $boleto->external_id
            ?: (string) $boleto->getKey();

        $baseName = sprintf('boleto-bradesco-%s', Str::slug($identifier ?: 'boleto'));
        $filename = $baseName.'.pdf';
        $path = $directory.'/'.$filename;

        $disk->put($path, $contents);

        $displayName = sprintf(
            'Boleto Bradesco - %s',
            $boleto->nosso_numero ?: $boleto->document_number ?: $boleto->getKey()
        );

        $attachment = $fatura->anexos()
            ->where('path', $path)
            ->orWhere('display_name', $displayName)
            ->first();

        $payload = [
            'path' => $path,
            'original_name' => $filename,
            'display_name' => $displayName,
            'mime_type' => 'application/pdf',
            'size' => strlen($contents),
            'uploaded_by' => null,
        ];

        if ($attachment) {
            $attachment->update($payload);
        } else {
            $attachment = $fatura->anexos()->create($payload);
        }

        return $attachment;
    }

    private function collectInstructions(array $responsePayload, array $payload, Collection $itens): array
    {
        $messages = collect(Arr::get($responsePayload, 'lista', []))
            ->pluck('mensagem')
            ->filter()
            ->values();

        if ($messages->isEmpty()) {
            $messages = collect(Arr::get($payload, 'listaMsgs', []))
                ->map(fn ($item) => is_array($item) ? ($item['mensagem'] ?? null) : $item)
                ->filter();
        }

        if ($messages->isEmpty()) {
            $itemsDescriptions = $itens->map(function ($item) {
                $categoria = Str::upper($item->categoria ?? 'ITEM');
                $valor = number_format((float) $item->valor_total, 2, ',', '.');

                return "{$categoria} .......... R$ {$valor}";
            });

            $messages = collect([
                'VALORES EXPRESSOS EM REAIS',
                'JUROS E MULTA CONFORME CONTRATO',
            ])->merge($itemsDescriptions)->filter();
        }

        return $messages->take(6)->values()->all();
    }

    private function generateBarcodeImage(?string $codigoBarras): ?string
    {
        if (! $codigoBarras || strlen($codigoBarras) !== 44) {
            return null;
        }

        $generator = new BarcodeGeneratorPNG();
        $binary = $generator->getBarcode($codigoBarras, $generator::TYPE_INTERLEAVED_2_5, 2, 60);

        return 'data:image/png;base64,'.base64_encode($binary);
    }

    private function formatDocumento(?string $value): string
    {
        $digits = $this->sanitizeDigits($value);
        $length = strlen($digits);

        if ($length === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
        }

        if ($length === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits);
        }

        return $value ?: '';
    }

    private function formatEnderecoPessoa(?object $pessoa): string
    {
        if (! $pessoa) {
            return '';
        }

        return $this->formatEndereco([
            $pessoa->rua,
            $pessoa->numero,
            $pessoa->bairro,
            $pessoa->cidade,
            $pessoa->estado,
            $pessoa->cep,
        ]);
    }

    private function formatEndereco(array $partes): string
    {
        $limpos = array_values(array_filter(array_map(function ($parte) {
            if (! $parte) {
                return null;
            }

            return is_string($parte) ? trim($parte) : $parte;
        }, $partes)));

        return implode(', ', $limpos);
    }

    private function formatLinhaDigitavel(?string $linhaDigitavel): string
    {
        $digits = $this->sanitizeDigits($linhaDigitavel);

        if (strlen($digits) !== 47) {
            return $linhaDigitavel ?? '';
        }

        return sprintf(
            '%s.%s %s.%s %s.%s %s %s',
            substr($digits, 0, 5),
            substr($digits, 5, 5),
            substr($digits, 10, 5),
            substr($digits, 15, 6),
            substr($digits, 21, 5),
            substr($digits, 26, 6),
            substr($digits, 32, 1),
            substr($digits, 33)
        );
    }

    private function sanitizeDigits(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        return $digits ?: null;
    }

    private function resolveAgencia(array $payload, array $responsePayload): ?string
    {
        if ($agencia = Arr::get($responsePayload, 'titulo.agencCred')) {
            return (string) $agencia;
        }

        if ($agencia = Arr::get($payload, 'agenciaDoDebAutomatico')) {
            return (string) $agencia;
        }

        return null;
    }

    private function resolveConta(array $payload, array $responsePayload): ?string
    {
        if ($conta = Arr::get($responsePayload, 'titulo.ctaCred')) {
            $digito = Arr::get($responsePayload, 'titulo.digCred');

            return $digito ? sprintf('%s-%s', $conta, $digito) : (string) $conta;
        }

        if ($conta = Arr::get($payload, 'contaDoDebAutomatico')) {
            $digito = Arr::get($payload, 'digitoAgenciaDoDebAutomat');

            return $digito ? sprintf('%s-%s', $conta, $digito) : (string) $conta;
        }

        return null;
    }

    private function resolveCodigoBarras(?string $codigoBarras, ?string $linhaDigitavel): ?string
    {
        if ($codigoBarras && strlen($codigoBarras) === 44) {
            return $codigoBarras;
        }

        $linha = $this->sanitizeDigits($linhaDigitavel);

        if (strlen($linha) !== 47) {
            return $codigoBarras;
        }

        $campo1 = substr($linha, 0, 9);
        $campo2 = substr($linha, 10, 10);
        $campo3 = substr($linha, 21, 10);
        $dvGeral = substr($linha, 32, 1);
        $campo5 = substr($linha, 33, 14);

        $freeField = substr($campo1, 4).$campo2.$campo3;

        return substr($campo1, 0, 4).$dvGeral.$campo5.$freeField;
    }
}
