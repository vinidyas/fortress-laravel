<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4;
            margin: 10mm 14mm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10.5px;
            color: #111;
            margin: 0;
        }

        .boleto {
            border: 1px solid #000;
            padding: 8px 12px;
        }

        .section-title {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 3px;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #000;
            padding-bottom: 3px;
            margin-bottom: 3px;
            page-break-inside: avoid;
        }

        .bank {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .bank-code {
            font-size: 16px;
            font-weight: 700;
        }

        .line-digitavel {
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 1.5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            font-size: 7.5px;
            font-weight: 700;
            text-transform: uppercase;
            color: #555;
            padding: 3px;
        }

        td {
            font-size: 10px;
            padding: 3px;
        }

        .field-table td,
        .field-table th {
            border: 1px solid #000;
        }

        .field-table td {
            min-height: 14px;
        }

        .section-block {
            page-break-inside: avoid;
            break-inside: avoid;
            page-break-after: avoid;
            break-after: avoid;
        }

        .recibo {
            margin-bottom: 8px;
        }

        .cut-line {
            margin: 8px 0;
            border-top: 1px dashed #000;
            position: relative;
            page-break-after: avoid;
        }

        .cut-line span {
            position: absolute;
            top: -6px;
            left: 8px;
            background: #fff;
            font-size: 8px;
            padding: 0 4px;
        }

        .instructions {
            border: 1px solid #000;
            padding: 5px 7px;
        }

        .instructions ul {
            margin: 0;
            padding-left: 16px;
        }

        .instructions li {
            margin-bottom: 2px;
        }

        .sacado {
            border: 1px solid #000;
            border-top: none;
            padding: 6px 8px;
        }

        .barcode {
            margin-top: 8px;
            text-align: center;
        }

        .barcode img {
            width: 100%;
            max-width: 460px;
            height: auto;
        }

        .barcode-text {
            font-size: 11px;
            letter-spacing: 2px;
            margin-top: 4px;
        }

        .label {
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .small {
            font-size: 8.5px;
        }
    </style>
</head>

<body>
@php
    $logoPath = base_path('docs/Banco_Bradesco_logo.svg.png');
    $logoBase64 = file_exists($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
    $localPagamento = 'Pagável preferencialmente na rede Bradesco ou por qualquer banco.';
    $cedente = 'INOVA FOODS CONSULTORIA E REPRESENTAÇÃO | CNPJ: 05.154.363/0001-98';
    $agencia = trim(($beneficiario['agencia'] ?? '2681') . ' / ' . ($beneficiario['conta'] ?? '2863'));
    $dataDocumento = optional($boleto->registrado_em ?? $fatura?->created_at)->format('d/m/Y');
    $dataProcessamento = optional($boleto->created_at ?? $fatura?->updated_at)->format('d/m/Y');
    $valorFormatado = number_format((float) ($valor ?? 0), 2, ',', '.');
    $sacadoLinha = trim(($pagador['nome'] ?? '') . ' | CPF/CNPJ: ' . ($pagador['documento'] ?? ''));
    $sacadoEndereco = $pagador['endereco'] ?? '';
    $contratoImovel = trim(($contratoCodigo ?? '-') . ' - ' . ($imovel['descricao'] ?? $imovel['codigo'] ?? ''));
@endphp
    <div class="boleto">
        @foreach(['recibo' => 'Recibo do Pagador', 'ficha' => 'Ficha de Compensação'] as $section => $label)
            @php $isRecibo = $section === 'recibo'; @endphp
            <div class="section-block" style="margin-bottom: {{ $loop->last ? '0' : '18px' }};">
                <div class="header">
                    <div class="bank">
                        @if($logoBase64)
                            <img src="{{ $logoBase64 }}" alt="Bradesco" style="height:34px;">
                        @endif
                        <div class="bank-code">237-2</div>
                    </div>
                    <div class="line-digitavel">{{ $linhaDigitavel }}</div>
                </div>
                <table class="field-table">
                    <tr>
                        <th style="width: 60%;">Local de pagamento</th>
                        <th style="width: 20%;">Vencimento</th>
                        <th style="width: 20%;">Agência/Código Beneficiário</th>
                    </tr>
                    <tr>
                        <td>{{ $localPagamento }}</td>
                        <td>{{ $vencimento }}</td>
                        <td>{{ $agencia ?: '---' }}</td>
                    </tr>
                    <tr>
                        <th colspan="2">Beneficiário</th>
                        <th>Uso do Banco</th>
                    </tr>
                    <tr>
                        <td colspan="2">{{ $cedente }}<br>{{ $imovel['endereco'] ?? '' }}</td>
                        <td>{{ $contratoCodigo ?? '' }}</td>
                    </tr>
                </table>
                <table class="field-table" style="margin-top:4px;">
                    <tr>
                        <th style="width: 16%;">Data do Doc.</th>
                        <th style="width: 18%;">Nº do documento</th>
                        <th style="width: 12%;">Espécie doc.</th>
                        <th style="width: 10%;">Aceite</th>
                        <th style="width: 16%;">Data Proc.</th>
                        <th style="width: 16%;">Nosso número</th>
                        <th style="width: 12%;">Valor</th>
                    </tr>
                    <tr>
                        <td>{{ $dataDocumento }}</td>
                        <td>{{ $documento }}</td>
                        <td>DM</td>
                        <td>N</td>
                        <td>{{ $dataProcessamento }}</td>
                        <td>{{ $nossoNumero }}</td>
                        <td>R$ {{ $valorFormatado }}</td>
                    </tr>
                </table>
                <div class="section-title" style="margin-top:8px;">{{ $isRecibo ? 'Instruções' : 'Instruções do beneficiário' }}</div>
                <div class="instructions">
                    <ul>
                        @foreach($instructions as $mensagem)
                            <li>{{ $mensagem }}</li>
                        @endforeach
                    </ul>
                </div>
                @unless($isRecibo)
                    <table class="field-table" style="margin-top:6px;">
                        <tr>
                            <th>(-) Descontos/Abatimentos</th>
                            <th>(-) Outras Deduções</th>
                            <th>(+) Mora/Multa</th>
                            <th>(+) Outros Acréscimos</th>
                            <th>(=) Valor Cobrado</th>
                        </tr>
                        <tr>
                            <td>R$ 0,00</td>
                            <td>R$ 0,00</td>
                            <td>R$ 0,00</td>
                            <td>R$ 0,00</td>
                            <td>R$ {{ $valorFormatado }}</td>
                        </tr>
                    </table>
                @endunless
                <div class="sacado">
                    <div class="label">Pagador</div>
                    <div>{{ $sacadoLinha }}</div>
                    <div>{{ $sacadoEndereco }}</div>
                    <div class="small">Contrato / Imóvel: {{ $contratoImovel }}</div>
                </div>
                @unless($isRecibo)
                    <div class="barcode">
                        @if($barcodeImage)
                            <img src="{{ $barcodeImage }}" alt="Código de barras">
                        @endif
                        @if($codigoBarras)
                            <div class="barcode-text">{{ $codigoBarras }}</div>
                        @endif
                    </div>
                @endunless
                <div style="text-align:right;font-size:10px;margin-top:4px;">{{ $label }}</div>
            </div>
            @if($loop->first)
                <div class="cut-line"><span>AUTENTICAÇÃO MECÂNICA</span></div>
            @endif
        @endforeach
    </div>
</body>

</html>
