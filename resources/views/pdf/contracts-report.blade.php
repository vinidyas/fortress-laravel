<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Contratos</title>
    <style>
        @page {
            margin: 18mm 12mm 18mm 12mm;
        }
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #1f2937;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }
        h1 {
            font-size: 20px;
            text-align: center;
            margin: 0 0 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #111827;
        }
        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .summary td {
            font-size: 11px;
            padding: 4px 6px;
            color: #4b5563;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th {
            background-color: #111827;
            color: #f9fafb;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 10px;
            padding: 8px 6px;
            text-align: left;
        }
        td {
            padding: 8px 6px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
            font-size: 11px;
        }
        .muted {
            color: #6b7280;
            font-size: 10px;
        }
        .text-right {
            text-align: right;
        }
    </style>
</head>
<body>
    <h1>Relatório de Contratos</h1>

    <table class="summary">
        <tr>
            <td><strong>Gerado em:</strong> {{ ($generated_at ?? now())->format('d/m/Y H:i') }}</td>
            <td><strong>Status:</strong> {{ !empty($filters['only_active']) ? 'Somente ativos' : 'Todos os contratos' }}</td>
        </tr>
        <tr>
            <td>
                <strong>Período:</strong>
                @if(($filters['date_start'] ?? null) || ($filters['date_end'] ?? null))
                    {{ $filters['date_start'] ? \Carbon\Carbon::parse($filters['date_start'])->format('d/m/Y') : 'Início' }}
                    —
                    {{ $filters['date_end'] ? \Carbon\Carbon::parse($filters['date_end'])->format('d/m/Y') : 'Hoje' }}
                @else
                    Não informado
                @endif
            </td>
            <td>
                <strong>Base de data:</strong>
                {{ ($filters['date_field'] ?? 'inicio') === 'fim' ? 'Data de término' : 'Data de início' }}
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width: 13%;">Contrato</th>
                <th style="width: 27%;">Imóvel</th>
                <th style="width: 22%;">Pagador</th>
                <th style="width: 12%;">Início</th>
                <th style="width: 12%;">Fim</th>
                <th style="width: 12%;">Reajuste</th>
                <th style="width: 12%;">Valor</th>
            </tr>
        </thead>
        <tbody>
            @forelse($contracts as $contrato)
                <tr>
                    <td>
                        <div>{{ $contrato['codigo'] ?? '—' }}</div>
                        <div class="muted">{{ $contrato['status'] ?? '—' }}</div>
                    </td>
                    <td>
                        <div>{{ $contrato['imovel_label'] ?? '—' }}</div>
                        <div class="muted">{{ $contrato['imovel_info'] ?? '—' }}</div>
                    </td>
                    <td>{{ $contrato['locatario'] ?? '—' }}</td>
                    <td>{{ $contrato['data_inicio'] ?? '—' }}</td>
                    <td>{{ $contrato['data_fim'] ?? '—' }}</td>
                    <td>{{ $contrato['proximo_reajuste'] ?? '—' }}</td>
                    <td class="text-right">
                        {{ isset($contrato['valor_aluguel']) ? 'R$ '.number_format($contrato['valor_aluguel'], 2, ',', '.') : '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-right">Nenhum contrato encontrado para os filtros selecionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
