@php
    $contratoCodigo = $contrato?->codigo_contrato ?? ('Contrato #' . $fatura->contrato_id);
    $competencia = optional($fatura->competencia)->format('m/Y');
    $vencimento = optional($fatura->vencimento)->format('d/m/Y');
    $valorTotal = 'R$ ' . number_format((float) $fatura->valor_total, 2, ',', '.');
    $itens = $fatura->relationLoaded('itens') ? $fatura->itens : $fatura->itens()->limit(5)->get();
@endphp

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $contratoCodigo }} - Fatura {{ $competencia }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background-color: #f5f6fa;
            color: #1f2937;
            margin: 0;
            padding: 32px 0;
        }
        .wrapper {
            max-width: 620px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
        }
        .header {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: #e2e8f0;
            padding: 28px 32px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .header p {
            margin: 6px 0 0;
            font-size: 13px;
            color: #cbd5f5;
        }
        .content {
            padding: 28px 32px;
        }
        .summary {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 24px;
        }
        .summary h2 {
            margin: 0 0 10px;
            font-size: 16px;
            color: #0f172a;
        }
        .summary p {
            margin: 4px 0;
            font-size: 13px;
            color: #475569;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        .items-table thead {
            background: #1e293b;
            color: #e2e8f0;
        }
        .items-table th,
        .items-table td {
            padding: 10px 12px;
            font-size: 12.5px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        .items-table td:last-child,
        .items-table th:last-child {
            text-align: right;
        }
        .total {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin: 22px 0 0;
            text-align: right;
        }
        .cta {
            display: inline-block;
            margin: 28px 0 10px;
            padding: 12px 22px;
            border-radius: 999px;
            background: #0f172a;
            color: #f8fafc;
            text-decoration: none;
            font-weight: 600;
        }
        .footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 20px 32px;
            font-size: 12px;
            color: #64748b;
        }
        .custom-message {
            margin-top: 18px;
            padding: 16px 18px;
            border-radius: 12px;
            background: #eef2ff;
            color: #312e81;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>Fortress Empreendimentos</h1>
            <p>{{ $contratoCodigo }}</p>
        </div>

        <div class="content">
            <div class="summary">
                <h2>Olá, {{ $fatura->contrato?->locatario?->nome_razao_social ?? 'cliente' }}!</h2>
                <p>Segue o resumo da fatura referente à competência {{ $competencia ?? '—' }}.</p>
                <p><strong>Vencimento:</strong> {{ $vencimento ?? '—' }}</p>
                <p><strong>Valor para pagamento:</strong> {{ $valorTotal }}</p>
            </div>

            @if ($customMessage)
                <div class="custom-message">
                    {!! nl2br(e($customMessage)) !!}
                </div>
            @endif

            @if ($itens->isNotEmpty())
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th>Descrição</th>
                            <th style="text-align:right;">Qtd</th>
                            <th style="text-align:right;">Valor Unitário</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($itens as $item)
                            <tr>
                                <td>{{ $item->categoria }}</td>
                                <td>{{ $item->descricao ?? '---' }}</td>
                                <td style="text-align:right;">{{ number_format((float) $item->quantidade, 0, ',', '.') }}</td>
                                <td style="text-align:right;">{{ 'R$ ' . number_format((float) $item->valor_total, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <p class="total">Total: {{ $valorTotal }}</p>

            <a href="{{ $billingUrl }}" class="cta" target="_blank" rel="noopener noreferrer">
                Visualizar fatura completa
            </a>

            <p style="font-size:12px; color:#475569; margin-top:12px;">
                Se preferir, você também pode <a href="{{ $receiptUrl }}" style="color:#1d4ed8; text-decoration:none;" target="_blank" rel="noopener noreferrer">acessar o recibo detalhado</a>.
            </p>

            <p style="font-size:12px; color:#475569; margin-top:20px;">
                Em caso de dúvidas, entre em contato com o suporte da Fortress Empreendimentos pelos canais oficiais.
            </p>
        </div>

        <div class="footer">
            Fortress Empreendimentos<br>
            Tel: (11) 97279-4688 ou (11) 99388-2274 · contato@fortressempreendimentos.com.br
        </div>
    </div>
</body>
</html>
